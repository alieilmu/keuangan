<?php

namespace App\Http\Controllers;

use App\Enums\BillStatus;
use App\Enums\DocumentKind;
use App\Enums\TransactionType;
use App\Http\Requests\BillRequest;
use App\Jobs\EvaluateBudgetThreshold;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Services\CreditService;
use App\Services\DocumentService;
use App\Services\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly CreditService $credits,
        private readonly DocumentService $documents,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $status = in_array($request->query('status'), BillStatus::values(), true)
            ? $request->query('status')
            : null;

        $today = CarbonImmutable::today();

        $bills = Bill::query()
            ->with(['account:id,name', 'category:id,name,color', 'credit:id,name,tenor_months', 'documents'])
            ->where('user_id', $user->getKey())
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderByRaw("CASE WHEN status = 'unpaid' THEN 0 ELSE 1 END")
            ->orderBy('due_date')
            ->get()
            ->map(function (Bill $bill) use ($today) {
                $due = CarbonImmutable::parse($bill->due_date);

                return [
                    'id' => $bill->getKey(),
                    'title' => $bill->title,
                    'amount' => (float) $bill->amount,
                    'due_date' => $due->toDateString(),
                    'due_label' => $due->translatedFormat('d M Y'),
                    'days_left' => (int) $today->diffInDays($due, false),
                    'status' => $bill->status->value,
                    'status_label' => $bill->status->label(),
                    'paid_at' => $bill->paid_at?->toIso8601String(),
                    'notes' => $bill->notes,
                    'remind_days_before' => $bill->remind_days_before,
                    'account_id' => $bill->account_id,
                    'account' => $bill->account?->name,
                    'category_id' => $bill->category_id,
                    'category' => $bill->category?->name,
                    'category_color' => $bill->category?->color,
                    'credit_id' => $bill->credit_id,
                    'credit' => $bill->credit?->name,
                    'installment_label' => $bill->credit
                        ? 'Cicilan '.$bill->installment_number.'/'.$bill->credit->tenor_months
                        : null,
                    'invoice_document' => DocumentService::present($bill->invoiceDocument()),
                    'receipt_document' => DocumentService::present($bill->receiptDocument()),
                ];
            });

        return Inertia::render('Bills/Index', [
            'bills' => $bills->values(),
            'filters' => ['status' => $status],
            'summary' => [
                'unpaid_total' => round((float) $bills->where('status', 'unpaid')->sum('amount'), 2),
                'unpaid_count' => $bills->where('status', 'unpaid')->count(),
                'overdue_count' => $bills->where('status', 'unpaid')->where('days_left', '<', 0)->count(),
            ],
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

    public function store(BillRequest $request): RedirectResponse
    {
        $bill = $request->user()->bills()->create($request->safe()->except('document'));

        $this->documents->attach(
            $request->user(),
            $bill,
            $request->file('document'),
            DocumentKind::Invoice
        );

        return back()->with('success', 'Tagihan berhasil ditambahkan.');
    }

    public function update(BillRequest $request, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $bill);

        if ($bill->status === BillStatus::Paid) {
            return back()->with('error', 'Tagihan lunas tidak bisa diubah. Batalkan pembayarannya dulu.');
        }

        $bill->update(array_merge($request->safe()->except('document'), ['reminded_on' => null]));

        if ($request->hasFile('document')) {
            $this->documents->attach(
                $request->user(),
                $bill,
                $request->file('document'),
                DocumentKind::Invoice
            );
        }

        return back()->with('success', 'Tagihan berhasil diperbarui.');
    }

    public function destroy(Bill $bill): RedirectResponse
    {
        $this->authorize('delete', $bill);

        if ($bill->transaction_id !== null) {
            return back()->with('error', 'Batalkan pembayaran terlebih dahulu sebelum menghapus tagihan.');
        }

        $this->documents->detach($bill);

        $bill->delete();

        return back()->with('success', 'Tagihan berhasil dihapus.');
    }

    /**
     * "Bayar Sekarang": membuat transaksi pengeluaran, memotong saldo akun,
     * lalu menandai tagihan lunas. Semuanya dalam satu DB transaction.
     */
    public function pay(Request $request, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $bill);

        if ($bill->status === BillStatus::Paid) {
            return back()->with('error', 'Tagihan ini sudah lunas.');
        }

        $userId = $request->user()->getKey();

        $validated = $request->validate([
            'account_id' => [
                'required', 'integer',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $userId)
                    ->where('type', TransactionType::Expense->value),
            ],
            'paid_on' => ['nullable', 'date'],
            // Nota pembayaran opsional; kalau diunggah langsung menempel ke tagihan.
            'receipt' => DocumentService::rules(required: false),
        ], DocumentService::messages('receipt'));

        $paidOn = isset($validated['paid_on'])
            ? CarbonImmutable::parse($validated['paid_on'])
            : CarbonImmutable::today();

        $categoryId = $validated['category_id'] ?? $bill->category_id;

        DB::transaction(function () use ($bill, $request, $validated, $paidOn, $categoryId): void {
            $transaction = $this->ledger->create($request->user(), [
                'account_id' => $validated['account_id'],
                'category_id' => $categoryId,
                'type' => TransactionType::Expense->value,
                'amount' => $bill->amount,
                'transaction_date' => $paidOn->toDateString(),
                'description' => 'Pembayaran tagihan: '.$bill->title,
            ]);

            $bill->forceFill([
                'status' => BillStatus::Paid->value,
                'paid_at' => now(),
                'account_id' => $validated['account_id'],
                'category_id' => $categoryId,
                'transaction_id' => $transaction->getKey(),
            ])->save();

            // Tagihan cicilan: sisa tenor kredit langsung berkurang satu bulan.
            $bill->loadMissing('credit');

            if ($bill->credit) {
                $this->credits->registerPayment($bill->credit);
            }
        });

        if ($request->hasFile('receipt')) {
            $this->documents->attach(
                $request->user(),
                $bill,
                $request->file('receipt'),
                DocumentKind::Receipt
            );
        }

        // Siapkan tagihan angsuran berikutnya bila sudah masuk jendela penagihan.
        if ($bill->credit) {
            $this->credits->generateNextBill($bill->credit->refresh());
        }

        if ($categoryId) {
            EvaluateBudgetThreshold::dispatch((int) $userId, (int) $categoryId, $paidOn->toDateString());
        }

        return back()->with('success', 'Tagihan '.$bill->title.' berhasil dibayar.');
    }

    /**
     * Batalkan pembayaran: hapus transaksi terkait dan kembalikan saldo akun.
     */
    public function unpay(Bill $bill): RedirectResponse
    {
        $this->authorize('update', $bill);

        if ($bill->status !== BillStatus::Paid) {
            return back()->with('error', 'Tagihan ini belum dibayar.');
        }

        DB::transaction(function () use ($bill): void {
            $bill->loadMissing('transaction');

            if ($bill->transaction) {
                $this->ledger->delete($bill->transaction);
            }

            $bill->forceFill([
                'status' => BillStatus::Unpaid->value,
                'paid_at' => null,
                'transaction_id' => null,
                'reminded_on' => null,
            ])->save();

            // Nota pembayaran ikut dilepas; berkas tagihannya tetap disimpan.
            $this->documents->detach($bill, DocumentKind::Receipt);

            $bill->loadMissing('credit');

            if ($bill->credit) {
                $this->credits->revertPayment($bill->credit);

                // Tagihan angsuran berikutnya yang sudah terlanjur dibuat dan
                // belum dibayar ikut ditarik kembali agar urutan tenor konsisten.
                $bill->credit->bills()
                    ->where('status', BillStatus::Unpaid->value)
                    ->where('installment_number', '>', $bill->installment_number)
                    ->delete();
            }
        });

        return back()->with('success', 'Pembayaran tagihan dibatalkan.');
    }
}
