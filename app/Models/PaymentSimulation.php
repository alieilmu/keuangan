<?php

namespace App\Models;

use App\Enums\PaymentSimulationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subscription_id', 'method', 'amount', 'status', 'reference', 'simulated_at'])]
class PaymentSimulation extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentSimulationStatus::class,
            'simulated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
