<?php

namespace App\Models;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'account_id', 'category_id', 'transfer_id', 'type', 'amount', 'transaction_date', 'description'])]
class Transaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
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

    /** Terisi bila baris ini adalah salah satu kaki dari sebuah transfer. */
    /** @return BelongsTo<Transfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function isTransferLeg(): bool
    {
        return $this->transfer_id !== null;
    }

    /**
     * Batasi query pada rentang satu bulan kalender.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeInPeriod(Builder $query, int $year, int $month): void
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        $query->whereBetween('transaction_date', [
            $start->toDateString(),
            $start->endOfMonth()->toDateString(),
        ]);
    }
}
