<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Services\DefaultDataProvisioner;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($this->user);

        $this->account = $this->user->accounts()->where('name', 'Dompet Tunai')->firstOrFail();
        $this->account->forceFill(['opening_balance' => 1_000_000, 'balance' => 1_000_000])->save();

        $this->category = $this->user->categories()->where('name', 'Makanan')->firstOrFail();
    }

    public function test_pengeluaran_memotong_saldo_akun(): void
    {
        $this->actingAs($this->user)
            ->post('/transactions', [
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => 250_000,
                'transaction_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame('750000.00', $this->account->fresh()->balance);
    }

    public function test_pemasukan_menambah_saldo_akun(): void
    {
        app(LedgerService::class)->create($this->user, [
            'account_id' => $this->account->id,
            'category_id' => null,
            'type' => 'income',
            'amount' => 500_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertSame('1500000.00', $this->account->fresh()->balance);
    }

    public function test_mengubah_transaksi_membalik_efek_lama(): void
    {
        $ledger = app(LedgerService::class);

        $transaction = $ledger->create($this->user, [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 250_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $ledger->update($transaction, [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 100_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertSame('900000.00', $this->account->fresh()->balance);
    }

    public function test_menghapus_transaksi_mengembalikan_saldo(): void
    {
        $ledger = app(LedgerService::class);

        $transaction = $ledger->create($this->user, [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 250_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $ledger->delete($transaction);

        $this->assertSame('1000000.00', $this->account->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_user_lain_tidak_bisa_menghapus_transaksi_orang_lain(): void
    {
        $transaction = app(LedgerService::class)->create($this->user, [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 250_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs(User::factory()->create())
            ->delete("/transactions/{$transaction->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_transaksi_menolak_akun_milik_user_lain(): void
    {
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post('/transactions', [
                'account_id' => $this->account->id,
                'type' => 'expense',
                'amount' => 10_000,
                'transaction_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('account_id');

        $this->assertDatabaseCount('transactions', 0);
    }
}
