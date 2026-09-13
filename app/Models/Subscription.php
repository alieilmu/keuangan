<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'group_id', 'subscription_plan_id', 'status',
    'started_at', 'expires_at', 'canceled_at', 'changed_by',
    'billing_months', 'amount', 'extra_members',
])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'canceled_at' => 'datetime',
            'billing_months' => 'integer',
            'amount' => 'integer',
            'extra_members' => 'integer',
        ];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return HasMany<PaymentSimulation, $this> */
    public function paymentSimulations(): HasMany
    {
        return $this->hasMany(PaymentSimulation::class);
    }

    /**
     * Status efektif: langganan berbayar yang lewat tanggal kedaluwarsa
     * dianggap kedaluwarsa meski kolom status di database belum diperbarui
     * oleh scheduler. Paket tanpa expires_at selalu aktif.
     */
    public function isEffectivelyActive(): bool
    {
        if ($this->status !== SubscriptionStatus::Active) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Nilai langganan per bulan, dasar perhitungan MRR. Baris yang dibuat
     * lewat pesanan menyimpan amount (sudah termasuk diskon & slot anggota)
     * untuk satu periode billing_months. Baris lama tanpa amount jatuh ke
     * harga dasar paket -- sama persis dengan perhitungan MRR sebelumnya.
     */
    public function monthlyValue(): float
    {
        if ($this->amount !== null && $this->billing_months > 0) {
            return $this->amount / $this->billing_months;
        }

        return (float) ($this->plan?->price ?? 0)
            + (int) $this->extra_members * (int) ($this->plan?->extra_member_price ?? 0);
    }

    public function isDemo(): bool
    {
        return $this->plan?->code === 'demo';
    }

    /**
     * Sisa hari aktif. Null bila tidak ada tanggal kedaluwarsa (langganan
     * tanpa batas waktu). Dibulatkan ke ATAS supaya sisa 6 jam tetap
     * tampil "1 hari lagi", bukan "0 hari" yang membingungkan.
     */
    public function remainingDays(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        if ($this->expires_at->isPast()) {
            return 0;
        }

        return (int) ceil(now()->diffInHours($this->expires_at) / 24);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
