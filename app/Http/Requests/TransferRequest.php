<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->getKey();

        $ownedAccount = fn () => Rule::exists('accounts', 'id')->where('user_id', $userId);

        return [
            'from_account_id' => ['required', 'integer', $ownedAccount()],
            // Beda baris akun wajib, TAPI bank atau nomor rekening yang sama
            // tetap diizinkan (mis. Mandiri ke Mandiri).
            'to_account_id' => ['required', 'integer', 'different:from_account_id', $ownedAccount()],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'transfer_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_account_id.different' => 'Akun tujuan tidak boleh sama dengan akun asal.',
            'amount.gt' => 'Nominal transfer harus lebih besar dari 0.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_account_id' => 'akun asal',
            'to_account_id' => 'akun tujuan',
        ];
    }
}
