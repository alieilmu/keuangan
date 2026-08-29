<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Bill;
use App\Models\SavingsGoal;
use App\Models\Transfer;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\SavingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsGoalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $source;

    private Account $storage;

    protected function setUp(): void
    {
        parent::setUp();

        // Waktu dibekukan agar jendela penagihan 31 hari deterministik.
        $this->travelTo(CarbonImmutable::parse('2026-01-12'));

        $this->user = User::factory()->create();

        $this->source = $this->account('BCA Gaji', 20_000_000, AccountType::Bank, '1234567890');
        $this->storage = $this->account('BSI Tabungan', 0, AccountType::Bank, '7770001111');
    }

    private function account(string $name, float $balance, AccountType $type, string $number): Account
    {
        return $this->user->accounts()->create([
            'name' => $name,
            'type' => $type->value,
            'account_number' => $number,
            'opening_balance' => $balance,
            'balance' => $balance,
            'color' => '#10b981',
            'is_active' => true,
        ]);
    }

    private function makeGoal(array $overrides = []): SavingsGoal
    {
        return $this->user->savingsGoals()->create(SavingsService::withDerivedColumns(array_merge([
            'name' => 'Dana Darurat',
            'target_amount' => 12_000_000,
            'monthly_contribution' => 1_000_000,
            'start_date' => '2026-01-10',
            'due_day' => 10,
            'source_account_id' => $this->source->getKey(),
            'storage_account_id' => $this->storage->getKey(),
        ], $overrides)));
    }

    public function test_akun_sumber_dan_penyimpanan_harus_berbeda(): void
    {
        $this->actingAs($this->user)->post('/savings', [
            'name' => 'Dana Darurat',
            'target_amount' => 12_000_000,
            'monthly_contribution' => 1_000_000,
            'start_date' => '2026-01-10',
            'due_day' => 10,
            'source_account_id' => $this->source->getKey(),
            'storage_account_id' => $this->source->getKey(),
        ])->assertSessionHasErrors('storage_account_id');

        $this->assertSame(0, SavingsGoal::query()->count());
    }

    public function test_target_baru_langsung_membuat_tagihan_setoran(): void
    {
        $this->actingAs($this->user)->post('/savings', [
            'name' => 'Dana Darurat',
            'target_amount' => 12_000_000,
            'monthly_contribution' => 1_000_000,
            'start_date' => '2026-01-10',
            'due_day' => 10,
            'source_account_id' => $this->source->getKey(),
            'storage_account_id' => $this->storage->getKey(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $bill = Bill::query()->sole();

        $this->assertSame(1, $bill->contribution_number);
        $this->assertEquals(1_000_000, (float) $bill->amount);
        $this->assertSame('2026-01-10', $bill->due_date->toDateString());
        $this->assertSame($this->source->getKey(), $bill->account_id);
    }

    public function test_membayar_setoran_memindahkan_dana_bukan_mencatat_pengeluaran(): void
    {
        $goal = $this->makeGoal();
        $bill = app(SavingsService::class)->generateNextBill($goal);

        $this->actingAs($this->user)
            ->post("/bills/{$bill->getKey()}/pay", ['account_id' => $this->source->getKey()])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Saldo berpindah dari sumber ke penyimpanan.
        $this->assertEquals(19_000_000, (float) $this->source->refresh()->balance);
        $this->assertEquals(1_000_000, (float) $this->storage->refresh()->balance);

        // Tercatat sebagai transfer yang tertaut ke target tabungan.
        $transfer = Transfer::query()->sole();
        $this->assertSame($goal->getKey(), $transfer->savings_goal_id);
        $this->assertCount(2, $transfer->transactions()->get());

        // Tidak ada pengeluaran yang tercatat.
        $summary = app(DashboardService::class)
            ->cashflowSummary($this->user, 2026, 1);
        $this->assertEquals(0, $summary['expense']);

        $this->assertEquals(1_000_000, $goal->refresh()->savedAmount());
    }

    public function test_setoran_berikutnya_dibuat_setelah_setoran_sebelumnya_lunas(): void
    {
        $goal = $this->makeGoal();
        $service = app(SavingsService::class);
        $bill = $service->generateNextBill($goal);

        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/pay", [
            'account_id' => $this->source->getKey(),
        ]);

        $second = Bill::query()->where('contribution_number', 2)->sole();

        $this->assertSame('2026-02-10', $second->due_date->toDateString());
        $this->assertSame('unpaid', $second->status->value);
    }

    public function test_membatalkan_setoran_mengembalikan_dana_ke_akun_sumber(): void
    {
        $goal = $this->makeGoal();
        $bill = app(SavingsService::class)->generateNextBill($goal);

        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/pay", [
            'account_id' => $this->source->getKey(),
        ]);

        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/unpay")->assertRedirect();

        $this->assertEquals(20_000_000, (float) $this->source->refresh()->balance);
        $this->assertEquals(0, (float) $this->storage->refresh()->balance);
        $this->assertSame(0, Transfer::query()->count());
        $this->assertEquals(0, $goal->refresh()->savedAmount());
        $this->assertSame('unpaid', $bill->refresh()->status->value);
    }

    public function test_target_ditandai_tercapai_saat_dana_terkumpul_penuh(): void
    {
        $goal = $this->makeGoal(['target_amount' => 1_000_000]);
        $bill = app(SavingsService::class)->generateNextBill($goal);

        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/pay", [
            'account_id' => $this->source->getKey(),
        ]);

        $this->assertSame('completed', $goal->refresh()->status->value);
        // Target tercapai: tidak ada tagihan setoran baru.
        $this->assertSame(1, Bill::query()->count());
    }

    public function test_setor_lebih_awal_hanya_saat_tidak_ada_setoran_menunggu(): void
    {
        $goal = $this->makeGoal();
        $service = app(SavingsService::class);
        $bill = $service->generateNextBill($goal);

        // Masih ada setoran menunggu -> ditolak.
        $this->actingAs($this->user)
            ->post("/savings/{$goal->getKey()}/next-contribution")
            ->assertSessionHas('error');

        $this->assertSame(1, Bill::query()->count());

        $this->actingAs($this->user)->post("/bills/{$bill->getKey()}/pay", [
            'account_id' => $this->source->getKey(),
        ]);

        // Setoran ke-2 dibuat otomatis, lunasi juga.
        $second = Bill::query()->where('contribution_number', 2)->sole();
        $this->actingAs($this->user)->post("/bills/{$second->getKey()}/pay", [
            'account_id' => $this->source->getKey(),
        ]);

        // Setoran ke-3 jatuh tempo Maret, di luar jendela -> butuh tombol.
        $this->assertSame(0, Bill::query()->where('contribution_number', 3)->count());

        $this->actingAs($this->user)
            ->post("/savings/{$goal->getKey()}/next-contribution")
            ->assertSessionHas('success');

        $this->assertSame(1, Bill::query()->where('contribution_number', 3)->count());
    }

    public function test_halaman_detail_hanya_untuk_pemiliknya(): void
    {
        $goal = $this->makeGoal();

        $this->actingAs($this->user)->get("/savings/{$goal->getKey()}")->assertOk();
        $this->actingAs(User::factory()->create())->get("/savings/{$goal->getKey()}")->assertForbidden();
    }
}
