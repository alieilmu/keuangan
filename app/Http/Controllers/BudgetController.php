<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = DashboardController::resolvePeriod($request->query('period'));

        $summary = $this->budgets->summary($user, $period->year, $period->month);

        return Inertia::render('Budgets/Index', [
            'budgets' => $summary->values(),
            'totals' => [
                'limit' => round((float) $summary->sum('limit_amount'), 2),
                'spent' => round((float) $summary->sum('spent'), 2),
            ],
            'period' => [
                'iso' => $period->format('Y-m'),
                'label' => $period->translatedFormat('F Y'),
                'month' => $period->month,
                'year' => $period->year,
            ],
            'categories' => Category::query()
                ->where('user_id', $user->getKey())
                ->where('type', TransactionType::Expense->value)
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
        ]);
    }

    public function store(BudgetRequest $request): RedirectResponse
    {
        $request->user()->budgets()->create($request->validated());

        return back()->with('success', 'Anggaran berhasil disimpan.');
    }

    public function update(BudgetRequest $request, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $data = $request->validated();
        // Ambang notifikasi di-reset supaya perubahan limit dievaluasi ulang.
        $data['notified_threshold'] = null;

        $budget->update($data);

        return back()->with('success', 'Anggaran berhasil diperbarui.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return back()->with('success', 'Anggaran berhasil dihapus.');
    }

    /**
     * Salin seluruh anggaran periode sebelumnya ke periode yang diminta.
     */
    public function copyPrevious(Request $request): RedirectResponse
    {
        $user = $request->user();
        $period = DashboardController::resolvePeriod($request->input('period'));
        $previous = $period->subMonth();

        $existing = Budget::query()
            ->where('user_id', $user->getKey())
            ->where('period_year', $period->year)
            ->where('period_month', $period->month)
            ->pluck('category_id')
            ->all();

        $rows = Budget::query()
            ->where('user_id', $user->getKey())
            ->where('period_year', $previous->year)
            ->where('period_month', $previous->month)
            ->whereNotIn('category_id', $existing)
            ->get()
            ->map(fn (Budget $budget) => [
                'user_id' => $user->getKey(),
                'category_id' => $budget->category_id,
                'limit_amount' => $budget->limit_amount,
                'period_month' => $period->month,
                'period_year' => $period->year,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows === []) {
            return back()->with('error', 'Tidak ada anggaran bulan sebelumnya yang bisa disalin.');
        }

        Budget::query()->insert($rows);

        return back()->with('success', count($rows).' anggaran disalin dari bulan sebelumnya.');
    }
}
