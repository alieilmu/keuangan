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
     * oleh scheduler. Paket Free (expires_at null) selalu aktif.
     */
    public function isEffectivelyActive(): bool
    {
        if ($this->status !== SubscriptionStatus::Active) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
