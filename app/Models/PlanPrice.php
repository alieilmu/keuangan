<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu opsi durasi yang bisa dibeli untuk sebuah paket, mis. Premium 6 bulan. */
#[Fillable(['subscription_plan_id', 'months', 'price', 'is_active'])]
class PlanPrice extends Model
{
    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function label(): string
    {
        return match ($this->months) {
            1 => 'Bulanan',
            12 => 'Tahunan (12 bulan)',
            default => "{$this->months} bulan",
        };
    }
}
