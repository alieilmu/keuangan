<?php

namespace App\Models;

use App\Enums\CreditStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'account_id', 'category_id', 'name', 'total_amount', 'interest_rate',
    'monthly_installment', 'start_date', 'end_date', 'tenor_months', 'remaining_months',
    'due_day', 'status', 'notes',
])]
class Credit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CreditStatus::class,
            'total_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'monthly_installment' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'tenor_months' => 'integer',
            'remaining_months' => 'integer',
            'due_day' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** Jumlah angsuran yang sudah lunas. */
    public function paidMonths(): int
    {
        return max(0, $this->tenor_months - $this->remaining_months);
    }

    /** Nomor angsuran yang sedang berjalan, mis. 12 pada "bulan ke-12 dari 36". */
    public function currentInstallment(): int
    {
        return min($this->tenor_months, $this->paidMonths() + 1);
    }

    /** Sisa pokok yang belum dibayar berdasarkan tenor tersisa. */
    public function outstanding(): float
    {
        return round($this->remaining_months * (float) $this->monthly_installment, 2);
    }

    public function progressPercentage(): float
    {
        if ($this->tenor_months < 1) {
            return 0.0;
        }

        return round($this->paidMonths() / $this->tenor_months * 100, 1);
    }

    /**
     * Tanggal jatuh tempo angsuran ke-N.
     * Tanggal dijepit ke akhir bulan supaya due_day 31 tetap valid di Februari.
     */
    public function dueDateFor(int $installmentNumber): CarbonImmutable
    {
        $month = CarbonImmutable::parse($this->start_date)
            ->startOfMonth()
            ->addMonths(max(0, $installmentNumber - 1));

        return $month->setDay(min($this->due_day, $month->daysInMonth));
    }
}
