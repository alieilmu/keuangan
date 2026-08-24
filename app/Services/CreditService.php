<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\CreditStatus;
use App\Models\Bill;
use App\Models\Credit;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan main modul Kredit & Cicilan.
 *
 * Sumber kebenaran sisa tenor adalah kolom `remaining_months`. Kolom itu hanya
 * berubah lewat registerPayment() / revertPayment(), yang keduanya dipanggil dari
 * dalam DB transaction milik pembayaran tagihan sehingga selalu sinkron dengan
 * status tagihannya.
 */
class CreditService
{
    /** Tagihan angsuran dibuat maksimal sekian hari sebelum jatuh tempo. */
    public const GENERATION_HORIZON_DAYS = 31;

    /**
     * Jumlah angsuran antara dua tanggal (inklusif), minimal 1.
     */
    public static function tenorBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $months = ($end->year - $start->year) * 12 + ($end->month - $start->month) + 1;

        return max(1, $months);
    }

    /**
     * Lengkapi payload form dengan kolom turunan (tenor, tanggal jatuh tempo bulanan).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withDerivedColumns(array $data, ?Credit $existing = null): array
    {
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);

        $tenor = self::tenorBetween($start, $end);

        $data['tenor_months'] = $tenor;
        $data['due_day'] = (int) ($data['due_day'] ?? $start->day);

        // Saat kredit diubah, sisa tenor yang sudah berjalan dipertahankan
        // tetapi tetap dijepit agar tidak melebihi tenor baru.
        $remaining = $data['remaining_months'] ?? $existing?->remaining_months ?? $tenor;
        $data['remaining_months'] = max(0, min($tenor, (int) $remaining));

        $data['status'] = $data['remaining_months'] === 0
            ? CreditStatus::PaidOff->value
            : ($existing?->status === CreditStatus::Closed ? CreditStatus::Closed->value : CreditStatus::Active->value);

        return $data;
    }

    /**
     * Buat tagihan untuk angsuran berikutnya bila sudah masuk jendela penagihan.
     *
     * Idempoten: unique index (credit_id, installment_number) mencegah duplikat
     * walaupun perintah dijalankan berkali-kali dalam sehari.
     */
    public function generateNextBill(
        Credit $credit,
        ?CarbonImmutable $today = null,
        bool $ignoreHorizon = false,
    ): ?Bill {
        $today ??= CarbonImmutable::today();

        if ($credit->status !== CreditStatus::Active || $credit->remaining_months < 1) {
            return null;
        }

        $installment = $credit->currentInstallment();
        $dueDate = $credit->dueDateFor($installment);

        // Belum waktunya ditagih. Tombol "tagih lebih awal" melewati batas ini.
        if (! $ignoreHorizon && $dueDate->greaterThan($today->addDays(self::GENERATION_HORIZON_DAYS))) {
            return null;
        }

        if ($credit->bills()->where('installment_number', $installment)->exists()) {
            return null;
        }

        try {
            return $credit->bills()->create([
                'user_id' => $credit->user_id,
                'account_id' => $credit->account_id,
                'category_id' => $credit->category_id,
                'installment_number' => $installment,
                'title' => $credit->name.' - Cicilan '.$installment.'/'.$credit->tenor_months,
                'amount' => $credit->monthly_installment,
                'due_date' => $dueDate->toDateString(),
                'status' => BillStatus::Unpaid->value,
                'remind_days_before' => 3,
            ]);
        } catch (QueryException) {
            // Balapan dengan proses lain: tagihannya sudah dibuat, aman diabaikan.
            return null;
        }
    }

    /**
     * Jalankan generator untuk seluruh kredit aktif (dipakai scheduler harian).
     *
     * @return int jumlah tagihan yang dibuat
     */
    public function generateForAll(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $created = 0;

        Credit::query()
            ->where('status', CreditStatus::Active->value)
            ->where('remaining_months', '>', 0)
            ->chunkById(200, function ($credits) use ($today, &$created): void {
                foreach ($credits as $credit) {
                    $created += $this->generateNextBill($credit, $today) !== null ? 1 : 0;
                }
            });

        return $created;
    }

    /**
     * Angsuran dibayar: sisa tenor berkurang satu, dan kredit ditandai lunas
     * begitu sisa tenor habis. Baris dikunci agar aman dari request paralel.
     */
    public function registerPayment(Credit $credit): void
    {
        DB::transaction(function () use ($credit): void {
            /** @var Credit $locked */
            $locked = Credit::query()->lockForUpdate()->findOrFail($credit->getKey());

            $locked->remaining_months = max(0, $locked->remaining_months - 1);

            if ($locked->remaining_months === 0 && $locked->status === CreditStatus::Active) {
                $locked->status = CreditStatus::PaidOff;
            }

            $locked->save();

            $credit->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Kebalikan registerPayment(), dipakai saat pembayaran tagihan dibatalkan.
     */
    public function revertPayment(Credit $credit): void
    {
        DB::transaction(function () use ($credit): void {
            /** @var Credit $locked */
            $locked = Credit::query()->lockForUpdate()->findOrFail($credit->getKey());

            $locked->remaining_months = min($locked->tenor_months, $locked->remaining_months + 1);

            if ($locked->remaining_months > 0 && $locked->status === CreditStatus::PaidOff) {
                $locked->status = CreditStatus::Active;
            }

            $locked->save();

            $credit->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Payload siap-render untuk halaman kredit maupun widget dashboard.
     *
     * @return array<string, mixed>
     */
    public static function present(Credit $credit): array
    {
        $paid = $credit->paidMonths();

        return [
            'id' => $credit->getKey(),
            'name' => $credit->name,
            'total_amount' => (float) $credit->total_amount,
            'interest_rate' => $credit->interest_rate !== null ? (float) $credit->interest_rate : null,
            'monthly_installment' => (float) $credit->monthly_installment,
            'start_date' => $credit->start_date?->toDateString(),
            'end_date' => $credit->end_date?->toDateString(),
            'end_label' => $credit->end_date?->translatedFormat('M Y'),
            'due_day' => $credit->due_day,
            'tenor_months' => $credit->tenor_months,
            'remaining_months' => $credit->remaining_months,
            'paid_months' => $paid,
            'current_installment' => $credit->currentInstallment(),
            // "Bulan ke-12 dari 36"
            'progress_label' => $credit->remaining_months === 0
                ? 'Lunas '.$credit->tenor_months.' dari '.$credit->tenor_months.' bulan'
                : 'Bulan ke-'.$credit->currentInstallment().' dari '.$credit->tenor_months,
            'progress_percentage' => $credit->progressPercentage(),
            'outstanding' => $credit->outstanding(),
            'status' => $credit->status->value,
            'status_label' => $credit->status->label(),
            'account_id' => $credit->account_id,
            'account' => $credit->account?->name,
            'category_id' => $credit->category_id,
            'category' => $credit->category?->name,
            'notes' => $credit->notes,
            'next_due_date' => $credit->remaining_months > 0
                ? $credit->dueDateFor($credit->currentInstallment())->toDateString()
                : null,
        ];
    }

    /**
     * Jadwal angsuran lengkap: yang sudah punya tagihan memakai data aslinya,
     * sisanya ditampilkan sebagai rencana. Ini yang mengisi histori pembayaran.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function schedule(Credit $credit): Collection
    {
        $bills = $credit->bills()
            ->with(['account:id,name', 'transaction:id,transaction_date,amount', 'documents'])
            ->get()
            ->keyBy('installment_number');

        return Collection::range(1, max(1, $credit->tenor_months))
            ->map(function (int $number) use ($credit, $bills): array {
                /** @var Bill|null $bill */
                $bill = $bills->get($number);

                if (! $bill instanceof Bill) {
                    $due = $credit->dueDateFor($number);

                    // Kredit yang sudah berjalan saat dicatat: angsuran lampau
                    // ikut terhitung lunas walau tagihannya tidak pernah dibuat
                    // di aplikasi ini.
                    $settledBefore = $number <= $credit->paidMonths();

                    return [
                        'installment_number' => $number,
                        'state' => $settledBefore ? 'prior' : 'planned',
                        'state_label' => $settledBefore ? 'Lunas sebelum dicatat' : 'Belum ditagih',
                        'amount' => (float) $credit->monthly_installment,
                        'due_date' => $due->toDateString(),
                        'due_label' => $due->translatedFormat('d M Y'),
                        'bill_id' => null,
                        'paid_at' => null,
                        'paid_label' => null,
                        'account' => null,
                        'invoice_document' => null,
                        'receipt_document' => null,
                    ];
                }

                $paid = $bill->status === BillStatus::Paid;

                return [
                    'installment_number' => $number,
                    'state' => $paid ? 'paid' : 'billed',
                    'state_label' => $paid ? 'Lunas' : 'Menunggu pembayaran',
                    'amount' => (float) $bill->amount,
                    'due_date' => $bill->due_date->toDateString(),
                    'due_label' => $bill->due_date->translatedFormat('d M Y'),
                    'bill_id' => $bill->getKey(),
                    'paid_at' => $bill->paid_at?->toIso8601String(),
                    'paid_label' => $bill->paid_at?->translatedFormat('d M Y'),
                    'account' => $bill->account?->name,
                    'invoice_document' => DocumentService::present($bill->invoiceDocument()),
                    'receipt_document' => DocumentService::present($bill->receiptDocument()),
                ];
            })
            ->values();
    }

    /**
     * Boleh menagih angsuran berikutnya lebih awal hanya bila tidak ada
     * tagihan yang masih menunggu pembayaran dan tenornya belum habis.
     */
    public function canBillNextEarly(Credit $credit): bool
    {
        if ($credit->status !== CreditStatus::Active || $credit->remaining_months < 1) {
            return false;
        }

        $hasUnpaid = $credit->bills()->where('status', BillStatus::Unpaid->value)->exists();

        if ($hasUnpaid) {
            return false;
        }

        return ! $credit->bills()
            ->where('installment_number', $credit->currentInstallment())
            ->exists();
    }
}
