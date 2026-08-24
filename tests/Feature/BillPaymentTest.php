<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\User;
use App\Notifications\BillDueReminder;
use App\Services\DefaultDataProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($this->user);
    }

    private function makeBill(array $attributes = []): Bill
    {
        return $this->user->bills()->create(array_merge([
            'title' => 'Internet Rumah',
            'amount' => 350_000,
            'due_date' => now()->addDays(2)->toDateString(),
            'status' => 'unpaid',
            'remind_days_before' => 3,
        ], $attributes));
    }

    public function test_membayar_tagihan_membuat_transaksi_dan_memotong_saldo(): void
    {
        $account = $this->user->accounts()->firstOrFail();
        $account->forceFill(['opening_balance' => 1_000_000, 'balance' => 1_000_000])->save();

        $category = $this->user->categories()->where('name', 'Tagihan')->firstOrFail();
        $bill = $this->makeBill();

        $this->actingAs($this->user)
            ->post("/bills/{$bill->id}/pay", [
                'account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertRedirect();

        $bill->refresh();

        $this->assertSame('paid', $bill->status->value);
        $this->assertNotNull($bill->transaction_id);
        $this->assertSame('650000.00', $account->fresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'id' => $bill->transaction_id,
            'type' => 'expense',
            'amount' => 350_000,
        ]);
    }

    public function test_membatalkan_pembayaran_mengembalikan_saldo_dan_menghapus_transaksi(): void
    {
        $account = $this->user->accounts()->firstOrFail();
        $account->forceFill(['opening_balance' => 1_000_000, 'balance' => 1_000_000])->save();

        $bill = $this->makeBill();

        $this->actingAs($this->user)->post("/bills/{$bill->id}/pay", ['account_id' => $account->id]);
        $this->actingAs($this->user)->post("/bills/{$bill->id}/unpay")->assertRedirect();

        $bill->refresh();

        $this->assertSame('unpaid', $bill->status->value);
        $this->assertNull($bill->transaction_id);
        $this->assertSame('1000000.00', $account->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tagihan_lunas_tidak_bisa_dibayar_dua_kali(): void
    {
        $account = $this->user->accounts()->firstOrFail();
        $account->forceFill(['opening_balance' => 1_000_000, 'balance' => 1_000_000])->save();

        $bill = $this->makeBill();

        $this->actingAs($this->user)->post("/bills/{$bill->id}/pay", ['account_id' => $account->id]);
        $this->actingAs($this->user)->post("/bills/{$bill->id}/pay", ['account_id' => $account->id]);

        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame('650000.00', $account->fresh()->balance);
    }

    public function test_scheduler_mengirim_pengingat_sekali_sehari(): void
    {
        Notification::fake();

        $bill = $this->makeBill(['due_date' => now()->addDay()->toDateString()]);
        $this->makeBill(['title' => 'Masih Jauh', 'due_date' => now()->addDays(20)->toDateString()]);

        $this->artisan('bills:remind')->assertSuccessful();
        $this->artisan('bills:remind')->assertSuccessful(); // idempoten

        Notification::assertSentToTimes($this->user, BillDueReminder::class, 1);
        $this->assertSame(now()->toDateString(), $bill->fresh()->reminded_on->toDateString());
    }
}
