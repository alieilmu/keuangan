<?php

namespace App\Support;

/**
 * Aturan indikator warna anggaran (dipakai backend & frontend).
 *
 *   0% - 50%   : hijau      (aman)
 *  51% - 70%   : kuning     (peringatan dini)
 *  71% - 100%  : merah      (mendekati / mencapai limit)
 *  > 100%      : merah gelap(overbudget)
 */
final class BudgetStatus
{
    public const SAFE = 'safe';

    public const WARNING = 'warning';

    public const DANGER = 'danger';

    public const OVER = 'over';

    /** Ambang batas yang memicu push notification. */
    public const NOTIFY_THRESHOLD = 70;

    public static function fromPercentage(float $percentage): string
    {
        return match (true) {
            $percentage > 100 => self::OVER,
            $percentage > 70 => self::DANGER,
            $percentage > 50 => self::WARNING,
            default => self::SAFE,
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::OVER => 'Overbudget',
            self::DANGER => 'Mendekati limit',
            self::WARNING => 'Perlu diwaspadai',
            default => 'Aman',
        };
    }
}
