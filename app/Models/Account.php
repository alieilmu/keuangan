<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'type', 'account_number', 'opening_balance', 'balance', 'color', 'is_active'])]
class Account extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'opening_balance' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Nomor rekening wajib untuk akun bank & e-wallet, tidak berlaku untuk
     * akun kas fisik seperti dompet tunai.
     */
    public function requiresAccountNumber(): bool
    {
        return in_array($this->type, [AccountType::Bank, AccountType::EWallet], true);
    }

    /** Label singkat untuk pemilih akun: "BCA Utama - 1234567890". */
    public function displayName(): string
    {
        return filled($this->account_number)
            ? $this->name.' - '.$this->account_number
            : $this->name;
    }
}
