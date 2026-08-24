<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'category_id', 'limit_amount', 'period_month', 'period_year', 'notified_threshold'])]
class Budget extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limit_amount' => 'decimal:2',
            'period_month' => 'integer',
            'period_year' => 'integer',
            'notified_threshold' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
