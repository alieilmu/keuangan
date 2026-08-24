<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetAlerter;
use App\Services\BudgetService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Berjalan setiap hari lewat Task Scheduling.
 * Mengirim push notification saat pemakaian anggaran menembus 70% dan 100%.
 *
 * Agregasi seluruh user dikerjakan dalam SATU query (budgets LEFT JOIN sum
 * pengeluaran per user + kategori), bukan looping query per user.
 */
class CheckBudgetThresholds extends Command
{
    protected $signature = 'budgets:check {--date= : Tanggal acuan (Y-m-d), default hari ini}';

    protected $description = 'Cek anggaran yang menembus ambang batas dan kirim notifikasi';

    public function handle(BudgetAlerter $alerter): int
    {
        $reference = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))
            : CarbonImmutable::today();

        [$start, $end] = BudgetService::periodRange($reference->year, $reference->month);

        $spent = Transaction::query()
            ->select('user_id', 'category_id', DB::raw('SUM(amount) as total'))
            ->where('type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereNotNull('category_id')
            ->groupBy('user_id', 'category_id');

        $rows = Budget::query()
            ->join('categories', 'categories.id', '=', 'budgets.category_id')
            ->leftJoinSub($spent, 'spent', function ($join): void {
                $join->on('spent.user_id', '=', 'budgets.user_id')
                    ->on('spent.category_id', '=', 'budgets.category_id');
            })
            ->where('budgets.period_year', $reference->year)
            ->where('budgets.period_month', $reference->month)
            ->where('budgets.limit_amount', '>', 0)
            ->get([
                'budgets.id',
                'budgets.user_id',
                'budgets.limit_amount',
                'budgets.notified_threshold',
                'categories.name as category_name',
                DB::raw('COALESCE(spent.total, 0) as spent'),
            ]);

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->unique())
            ->get()
            ->keyBy('id');

        $sent = 0;

        foreach ($rows as $budget) {
            $user = $users->get($budget->user_id);

            if (! $user instanceof User) {
                continue;
            }

            $notified = $alerter->evaluate(
                $user,
                $budget,
                (float) $budget->getAttribute('spent'),
                (string) $budget->getAttribute('category_name'),
            );

            $sent += $notified ? 1 : 0;
        }

        $this->info("Notifikasi anggaran terkirim: {$sent}");

        return self::SUCCESS;
    }
}
