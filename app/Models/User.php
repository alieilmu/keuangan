<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable([
    'name', 'email', 'password', 'group_id', 'is_admin', 'is_blocked', 'blocked_at',
    'walkthrough_step', 'walkthrough_completed_at', 'walkthrough_skipped_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
            'blocked_at' => 'datetime',
            'walkthrough_step' => 'integer',
            'walkthrough_completed_at' => 'datetime',
            'walkthrough_skipped_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Id seluruh user yang datanya boleh dilihat & dikelola user ini.
     *
     * Bila tergabung dalam sebuah group, cakupannya adalah seluruh anggota
     * group tersebut (kas bersama). Bila belum punya group, cakupannya hanya
     * dirinya sendiri -- sehingga user baru tetap terisolasi sampai
     * dimasukkan ke sebuah group.
     *
     * @return array<int, int>
     */
    public function visibleUserIds(): array
    {
        if ($this->group_id === null) {
            return [(int) $this->getKey()];
        }

        return static::query()
            ->where('group_id', $this->group_id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Anggota group selain dirinya sendiri.
     *
     * @return array<int, int>
     */
    public function partnerUserIds(): array
    {
        return array_values(array_diff($this->visibleUserIds(), [(int) $this->getKey()]));
    }

    /** Apakah baris milik $userId berada dalam cakupan user ini. */
    public function canReach(?int $userId): bool
    {
        return $userId !== null && in_array((int) $userId, $this->visibleUserIds(), true);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Budget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** @return HasMany<Credit, $this> */
    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    /** @return HasMany<Transfer, $this> */
    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    /** @return HasMany<SavingsGoal, $this> */
    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function initials(): string
    {
        $parts = collect(explode(' ', trim($this->name)))->filter()->take(2);

        return $parts->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'U';
    }
}
