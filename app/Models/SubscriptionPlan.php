<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'price', 'max_members', 'extra_member_price', 'features', 'is_active'])]
class SubscriptionPlan extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'max_members' => 'integer',
            'extra_member_price' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<PlanPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class)->orderBy('months');
    }

    /** Slot anggota tambahan hanya dijual untuk paket berkuota dengan harga slot. */
    public function sellsExtraMembers(): bool
    {
        return $this->max_members !== null && $this->extra_member_price > 0;
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }
}
