<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BudgetThresholdReached;
use App\Services\DefaultDataProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BudgetNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifikasi_dikirim_saat_anggaran_menembus_70_persen_lalu_100_persen(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($user);

        $account = $user->accounts()->firstOrFail();
        $category = $user->categories()->where('name', 'Makanan')->firstOrFail();

        $budget = $user->budgets()->create([
            'category_id' => $category->id,
            'limit_amount' => 1_000_000,
            'period_month' => now()->month,
            'period_year' => now()->year,
        ]);

        $spend = fn (int $amount) => $this->actingAs($user)->post('/transactions', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => $amount,
            'transaction_date' => now()->toDateString(),
        ]);

        // 60% - masih aman, belum ada notifikasi.
        $spend(600_000);
        Notification::assertNothingSent();

        // 75% - menembus ambang 70%.
        $spend(150_000);
        Notification::assertSentToTimes($user, BudgetThresholdReached::class, 1);
        $this->assertSame(70, $budget->fresh()->notified_threshold);

        // 85% - masih di ambang yang sama, tidak dikirim ulang.
        $spend(100_000);
        Notification::assertSentToTimes($user, BudgetThresholdReached::class, 1);

        // 105% - menembus ambang 100%.
        $spend(200_000);
        Notification::assertSentToTimes($user, BudgetThresholdReached::class, 2);
        $this->assertSame(100, $budget->fresh()->notified_threshold);
    }

    public function test_scheduler_harian_tidak_mengirim_ulang_notifikasi_yang_sama(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($user);

        $account = $user->accounts()->firstOrFail();
        $category = $user->categories()->where('name', 'Makanan')->firstOrFail();

        $user->budgets()->create([
            'category_id' => $category->id,
            'limit_amount' => 1_000_000,
            'period_month' => now()->month,
            'period_year' => now()->year,
        ]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 800_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->artisan('budgets:check')->assertSuccessful();
        $this->artisan('budgets:check')->assertSuccessful();

        Notification::assertSentToTimes($user, BudgetThresholdReached::class, 1);
    }
}
