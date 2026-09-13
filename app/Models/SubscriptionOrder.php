<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference', 'group_id', 'user_id', 'type', 'subscription_plan_id', 'billing_months', 'extra_members',
    'subtotal', 'discount', 'total', 'coupon_id', 'coupon_code', 'lines', 'status', 'paid_at',
    'confirmed_by', 'canceled_at',
])]
class SubscriptionOrder extends Model
{
    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'billing_months' => 'integer',
            'extra_members' => 'integer',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'lines' => 'array',
            'paid_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function describe(): string
    {
        return $this->type === OrderType::Plan
            ? ($this->plan?->name ?? 'Paket').' '.$this->billing_months.' bulan'
            : $this->extra_members.' slot anggota tambahan';
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'description' => $this->describe(),
            'group_name' => $this->group?->name,
            'user_name' => $this->user?->name,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'coupon_code' => $this->coupon_code,
            'lines' => $this->lines ?? [],
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_label' => $this->created_at?->translatedFormat('d M Y H:i'),
            'paid_label' => $this->paid_at?->translatedFormat('d M Y H:i'),
        ];
    }
}
