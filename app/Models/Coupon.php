<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'description', 'type', 'value', 'max_uses', 'starts_at', 'ends_at', 'is_active'])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'max_uses' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<SubscriptionOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class);
    }

    /** Pemakaian = pesanan yang masih menunggu atau sudah lunas. */
    public function usedCount(): int
    {
        return $this->orders()
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Paid->value])
            ->count();
    }

    public function isUsable(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture())
            && ($this->max_uses === null || $this->usedCount() < $this->max_uses);
    }

    public function discountFor(int $subtotal): int
    {
        return $this->type === CouponType::Percent
            ? intdiv($subtotal * min($this->value, 100), 100)
            : min($this->value, $subtotal);
    }

    public function describe(): string
    {
        return $this->type === CouponType::Percent
            ? "Diskon {$this->value}%"
            : 'Potongan Rp'.number_format($this->value, 0, ',', '.');
    }
}
