<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function account(string $name, float $balance, AccountType $type = AccountType::Bank, ?string $number = null): Account
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

    public function test_transfer_memindahkan_saldo_secara_sinkron(): void
    {
        $from = $this->account('BCA', 5_000_000);
        $to = $this->account('GoPay', 100_000, AccountType::EWallet, '08123456789');

        $this->actingAs($this->user)->post('/transfers', [
            'from_account_id' => $from->getKey(),
            'to_account_id' => $to->getKey(),
            'amount' => 750_000,
            'transfer_date' => '2026-08-10',
            'description' => 'Top up e-wallet',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(4_250_000, (float) $from->refresh()->balance);
        $this->assertEquals(850_000, (float) $to->refresh()->balance);
    }

    public function test_transfer_mencatat_dua_sisi_mutasi(): void
    {
        $from = $this->account('BCA', 1_000_000);
        $to = $this->account('Mandiri', 0);

        $this->actingAs($this->user)->post('/transfers', [
            'from_account_id' => $from->getKey(),
            'to_account_id' => $to->getKey(),
            'amount' => 200_000,
            'transfer_date' => '2026-08-10',
        ]);

        $transfer = Transfer::query()->sole();
        $legs = Transaction::query()->where('transfer_id', $transfer->getKey())->get();

        $this->assertCount(2, $legs, 'harus ada mutasi keluar dan mutasi masuk');

        $out = $legs->firstWhere('account_id', $from->getKey());
        $in = $legs->firstWhere('account_id', $to->getKey());

        $this->assertSame('transfer_out', $out->type->value);
        $this->assertSame('transfer_in', $in->type->value);
        $this->assertEquals(200_000, (float) $out->amount);
        $this->assertEquals(200_000, (float) $in->amount);
    }

    public function test_transfer_sesama_bank_dan_nomor_rekening_sama_tetap_dicatat(): void
    {
        // Dua catatan akun untuk rekening Mandiri dengan nomor yang sama.
        $from = $this->account('Mandiri Suami', 3_000_000, AccountType::Bank, '1370011223344');
        $to = $this->account('Mandiri Bersama', 500_000, AccountType::Bank, '1370011223344');

        $this->actingAs($this->user)->post('/transfers', [
            'from_account_id' => $from->getKey(),
            'to_account_id' => $to->getKey(),
            'amount' => 1_000_000,
            'transfer_date' => '2026-08-11',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $transfer = Transfer::query()->sole();

        $this->assertTrue($transfer->isSameInstitution());
        $this->assertCount(2, $transfer->transactions()->get());
        $this->assertEquals(2_000_000, (float) $from->refresh()->balance);
        $this->assertEquals(1_500_000, (float) $to->refresh()->balance);
    }

    public function test_transfer_ke_akun_yang_sama_persis_ditolak(): void
    {
        $account = $this->account('BCA', 1_000_000);

        $this->actingAs($this->user)->post('/transfers', [
            'from_account_id' => $account->getKey(),
            'to_account_id' => $account->getKey(),
            'amount' => 100_000,
            'transfer_date' => '2026-08-10',
        ])->assertSessionHasErrors('to_account_id');

        $this->assertSame(0, Transfer::query()->count());
        $this->assertEquals(1_000_000, (float) $account->refresh()->balance);
    }

    public function test_transfer_tidak_terhitung_sebagai_pemasukan_atau_pengeluaran(): void
    {
        $from = $this->account('BCA', 2_000_000);
        $to = $this->account('GoPay', 0, AccountType::EWallet, '0812');

        $this->actingAs($this->user)->post('/transfers', [
            'from_account_id' => $from->getKey(),
            'to_account_id' => $to->getKey(),
            'amount' => 500_000,
            'transfer_date' => now()->toDateString(),
        ]);

        $summary = app(DashboardService::class)
            ->cashflowSummary($this->user, (int) now()->year, (int) now()->month);

        $this->assertEquals(0, $summary['income'], 'transfer masuk bukan pemasukan');
        $this->assertEquals(0, $summary['expense'], 'transfer keluar bukan pengeluaran');
        // Total saldo gabungan tidak berubah oleh transfer.
        $this->assertEquals(2_000_000, $summary['total_balance']);
    }

    public function test_membatalkan_transfer_mengembalikan_kedua_saldo(): void
    {
        $from = $this->account('BCA', 1_000_000);
        $to = $this->account('GoPay', 0, AccountType::EWallet, '0812');

        $this->actingAs($this->user)->post('/transfers', [
            'from_account_id' => $from->getKey(),
            'to_account_id' => $to->getKey(),
            'amount' => 300_000,
            'transfer_date' => '2026-08-10',
        ]);

        $transfer = Transfer::query()->sole();

        $this->actingAs($this->user)
            ->delete("/transfers/{$transfer->getKey()}")
            ->assertRedirect();

        $this->assertSame(0, Transfer::query()->count());
        $this->assertSame(0, Transaction::query()->count());
        $this->assertEquals(1_000_000, (float) $from->refresh()->balance);
        $this->assertEquals(0, (float) $to->refresh()->balance);
    }

    public function test_hitung_ulang_saldo_memperhitungkan_transfer(): void
    {
        $from = $this->account('BCA', 1_000_000);
        $to = $this->account('GoPay', 0, AccountType::EWallet, '0812');

        $this->actingAs($this->user)->post('/transfers', [
            'from_account_id' => $from->getKey(),
            'to_account_id' => $to->getKey(),
            'amount' => 400_000,
            'transfer_date' => '2026-08-10',
        ]);

        // Rekalkulasi dari histori harus menghasilkan angka yang sama.
        app(LedgerService::class)->recalculate($this->user);

        $this->assertEquals(600_000, (float) $from->refresh()->balance);
        $this->assertEquals(400_000, (float) $to->refresh()->balance);
    }

    public function test_mutasi_transfer_tidak_bisa_dihapus_lewat_modul_transaksi(): void
    {
        $from = $this->account('BCA', 1_000_000);
        $to = $this->account('GoPay', 0, AccountType::EWallet, '0812');

        $this->actingAs($this->user)->post('/transfers', [
            'from_account_id' => $from->getKey(),
            'to_account_id' => $to->getKey(),
            'amount' => 250_000,
            'transfer_date' => '2026-08-10',
        ]);

        $leg = Transaction::query()->where('type', 'transfer_out')->sole();

        $this->actingAs($this->user)
            ->delete("/transactions/{$leg->getKey()}")
            ->assertSessionHas('error');

        $this->assertSame(2, Transaction::query()->count());
        $this->assertEquals(600_000 + 150_000, (float) $from->refresh()->balance);
    }
}
