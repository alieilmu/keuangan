<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Enums\DocumentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'user_id', 'account_id', 'category_id', 'transaction_id', 'credit_id',
    'installment_number', 'title', 'amount', 'due_date', 'status', 'paid_at',
    'notes', 'remind_days_before', 'reminded_on',
])]
class Bill extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BillStatus::class,
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'reminded_on' => 'date',
            'remind_days_before' => 'integer',
            'installment_number' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Terisi bila tagihan ini dibuat otomatis dari sebuah kredit.
     *
     * @return BelongsTo<Credit, $this>
     */
    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Berkas tagihan yang diunggah user. */
    public function invoiceDocument(): ?Document
    {
        return $this->documents->firstWhere('kind', DocumentKind::Invoice);
    }

    /** Nota pembayaran yang diunggah saat tagihan dibayar. */
    public function receiptDocument(): ?Document
    {
        return $this->documents->firstWhere('kind', DocumentKind::Receipt);
    }
}
