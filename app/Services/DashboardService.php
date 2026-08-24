<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\CreditStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Credit;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(private readonly BudgetService $budgets) {}

    /**
     * Seluruh data halaman dashboard.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user, CarbonImmutable $period): array
    {
        $year = $period->year;
        $month = $period->month;

        return [
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => $period->translatedFormat('F Y'),
                'iso' => $period->format('Y-m'),
            ],
            'summary' => $this->cashflowSummary($user, $year, $month),
            'expense_breakdown' => $this->expenseByCategory($user, $year, $month),
            'upcoming_bills' => $this->upcomingBills($user),
            'budgets' => $this->budgets->summary($user, $year, $month)->values(),
            'credits' => $this->activeCredits($user),
            'recent_transactions' => $this->recentTransactions($user),
        ];
    }

    /**
     * 3 hero card: total saldo gabungan, pemasukan & pengeluaran bulan berjalan.
     * Pemasukan + pengeluaran diambil dalam satu query dengan conditional SUM.
     *
     * @return array<string, float>
     */
    public function cashflowSummary(User $user, int $year, int $month): array
    {
        [$start, $end] = BudgetService::periodRange($year, $month);

        $flow = Transaction::query()
            ->where('user_id', $user->getKey())
            ->whereBetween('transaction_date', [$start, $end])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) as income")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) as expense")
            ->first();

        $income = (float) ($flow?->getAttribute('income') ?? 0);
        $expense = (float) ($flow?->getAttribute('expense') ?? 0);

        $totalBalance = (float) Account::query()
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->sum('balance');

        return [
            'total_balance' => round($totalBalance, 2),
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income - $expense, 2),
        ];
    }

    /**
     * Alokasi pengeluaran per kategori untuk pie chart.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function expenseByCategory(User $user, int $year, int $month): Collection
    {
        [$start, $end] = BudgetService::periodRange($year, $month);

        $rows = Transaction::query()
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.user_id', $user->getKey())
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereBetween('transactions.transaction_date', [$start, $end])
            ->groupBy('transactions.category_id', 'categories.name', 'categories.color')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->get([
                'transactions.category_id',
                DB::raw("COALESCE(categories.name, 'Tanpa Kategori') as name"),
                DB::raw("COALESCE(categories.color, '#94a3b8') as color"),
                DB::raw('SUM(transactions.amount) as total'),
            ]);

        $grandTotal = (float) $rows->sum(fn ($row) => (float) $row->getAttribute('total'));

        return $rows->map(fn ($row) => [
            'category_id' => $row->getAttribute('category_id'),
            'name' => $row->getAttribute('name'),
            'color' => $row->getAttribute('color'),
            'total' => round((float) $row->getAttribute('total'), 2),
            'percentage' => $grandTotal > 0
                ? round((float) $row->getAttribute('total') / $grandTotal * 100, 1)
                : 0.0,
        ])->values();
    }

    /**
     * Tagihan untuk widget carousel: yang belum dibayar, jatuh tempo terdekat dulu.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function upcomingBills(User $user, int $limit = 10): Collection
    {
        $today = CarbonImmutable::today();

        return Bill::query()
            ->with(['account:id,name', 'category:id,name,color'])
            ->where('user_id', $user->getKey())
            ->where('status', BillStatus::Unpaid->value)
            ->orderBy('due_date')
            ->limit($limit)
            ->get()
            ->map(function (Bill $bill) use ($today) {
                $due = CarbonImmutable::parse($bill->due_date);
                $daysLeft = (int) $today->diffInDays($due, false);

                return [
                    'id' => $bill->getKey(),
                    'title' => $bill->title,
                    'amount' => (float) $bill->amount,
                    'due_date' => $due->toDateString(),
                    'due_label' => $due->translatedFormat('d M Y'),
                    'days_left' => $daysLeft,
                    'is_overdue' => $daysLeft < 0,
                    'is_due_soon' => $daysLeft >= 0 && $daysLeft <= $bill->remind_days_before,
                    'account' => $bill->account?->name,
                    'account_id' => $bill->account_id,
                    'category' => $bill->category?->name,
                    'category_color' => $bill->category?->color,
                ];
            })
            ->values();
    }

    /**
     * Kredit yang masih berjalan, untuk widget progress cicilan di dashboard.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function activeCredits(User $user, int $limit = 6): Collection
    {
        return Credit::query()
            ->where('user_id', $user->getKey())
            ->where('status', CreditStatus::Active->value)
            ->orderBy('end_date')
            ->limit($limit)
            ->get()
            ->map(fn (Credit $credit) => CreditService::present($credit))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function recentTransactions(User $user, int $limit = 6): Collection
    {
        return Transaction::query()
            ->with(['account:id,name', 'category:id,name,color'])
            ->where('user_id', $user->getKey())
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->getKey(),
                'type' => $transaction->type->value,
                'amount' => (float) $transaction->amount,
                'description' => $transaction->description,
                'date' => $transaction->transaction_date?->toDateString(),
                'date_label' => $transaction->transaction_date?->translatedFormat('d M'),
                'account' => $transaction->account?->name,
                'category' => $transaction->category?->name,
                'category_color' => $transaction->category?->color,
            ])
            ->values();
    }
}
