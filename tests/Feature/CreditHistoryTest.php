<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Credit;
use App\Models\User;
use App\Services\CreditService;
use App\Services\DefaultDataProvisioner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Waktu dibekukan supaya jendela penagihan 31 hari deterministik:
        // angsuran Februari masuk jendela, angsuran Maret belum.
        $this->travelTo(CarbonImmutable::parse('2026-01-12'));

        $this->user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($this->user);

        $this->account = $this->user->accounts()->firstOrFail();
        $this->account->forceFill(['opening_balance' => 50_000_000, 'balance' => 50_000_000])->save();
    }

    private function makeCredit(): Credit
    {
        return $this->user->credits()->create(CreditService::withDerivedColumns([
            'name' => 'KPR Rumah',
            'total_amount' => 36_000_000,
            'monthly_installment' => 3_000_000,
            'start_date' => '2026-01-10',
            'end_date' => '2026-12-10',
            'due_day' => 10,
            'account_id' => $this->account->getKey(),
        ]));
    }

    public function test_jadwal_angsuran_menampilkan_seluruh_tenor(): void
    {
        $credit = $this->makeCredit();
        $schedule = app(CreditService::class)->schedule($credit);

        $this->assertCount(12, $schedule);
        $this->assertSame('planned', $schedule->first()['state']);
        $this->assertSame('2026-12-10', $schedule->last()['due_date']);
    }

    public function test_histori_menandai_angsuran_yang_sudah_lunas(): void
    {
        $credit = $this->makeCredit();
        $service = app(CreditService::class);
        $bill = $service->generateNextBill($credit);

        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/pay", [
            'account_id' => $this->account->getKey(),
            'paid_on' => '2026-01-10',
        ]);

        $schedule = $service->schedule($credit->refresh());

        $this->assertSame('paid', $schedule[0]['state']);
        $this->assertSame('Lunas', $schedule[0]['state_label']);
        $this->assertNotNull($schedule[0]['paid_at']);
        $this->assertSame('billed', $schedule[1]['state'], 'angsuran ke-2 sudah ditagih otomatis');
    }

    public function test_halaman_detail_kredit_bisa_diakses_pemiliknya(): void
    {
        $credit = $this->makeCredit();

        $this->actingAs($this->user)->get("/credits/{$credit->getKey()}")->assertOk();
        $this->actingAs(User::factory()->create())->get("/credits/{$credit->getKey()}")->assertForbidden();
    }

    public function test_tidak_bisa_menagih_lebih_awal_saat_angsuran_berjalan_belum_lunas(): void
    {
        $credit = $this->makeCredit();
        app(CreditService::class)->generateNextBill($credit);

        $this->actingAs($this->user)
            ->post("/credits/{$credit->getKey()}/next-installment")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Bill::query()->where('credit_id', $credit->getKey())->count());
    }

    public function test_bisa_menagih_angsuran_berikutnya_lebih_awal_setelah_lunas(): void
    {
        $credit = $this->makeCredit();
        $service = app(CreditService::class);

        // Angsuran 1 ditagih dan dilunasi.
        $bill = $service->generateNextBill($credit);
        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/pay", [
            'account_id' => $this->account->getKey(),
            'paid_on' => '2026-01-10',
        ]);

        // Angsuran 2 dibuat otomatis, dilunasi juga.
        $second = Bill::query()->where('credit_id', $credit->getKey())->where('installment_number', 2)->sole();
        $this->actingAs($this->user)->post("/bills/{$second->getKey()}/pay", [
            'account_id' => $this->account->getKey(),
        ]);

        // Angsuran 3 jatuh tempo Maret, di luar jendela 31 hari, jadi hanya bisa
        // muncul lewat tombol "Tagih Angsuran Berikutnya".
        $this->assertSame(0, Bill::query()->where('credit_id', $credit->getKey())
            ->where('installment_number', 3)->count());

        $this->actingAs($this->user)
            ->post("/credits/{$credit->getKey()}/next-installment")
            ->assertRedirect()
            ->assertSessionHas('success');

        $third = Bill::query()->where('credit_id', $credit->getKey())->where('installment_number', 3)->sole();

        $this->assertSame('2026-03-10', $third->due_date->toDateString());
        $this->assertSame('unpaid', $third->status->value);
        $this->assertEquals(3_000_000, (float) $third->amount);
    }

    public function test_membayar_di_muka_terus_menerus_mengurangi_tenor_dengan_benar(): void
    {
        $credit = $this->makeCredit();
        $service = app(CreditService::class);

        $bill = $service->generateNextBill($credit);

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/pay", [
                'account_id' => $this->account->getKey(),
            ]);

            $this->actingAs($this->user)->post("/credits/{$credit->getKey()}/next-installment");

            $bill = Bill::query()
                ->where('credit_id', $credit->getKey())
                ->where('status', 'unpaid')
                ->first();

            if ($bill === null) {
                break;
            }
        }

        $credit->refresh();

        $this->assertSame(8, $credit->remaining_months, '4 angsuran lunas dari 12');
        $this->assertSame('Bulan ke-5 dari 12', CreditService::present($credit)['progress_label']);
        $this->assertEquals(50_000_000 - (4 * 3_000_000), (float) $this->account->refresh()->balance);
    }
}
