<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'savings_goal_id', 'from_account_id', 'to_account_id',
    'amount', 'transfer_date', 'description', 'reference',
])]
class Transfer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transfer_date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    /** @return BelongsTo<SavingsGoal, $this> */
    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }

    /**
     * Dua kaki mutasi: satu keluar dari akun pengirim, satu masuk ke penerima.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Transfer sesama bank / nomor rekening sama tetap sah dan tetap dicatat
     * dua sisi; penanda ini hanya untuk ditampilkan di riwayat.
     */
    public function isSameInstitution(): bool
    {
        $this->loadMissing(['fromAccount', 'toAccount']);

        return $this->fromAccount?->type === $this->toAccount?->type
            && filled($this->fromAccount?->account_number)
            && $this->fromAccount?->account_number === $this->toAccount?->account_number;
    }
}
