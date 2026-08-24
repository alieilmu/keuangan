<?php

namespace App\Models;

use App\Enums\DocumentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class Document extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** Ukuran berkas dalam format yang mudah dibaca. */
    public function humanSize(): string
    {
        $kb = $this->size / 1024;

        return $kb < 1024
            ? number_format($kb, 0, ',', '.').' KB'
            : number_format($kb / 1024, 1, ',', '.').' MB';
    }
}
