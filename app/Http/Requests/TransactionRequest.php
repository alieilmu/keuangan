<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->getKey();

        return [
            'account_id' => [
                'required', 'integer',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId),
            ],
            // Hanya income/expense: kaki transfer dibuat lewat modul Transfer.
            'type' => ['required', Rule::in(TransactionType::manualValues())],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'transaction_date' => ['required', 'date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'Nominal harus lebih besar dari 0.',
            'account_id.exists' => 'Akun tidak ditemukan.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
        ];
    }
}
