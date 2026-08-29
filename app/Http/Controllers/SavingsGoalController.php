<?php

namespace App\Http\Controllers;

use App\Enums\BillStatus;
use App\Enums\SavingsGoalStatus;
use App\Http\Requests\SavingsGoalRequest;
use App\Models\Account;
use App\Models\SavingsGoal;
use App\Services\SavingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavingsGoalController extends Controller
{
    public function __construct(private readonly SavingsService $savings) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $goals = SavingsGoal::query()
            ->with(['sourceAccount:id,name,account_number', 'storageAccount:id,name,account_number'])
            ->where('user_id', $user->getKey())
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->map(fn (SavingsGoal $goal) => SavingsService::present($goal));

        $active = $goals->where('status', SavingsGoalStatus::Active->value);

        return Inertia::render('Savings/Index', [
            'goals' => $goals->values(),
            'summary' => [
                'active_count' => $active->count(),
                'monthly_total' => round((float) $active->sum('monthly_contribution'), 2),
                'saved_total' => round((float) $goals->sum('saved_amount'), 2),
                'target_total' => round((float) $active->sum('target_amount'), 2),
            ],
            'accounts' => Account::query()
                ->where('user_id', $user->getKey())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'account_number', 'balance']),
        ]);
    }

    public function show(Request $request, SavingsGoal $goal): Response
    {
        $this->authorize('view', $goal);

        return Inertia::render('Savings/Show', [
            'goal' => SavingsService::present($goal),
            'history' => $this->savings->history($goal),
            'can_bill_next_early' => $this->canBillNextEarly($goal),
            'accounts' => Account::query()
                ->where('user_id', $request->user()->getKey())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'account_number', 'balance']),
        ]);
    }

    public function store(SavingsGoalRequest $request): RedirectResponse
    {
        $goal = $request->user()->savingsGoals()->create(
            SavingsService::withDerivedColumns($request->validated())
        );

        $bill = $this->savings->generateNextBill($goal);

        return back()->with(
            'success',
            $bill !== null
                ? 'Target tabungan disimpan. Tagihan setoran pertama sudah dibuat.'
                : 'Target tabungan disimpan. Tagihan setoran dibuat otomatis menjelang jatuh tempo.'
        );
    }

    public function update(SavingsGoalRequest $request, SavingsGoal $goal): RedirectResponse
    {
        $this->authorize('update', $goal);

        $goal->update(SavingsService::withDerivedColumns($request->validated(), $goal));

        // Tagihan setoran yang belum dibayar mengikuti nilai terbaru.
        $goal->bills()
            ->where('status', BillStatus::Unpaid->value)
            ->update([
                'amount' => $goal->monthly_contribution,
                'account_id' => $goal->source_account_id,
            ]);

        return back()->with('success', 'Target tabungan berhasil diperbarui.');
    }

    public function destroy(SavingsGoal $goal): RedirectResponse
    {
        $this->authorize('delete', $goal);

        // Setoran yang sudah masuk tetap tersimpan sebagai riwayat transfer;
        // hanya tagihan yang belum dibayar yang ikut dihapus.
        $goal->bills()->where('status', BillStatus::Unpaid->value)->delete();

        $goal->delete();

        return back()->with('success', 'Target tabungan dihapus. Riwayat setoran tetap tersimpan.');
    }

    /**
     * Tombol setor lebih awal: hanya saat tidak ada setoran yang menunggu.
     */
    public function billNextEarly(SavingsGoal $goal): RedirectResponse
    {
        $this->authorize('update', $goal);

        if (! $this->canBillNextEarly($goal)) {
            return back()->with('error', 'Selesaikan dulu setoran yang masih menunggu pembayaran.');
        }

        $bill = $this->savings->generateNextBill($goal, ignoreHorizon: true);

        return $bill !== null
            ? back()->with('success', 'Tagihan setoran berikutnya dibuat dan siap dibayar.')
            : back()->with('error', 'Tidak ada setoran berikutnya yang perlu ditagih.');
    }

    private function canBillNextEarly(SavingsGoal $goal): bool
    {
        return $goal->status === SavingsGoalStatus::Active
            && $goal->savedAmount() < (float) $goal->target_amount
            && ! $goal->bills()->where('status', BillStatus::Unpaid->value)->exists();
    }
}
