<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreditRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $scopeIds = $this->user()->visibleUserIds();

        return [
            'name' => ['required', 'string', 'max:100'],
            'total_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'interest_rate' => ['nullable', 'numeric', 'between:0,999.99'],
            'monthly_installment' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'due_day' => ['required', 'integer', 'between:1,31'],
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
            // Hanya dipakai saat membuat kredit yang sudah berjalan sebagian.
            'remaining_months' => ['nullable', 'integer', 'min:0', 'max:1200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Target selesai tidak boleh lebih awal dari tanggal mulai.',
            'monthly_installment.gt' => 'Cicilan per bulan harus lebih besar dari 0.',
            'due_day.between' => 'Tanggal jatuh tempo harus antara 1 sampai 31.',
        ];
    }
}
