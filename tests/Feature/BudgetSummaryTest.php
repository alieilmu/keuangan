<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\DefaultDataProvisioner;
use App\Support\BudgetStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BudgetSummaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: int, 1: string, 2: float}>
     */
    public static function thresholds(): array
    {
        return [
            'aman' => [400_000, BudgetStatus::SAFE, 40.0],
            'batas hijau' => [500_000, BudgetStatus::SAFE, 50.0],
            'peringatan' => [650_000, BudgetStatus::WARNING, 65.0],
            'batas kuning' => [700_000, BudgetStatus::WARNING, 70.0],
            'bahaya' => [900_000, BudgetStatus::DANGER, 90.0],
            'tepat limit' => [1_000_000, BudgetStatus::DANGER, 100.0],
            'overbudget' => [1_200_000, BudgetStatus::OVER, 120.0],
        ];
    }

    #[DataProvider('thresholds')]
    public function test_status_warna_mengikuti_persentase_pemakaian(int $spent, string $status, float $percentage): void
    {
        $user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($user);

        $category = $user->categories()->where('name', 'Makanan')->firstOrFail();
        $account = $user->accounts()->firstOrFail();

        $user->budgets()->create([
            'category_id' => $category->id,
            'limit_amount' => 1_000_000,
            'period_month' => now()->month,
            'period_year' => now()->year,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => $spent,
            'transaction_date' => now()->toDateString(),
        ]);

        $summary = app(BudgetService::class)->summary($user, now()->year, now()->month);

        $this->assertCount(1, $summary);
        $this->assertSame($percentage, $summary[0]['percentage']);
        $this->assertSame($status, $summary[0]['status']);
        $this->assertLessThanOrEqual(100, $summary[0]['bar_width']);
    }

    public function test_pengeluaran_di_luar_periode_tidak_ikut_dihitung(): void
    {
        $user = User::factory()->create();
        app(DefaultDataProvisioner::class)->provision($user);

        $category = $user->categories()->where('name', 'Makanan')->firstOrFail();
        $account = $user->accounts()->firstOrFail();

        $user->budgets()->create([
            'category_id' => $category->id,
            'limit_amount' => 1_000_000,
            'period_month' => now()->month,
            'period_year' => now()->year,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 900_000,
            'transaction_date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
        ]);

        $summary = app(BudgetService::class)->summary($user, now()->year, now()->month);

        $this->assertSame(0.0, $summary[0]['spent']);
        $this->assertSame(BudgetStatus::SAFE, $summary[0]['status']);
    }
}
