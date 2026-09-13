<?php

namespace App\Models;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'group_id', 'category', 'message', 'status', 'admin_note', 'handled_by', 'handled_at'])]
class Feedback extends Model
{
    protected $table = 'feedback';

    protected function casts(): array
    {
        return [
            'category' => FeedbackCategory::class,
            'status' => FeedbackStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
