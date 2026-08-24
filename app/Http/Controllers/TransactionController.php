<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\TransactionRequest;
use App\Jobs\EvaluateBudgetThreshold;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'period' => $request->query('period'),
            'type' => in_array($request->query('type'), TransactionType::values(), true)
                ? $request->query('type')
                : null,
            'category_id' => $request->integer('category_id') ?: null,
            'account_id' => $request->integer('account_id') ?: null,
            'search' => $request->string('search')->trim()->value() ?: null,
        ];

        $period = DashboardController::resolvePeriod($filters['period']);

        $transactions = Transaction::query()
            ->with(['account:id,name', 'category:id,name,color'])
            ->where('user_id', $user->getKey())
            ->inPeriod($period->year, $period->month)
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['category_id'], fn ($query, $id) => $query->where('category_id', $id))
            ->when($filters['account_id'], fn ($query, $id) => $query->where('account_id', $id))
            ->when($filters['search'], fn ($query, $search) => $query->where('description', 'like', '%'.$search.'%'))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Transaction $transaction) => [
                'id' => $transaction->getKey(),
                'type' => $transaction->type->value,
                'amount' => (float) $transaction->amount,
                'transaction_date' => $transaction->transaction_date?->toDateString(),
                'description' => $transaction->description,
                'account_id' => $transaction->account_id,
                'account' => $transaction->account?->name,
                'category_id' => $transaction->category_id,
                'category' => $transaction->category?->name,
                'category_color' => $transaction->category?->color,
            ]);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => array_merge($filters, ['period' => $period->format('Y-m')]),
            'period' => [
                'iso' => $period->format('Y-m'),
                'label' => $period->translatedFormat('F Y'),
            ],
            'accounts' => Account::query()
                ->where('user_id', $user->getKey())
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'balance']),
            'categories' => Category::query()
                ->where('user_id', $user->getKey())
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'color']),
        ]);
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $transaction = $this->ledger->create($request->user(), $request->validated());

        $this->evaluateBudget($transaction);

        return back()->with('success', 'Transaksi berhasil dicatat.');
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $previousCategory = $transaction->category_id;

        $this->ledger->update($transaction, $request->validated());

        $this->evaluateBudget($transaction, $previousCategory);

        return back()->with('success', 'Transaksi berhasil diperbarui.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $this->ledger->delete($transaction);

        return back()->with('success', 'Transaksi berhasil dihapus.');
    }

    /**
     * Cek ambang batas anggaran segera setelah pengeluaran berubah.
     */
    private function evaluateBudget(Transaction $transaction, ?int $previousCategoryId = null): void
    {
        $date = $transaction->transaction_date?->toDateString() ?? now()->toDateString();

        $categoryIds = array_filter(array_unique([$transaction->category_id, $previousCategoryId]));

        foreach ($categoryIds as $categoryId) {
            EvaluateBudgetThreshold::dispatch((int) $transaction->user_id, (int) $categoryId, $date);
        }
    }
}
