<?php

namespace App\Http\Controllers;

use App\Enums\BillStatus;
use App\Enums\CreditStatus;
use App\Enums\TransactionType;
use App\Http\Requests\CreditRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Credit;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function __construct(private readonly CreditService $credits) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $credits = Credit::query()
            ->with(['account:id,name', 'category:id,name,color'])
            ->where('user_id', $user->getKey())
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('end_date')
            ->get()
            ->map(fn (Credit $credit) => array_merge(CreditService::present($credit), [
                'can_bill_next_early' => $this->credits->canBillNextEarly($credit),
            ]));

        $active = $credits->where('status', CreditStatus::Active->value);

        return Inertia::render('Credits/Index', [
            'credits' => $credits->values(),
            'summary' => [
                'active_count' => $active->count(),
                'monthly_total' => round((float) $active->sum('monthly_installment'), 2),
                'outstanding_total' => round((float) $active->sum('outstanding'), 2),
            ],
            'accounts' => Account::query()
                ->where('user_id', $user->getKey())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => Category::query()
                ->where('user_id', $user->getKey())
                ->where('type', TransactionType::Expense->value)
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * Halaman detail: progress kredit + histori pembayaran per angsuran.
     */
    public function show(Request $request, Credit $credit): Response
    {
        $this->authorize('view', $credit);

        $credit->loadMissing(['account:id,name', 'category:id,name,color']);
        $user = $request->user();

        return Inertia::render('Credits/Show', [
            'credit' => CreditService::present($credit),
            'schedule' => $this->credits->schedule($credit),
            'can_bill_next_early' => $this->credits->canBillNextEarly($credit),
            'accounts' => Account::query()
                ->where('user_id', $user->getKey())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'balance']),
            'categories' => Category::query()
                ->where('user_id', $user->getKey())
                ->where('type', TransactionType::Expense->value)
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * Tombol "Tagih Angsuran Berikutnya": menarik tagihan bulan depan ke bulan
     * ini supaya bisa langsung dibayar di muka. Hanya boleh saat tidak ada
     * tagihan yang masih menunggu pembayaran.
     */
    public function billNextEarly(Credit $credit): RedirectResponse
    {
        $this->authorize('update', $credit);

        if (! $this->credits->canBillNextEarly($credit)) {
            return back()->with('error', 'Lunasi dulu tagihan angsuran yang masih berjalan.');
        }

        $bill = $this->credits->generateNextBill($credit, ignoreHorizon: true);

        if ($bill === null) {
            return back()->with('error', 'Tidak ada angsuran berikutnya yang bisa ditagih.');
        }

        return back()->with(
            'success',
            'Tagihan '.$bill->title.' dibuat dan siap dibayar sekarang.'
        );
    }

    public function store(CreditRequest $request): RedirectResponse
    {
        $credit = $request->user()->credits()->create(
            CreditService::withDerivedColumns($request->validated())
        );

        // Tagihan angsuran pertama langsung dibuat bila sudah masuk jendela penagihan.
        $bill = $this->credits->generateNextBill($credit);

        return back()->with(
            'success',
            $bill !== null
                ? 'Kredit disimpan. Tagihan angsuran pertama sudah dibuat otomatis.'
                : 'Kredit disimpan. Tagihan angsuran dibuat otomatis menjelang jatuh tempo.'
        );
    }

    public function update(CreditRequest $request, Credit $credit): RedirectResponse
    {
        $this->authorize('update', $credit);

        $credit->update(CreditService::withDerivedColumns($request->validated(), $credit));

        // Tagihan angsuran yang belum dibayar disesuaikan dengan data kredit terbaru.
        $credit->bills()
            ->where('status', BillStatus::Unpaid->value)
            ->update([
                'amount' => $credit->monthly_installment,
                'account_id' => $credit->account_id,
                'category_id' => $credit->category_id,
            ]);

        return back()->with('success', 'Kredit berhasil diperbarui.');
    }

    public function destroy(Credit $credit): RedirectResponse
    {
        $this->authorize('delete', $credit);

        // Tagihan yang sudah dibayar dipertahankan sebagai histori (credit_id
        // otomatis jadi null lewat FK), yang belum dibayar ikut dihapus.
        $credit->bills()->where('status', BillStatus::Unpaid->value)->delete();

        $credit->delete();

        return back()->with('success', 'Kredit dihapus. Tagihan yang sudah lunas tetap tersimpan.');
    }
}
