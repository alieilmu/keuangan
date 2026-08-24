<?php

namespace App\Jobs;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Services\BudgetAlerter;
use App\Services\BudgetService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Evaluasi realtime setelah sebuah transaksi pengeluaran dibuat/diubah,
 * supaya user langsung ditegur begitu anggaran menembus 70%.
 */
class EvaluateBudgetThreshold implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $userId,
        public readonly int $categoryId,
        public readonly string $date,
    ) {}

    public function handle(BudgetAlerter $alerter, BudgetService $budgets): void
    {
        $date = CarbonImmutable::parse($this->date);

        $budget = Budget::query()
            ->where('user_id', $this->userId)
            ->where('category_id', $this->categoryId)
            ->where('period_year', $date->year)
            ->where('period_month', $date->month)
            ->first();

        $user = User::query()->find($this->userId);

        if (! $budget instanceof Budget || ! $user instanceof User) {
            return;
        }

        $category = Category::query()->find($this->categoryId);

        $alerter->evaluate(
            $user,
            $budget,
            $budgets->spentFor($budget),
            $category?->name ?? 'Kategori',
        );
    }
}
