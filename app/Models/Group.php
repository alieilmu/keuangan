<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Satu kas bersama yang dipakai beberapa user.
 *
 * Anggota group melihat dan mengelola kumpulan data keuangan yang sama.
 * Kolom user_id pada data tetap mencatat siapa yang menginput, sehingga
 * riwayat per orang tidak hilang.
 */
#[Fillable(['name'])]
class Group extends Model
{
    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<SubscriptionOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class);
    }

    /** @return HasOne<Subscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Batas jumlah anggota dari paket langganan saat ini. Null = tanpa
     * batas (paket Premium) atau grup belum berlangganan sama sekali.
     */
    public function memberQuota(): ?int
    {
        $subscription = $this->subscription;
        $max = $subscription?->plan?->max_members;

        // Slot anggota yang dibeli terpisah menambah kuota paket berbatas.
        return $max === null ? null : $max + (int) $subscription->extra_members;
    }

    /** Null = tanpa batas. */
    public function remainingQuota(): ?int
    {
        $quota = $this->memberQuota();

        return $quota === null ? null : max($quota - $this->users()->count(), 0);
    }

    public function hasCapacityFor(int $additional = 1): bool
    {
        $quota = $this->memberQuota();

        return $quota === null || ($this->users()->count() + $additional) <= $quota;
    }
}
