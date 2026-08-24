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

class CreditInstallmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($this->user);

        $this->account = $this->user->accounts()->firstOrFail();
        $this->account->forceFill(['opening_balance' => 50_000_000, 'balance' => 50_000_000])->save();
    }

    private function makeCredit(array $attributes = []): Credit
    {
        $category = $this->user->categories()->where('name', 'Tagihan')->firstOrFail();

        return $this->user->credits()->create(CreditService::withDerivedColumns(array_merge([
            'name' => 'Cicilan Motor',
            'total_amount' => 24_000_000,
            'interest_rate' => 8.5,
            'monthly_installment' => 750_000,
            'start_date' => '2026-01-10',
            'end_date' => '2028-12-10',
            'due_day' => 10,
            'account_id' => $this->account->getKey(),
            'category_id' => $category->getKey(),
        ], $attributes)));
    }

    public function test_tenor_dihitung_dari_tanggal_mulai_dan_target_selesai(): void
    {
        $credit = $this->makeCredit();

        $this->assertSame(36, $credit->tenor_months);
        $this->assertSame(36, $credit->remaining_months);
        $this->assertSame('active', $credit->status->value);
        $this->assertSame('Bulan ke-1 dari 36', CreditService::present($credit)['progress_label']);
    }

    public function test_tanggal_jatuh_tempo_dijepit_ke_akhir_bulan(): void
    {
        $credit = $this->makeCredit([
            'start_date' => '2026-01-31',
            'end_date' => '2026-04-30',
            'due_day' => 31,
        ]);

        // Februari 2026 hanya sampai tanggal 28.
        $this->assertSame('2026-02-28', $credit->dueDateFor(2)->toDateString());
        $this->assertSame('2026-03-31', $credit->dueDateFor(3)->toDateString());
    }

    public function test_scheduler_membuat_tagihan_angsuran_dan_tidak_menduplikasi(): void
    {
        $credit = $this->makeCredit();
        $today = CarbonImmutable::parse('2026-01-05');

        $service = app(CreditService::class);

        $this->assertNotNull($service->generateNextBill($credit, $today));
        $this->assertNull($service->generateNextBill($credit->refresh(), $today), 'tagihan kedua tidak boleh dibuat');

        $bill = Bill::query()->where('credit_id', $credit->getKey())->sole();

        $this->assertSame(1, $bill->installment_number);
        $this->assertSame('2026-01-10', $bill->due_date->toDateString());
        $this->assertEquals(750_000, (float) $bill->amount);
        $this->assertSame('Cicilan Motor - Cicilan 1/36', $bill->title);
    }

    public function test_tagihan_belum_dibuat_bila_jatuh_tempo_masih_jauh(): void
    {
        $credit = $this->makeCredit();

        $this->assertNull(
            app(CreditService::class)->generateNextBill($credit, CarbonImmutable::parse('2025-11-01'))
        );

        $this->assertSame(0, Bill::query()->whereNotNull('credit_id')->count());
    }

    public function test_membayar_tagihan_cicilan_mengurangi_sisa_tenor(): void
    {
        $credit = $this->makeCredit();
        $bill = app(CreditService::class)->generateNextBill($credit, CarbonImmutable::parse('2026-01-05'));

        $this->actingAs($this->user)
            ->post("/bills/{$bill->getKey()}/pay", [
                'account_id' => $this->account->getKey(),
                'paid_on' => '2026-01-10',
            ])
            ->assertRedirect();

        $credit->refresh();

        $this->assertSame(35, $credit->remaining_months);
        $this->assertSame('Bulan ke-2 dari 36', CreditService::present($credit)['progress_label']);
        $this->assertSame('paid', $bill->refresh()->status->value);

        // Saldo akun ikut terpotong lewat transaksi yang dibuat otomatis.
        $this->assertEquals(50_000_000 - 750_000, (float) $this->account->refresh()->balance);

        // Tagihan angsuran berikutnya langsung disiapkan.
        $next = Bill::query()->where('credit_id', $credit->getKey())->where('installment_number', 2)->first();
        $this->assertNotNull($next);
        $this->assertSame('2026-02-10', $next->due_date->toDateString());
    }

    public function test_membatalkan_pembayaran_mengembalikan_sisa_tenor(): void
    {
        $credit = $this->makeCredit();
        $bill = app(CreditService::class)->generateNextBill($credit, CarbonImmutable::parse('2026-01-05'));

        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/pay", [
            'account_id' => $this->account->getKey(),
        ]);

        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/unpay")->assertRedirect();

        $credit->refresh();

        $this->assertSame(36, $credit->remaining_months);
        $this->assertEquals(50_000_000, (float) $this->account->refresh()->balance);

        // Angsuran berikutnya yang sempat dibuat ikut ditarik kembali.
        $this->assertSame(0, Bill::query()->where('credit_id', $credit->getKey())
            ->where('installment_number', '>', 1)->count());
    }

    public function test_kredit_menjadi_lunas_saat_angsuran_terakhir_dibayar(): void
    {
        $credit = $this->makeCredit(['remaining_months' => 1]);
        $service = app(CreditService::class);

        $service->registerPayment($credit);

        $this->assertSame(0, $credit->remaining_months);
        $this->assertSame('paid_off', $credit->status->value);

        // Kredit lunas tidak lagi menghasilkan tagihan baru.
        $this->assertNull($service->generateNextBill($credit->refresh(), CarbonImmutable::parse('2028-12-01')));
    }

    public function test_user_lain_tidak_bisa_mengubah_kredit(): void
    {
        $credit = $this->makeCredit();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->delete("/credits/{$credit->getKey()}")
            ->assertForbidden();

        $this->assertDatabaseHas('credits', ['id' => $credit->getKey()]);
    }
}
