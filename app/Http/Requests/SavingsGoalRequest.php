<?php

namespace App\Http\Requests;

use App\Enums\SavingsGoalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavingsGoalRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->getKey();
        $ownedAccount = fn () => Rule::exists('accounts', 'id')->where('user_id', $userId);

        return [
            'name' => ['required', 'string', 'max:100'],
            'target_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'monthly_contribution' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'start_date' => ['required', 'date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            // Alokasi akun ganda: sumber dana dan penyimpanan harus berbeda,
            // karena setoran dicatat sebagai transfer antar keduanya.
            'source_account_id' => ['required', 'integer', $ownedAccount()],
            'storage_account_id' => [
                'required', 'integer', 'different:source_account_id', $ownedAccount(),
            ],
            'status' => ['nullable', Rule::in(SavingsGoalStatus::values())],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'storage_account_id.different' => 'Akun penyimpanan harus berbeda dari akun sumber dana.',
            'target_date.after_or_equal' => 'Target selesai tidak boleh lebih awal dari tanggal mulai.',
            'monthly_contribution.gt' => 'Setoran bulanan harus lebih besar dari 0.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'source_account_id' => 'akun sumber dana',
            'storage_account_id' => 'akun penyimpanan',
        ];
    }
}
