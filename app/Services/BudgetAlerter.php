<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use App\Notifications\BudgetThresholdReached;
use App\Support\BudgetStatus;

/**
 * Logika tunggal "kapan sebuah anggaran harus memicu notifikasi".
 * Dipakai oleh scheduler harian maupun evaluasi realtime setelah transaksi dibuat.
 */
class BudgetAlerter
{
    /** Ambang batas yang memicu notifikasi, diurutkan dari yang tertinggi. */
    public const THRESHOLDS = [100, BudgetStatus::NOTIFY_THRESHOLD];

    /**
     * @param  float  $spent  total pengeluaran kategori pada periode budget
     * @return bool true bila notifikasi dikirim
     */
    public function evaluate(User $user, Budget $budget, float $spent, string $categoryName): bool
    {
        $limit = (float) $budget->limit_amount;

        if ($limit <= 0) {
            return false;
        }

        $percentage = round($spent / $limit * 100, 1);
        $crossed = null;

        foreach (self::THRESHOLDS as $threshold) {
            if ($percentage >= $threshold) {
                $crossed = $threshold;
                break;
            }
        }

        // Pemakaian turun lagi di bawah ambang: reset agar bisa dinotifikasi ulang.
        if ($crossed === null) {
            if ($budget->notified_threshold !== null) {
                Budget::query()->whereKey($budget->getKey())->update(['notified_threshold' => null]);
            }

            return false;
        }

        if ($budget->notified_threshold !== null && $budget->notified_threshold >= $crossed) {
            return false; // Sudah pernah diberitahu pada ambang batas ini.
        }

        $user->notify(new BudgetThresholdReached($categoryName, $spent, $limit, $percentage));

        Budget::query()->whereKey($budget->getKey())->update(['notified_threshold' => $crossed]);

        return true;
    }
}
