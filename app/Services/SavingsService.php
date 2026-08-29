<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\SavingsGoalStatus;
use App\Models\Bill;
use App\Models\SavingsGoal;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * Tabungan terencana: target yang disetor rutin tiap bulan.
 *
 * Integrasi dengan modul Tagihan mengikuti pola modul Kredit - tiap bulan
 * dibuatkan satu entri `bills`. Bedanya, saat tagihan setoran dibayar sistem
 * TIDAK mencatat pengeluaran, melainkan sebuah transfer dari akun sumber dana
 * ke akun penyimpanan (lihat BillController::pay).
 */
class SavingsService
{
    /** Setoran ditagih paling cepat sekian hari sebelum jatuh tempo. */
    public const GENERATION_HORIZON_DAYS = 31;

    /**
     * Lengkapi payload form dengan kolom turunan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withDerivedColumns(array $data, ?SavingsGoal $existing = null): array
    {
        $start = CarbonImmutable::parse($data['start_date']);

        $data['due_day'] = (int) ($data['due_day'] ?? $start->day);
        $data['status'] = $data['status'] ?? $existing?->status?->value ?? SavingsGoalStatus::Active->value;

        return $data;
    }

    /**
     * Nomor setoran berikutnya = jumlah tagihan setoran yang sudah pernah dibuat + 1.
     */
    public function nextContributionNumber(SavingsGoal $goal): int
    {
        return (int) $goal->bills()->max('contribution_number') + 1;
    }

    /**
     * Buat tagihan setoran berikutnya bila sudah masuk jendela penagihan.
     * Idempoten lewat unique index (savings_goal_id, contribution_number).
     */
    public function generateNextBill(
        SavingsGoal $goal,
        ?CarbonImmutable $today = null,
        bool $ignoreHorizon = false,
    ): ?Bill {
        $today ??= CarbonImmutable::today();

        if ($goal->status !== SavingsGoalStatus::Active) {
            return null;
        }

        // Target sudah tercapai: tidak perlu setoran baru.
        if ($goal->savedAmount() >= (float) $goal->target_amount) {
            return null;
        }

        // Masih ada setoran yang belum dibayar.
        if ($goal->bills()->where('status', BillStatus::Unpaid->value)->exists()) {
            return null;
        }

        $number = $this->nextContributionNumber($goal);
        $dueDate = $goal->dueDateFor($number);

        if (! $ignoreHorizon && $dueDate->greaterThan($today->addDays(self::GENERATION_HORIZON_DAYS))) {
            return null;
        }

        try {
            return $goal->bills()->create([
                'user_id' => $goal->user_id,
                // Akun pembayaran = akun sumber dana tabungan.
                'account_id' => $goal->source_account_id,
                'category_id' => null,
                'contribution_number' => $number,
                'title' => 'Setoran '.$goal->name.' #'.$number,
                'amount' => $goal->monthly_contribution,
                'due_date' => $dueDate->toDateString(),
                'status' => BillStatus::Unpaid->value,
                'remind_days_before' => 3,
            ]);
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * Generator harian untuk seluruh target aktif.
     */
    public function generateForAll(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $created = 0;

        SavingsGoal::query()
            ->where('status', SavingsGoalStatus::Active->value)
            ->chunkById(200, function ($goals) use ($today, &$created): void {
                foreach ($goals as $goal) {
                    $created += $this->generateNextBill($goal, $today) !== null ? 1 : 0;
                }
            });

        return $created;
    }

    /**
     * Tandai target tercapai / kembalikan ke berjalan sesuai dana terkumpul.
     */
    public function refreshStatus(SavingsGoal $goal): void
    {
        if ($goal->status === SavingsGoalStatus::Paused) {
            return;
        }

        $reached = $goal->savedAmount() >= (float) $goal->target_amount;
        $target = $reached ? SavingsGoalStatus::Completed : SavingsGoalStatus::Active;

        if ($goal->status !== $target) {
            $goal->forceFill(['status' => $target->value])->save();
        }
    }

    /**
     * Riwayat setoran: tagihan yang sudah/belum dibayar beserta transfernya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(SavingsGoal $goal): array
    {
        return $goal->bills()
            ->with(['transaction:id,transaction_date'])
            ->orderBy('contribution_number')
            ->get()
            ->map(function (Bill $bill) use ($goal) {
                $paid = $bill->status === BillStatus::Paid;

                $transfer = $paid
                    ? $goal->transfers()
                        ->whereDate('transfer_date', $bill->paid_at?->toDateString() ?? $bill->due_date->toDateString())
                        ->first()
                    : null;

                return [
                    'contribution_number' => $bill->contribution_number,
                    'bill_id' => $bill->getKey(),
                    'amount' => (float) $bill->amount,
                    'due_date' => $bill->due_date->toDateString(),
                    'due_label' => $bill->due_date->translatedFormat('d M Y'),
                    'status' => $bill->status->value,
                    'status_label' => $paid ? 'Sudah disetor' : 'Menunggu setoran',
                    'paid_label' => $bill->paid_at?->translatedFormat('d M Y'),
                    'transfer_id' => $transfer?->getKey(),
                ];
            })
            ->all();
    }

    /**
     * Payload target tabungan untuk frontend.
     *
     * @return array<string, mixed>
     */
    public static function present(SavingsGoal $goal): array
    {
        $goal->loadMissing(['sourceAccount', 'storageAccount']);

        $saved = $goal->savedAmount();
        $target = (float) $goal->target_amount;

        return [
            'id' => $goal->getKey(),
            'name' => $goal->name,
            'target_amount' => $target,
            'saved_amount' => $saved,
            'remaining_amount' => round(max(0, $target - $saved), 2),
            'monthly_contribution' => (float) $goal->monthly_contribution,
            'progress_percentage' => $goal->progressPercentage($saved),
            'bar_width' => min(100, (int) round($goal->progressPercentage($saved))),
            'remaining_contributions' => $goal->remainingContributions($saved),
            'progress_label' => $saved >= $target
                ? 'Target tercapai'
                : 'Terkumpul '.$goal->progressPercentage($saved).'% - sisa '
                    .$goal->remainingContributions($saved).' setoran',
            'start_date' => $goal->start_date?->toDateString(),
            'target_date' => $goal->target_date?->toDateString(),
            'target_date_label' => $goal->target_date?->translatedFormat('M Y'),
            'due_day' => $goal->due_day,
            'status' => $goal->status->value,
            'status_label' => $goal->status->label(),
            'notes' => $goal->notes,
            // Alokasi akun ganda
            'source_account_id' => $goal->source_account_id,
            'source_account' => $goal->sourceAccount?->displayName(),
            'storage_account_id' => $goal->storage_account_id,
            'storage_account' => $goal->storageAccount?->displayName(),
        ];
    }
}
