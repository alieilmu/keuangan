<?php

namespace App\Models;

use App\Enums\SavingsGoalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'source_account_id', 'storage_account_id', 'name', 'target_amount',
    'monthly_contribution', 'start_date', 'target_date', 'due_day', 'status', 'notes',
])]
class SavingsGoal extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SavingsGoalStatus::class,
            'target_amount' => 'decimal:2',
            'monthly_contribution' => 'decimal:2',
            'start_date' => 'date',
            'target_date' => 'date',
            'due_day' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Akun sumber dana: tempat uang setoran diambil. */
    /** @return BelongsTo<Account, $this> */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    /** Akun penyimpanan: tempat dana tabungan dikumpulkan. */
    /** @return BelongsTo<Account, $this> */
    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'storage_account_id');
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** @return HasMany<Transfer, $this> */
    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    /** Dana yang benar-benar sudah masuk ke akun penyimpanan. */
    public function savedAmount(): float
    {
        return round((float) $this->transfers()->sum('amount'), 2);
    }

    public function progressPercentage(?float $saved = null): float
    {
        $target = (float) $this->target_amount;

        if ($target <= 0) {
            return 0.0;
        }

        return round(min(999, ($saved ?? $this->savedAmount()) / $target * 100), 1);
    }

    /** Perkiraan jumlah setoran yang masih dibutuhkan. */
    public function remainingContributions(?float $saved = null): int
    {
        $monthly = (float) $this->monthly_contribution;
        $left = (float) $this->target_amount - ($saved ?? $this->savedAmount());

        if ($monthly <= 0 || $left <= 0) {
            return 0;
        }

        return (int) ceil($left / $monthly);
    }

    /** Tanggal jatuh tempo setoran ke-N, dijepit ke akhir bulan. */
    public function dueDateFor(int $contributionNumber): CarbonImmutable
    {
        $month = CarbonImmutable::parse($this->start_date)
            ->startOfMonth()
            ->addMonths(max(0, $contributionNumber - 1));

        return $month->setDay(min($this->due_day, $month->daysInMonth));
    }
}
