<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BudgetStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    /**
     * Ringkasan anggaran satu periode.
     *
     * Seluruh agregasi dikerjakan database lewat SATU query:
     * budgets JOIN categories LEFT JOIN (SUM pengeluaran per kategori).
     * Tidak ada N+1 dan tidak ada penjumlahan di sisi PHP.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function summary(User $user, int $year, int $month): Collection
    {
        [$start, $end] = self::periodRange($year, $month);

        $spent = Transaction::query()
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->where('user_id', $user->getKey())
            ->where('type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereNotNull('category_id')
            ->groupBy('category_id');

        return Budget::query()
            ->join('categories', 'categories.id', '=', 'budgets.category_id')
            ->leftJoinSub($spent, 'spent', 'spent.category_id', '=', 'budgets.category_id')
            ->where('budgets.user_id', $user->getKey())
            ->where('budgets.period_year', $year)
            ->where('budgets.period_month', $month)
            // * 1.0 memaksa pembagian float (SQLite melakukan integer division tanpa ini).
            ->orderByDesc(DB::raw('COALESCE(spent.total, 0) * 1.0 / NULLIF(budgets.limit_amount, 0)'))
            ->orderBy('categories.name')
            ->get([
                'budgets.id',
                'budgets.category_id',
                'budgets.limit_amount',
                'budgets.period_month',
                'budgets.period_year',
                'budgets.notified_threshold',
                'categories.name as category_name',
                'categories.color as category_color',
                DB::raw('COALESCE(spent.total, 0) as spent'),
            ])
            ->map(fn (Budget $budget) => self::present($budget));
    }

    /**
     * Ubah satu baris hasil agregasi menjadi payload siap-render.
     *
     * @return array<string, mixed>
     */
    public static function present(Budget $budget): array
    {
        $limit = (float) $budget->limit_amount;
        $spent = (float) $budget->getAttribute('spent');
        $percentage = $limit > 0 ? round($spent / $limit * 100, 1) : 0.0;
        $status = BudgetStatus::fromPercentage($percentage);

        return [
            'id' => $budget->getKey(),
            'category_id' => $budget->category_id,
            'category_name' => $budget->getAttribute('category_name'),
            'category_color' => $budget->getAttribute('category_color'),
            'limit_amount' => $limit,
            'spent' => $spent,
            'remaining' => round($limit - $spent, 2),
            'percentage' => $percentage,
            // Lebar progress bar dibatasi 100 supaya tidak meluber.
            'bar_width' => min(100, (int) round($percentage)),
            'status' => $status,
            'status_label' => BudgetStatus::label($status),
            'period_month' => $budget->period_month,
            'period_year' => $budget->period_year,
        ];
    }

    /**
     * Total terpakai untuk satu budget (dipakai scheduler pengecek ambang batas).
     */
    public function spentFor(Budget $budget): float
    {
        [$start, $end] = self::periodRange($budget->period_year, $budget->period_month);

        return (float) Transaction::query()
            ->where('user_id', $budget->user_id)
            ->where('category_id', $budget->category_id)
            ->where('type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function periodRange(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return [$start->toDateString(), $start->endOfMonth()->toDateString()];
    }
}
