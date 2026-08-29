<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Services\DocumentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $scopeIds = $this->user()->visibleUserIds();

        return [
            'title' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'due_date' => ['required', 'date'],
            'account_id' => [
                'nullable', 'integer',
                Rule::exists('accounts', 'id')->whereIn('user_id', $scopeIds),
            ],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')
                    ->whereIn('user_id', $scopeIds)
                    ->where('type', TransactionType::Expense->value),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
            'remind_days_before' => ['required', 'integer', 'between:0,30'],
            // Dokumen tagihan (PDF / gambar) wajib saat tagihan dibuat manual.
            // Saat mengubah, boleh dikosongkan agar berkas lama tetap dipakai.
            'document' => DocumentService::rules(required: $this->routeIs('bills.store')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return DocumentService::messages('document');
    }
}
