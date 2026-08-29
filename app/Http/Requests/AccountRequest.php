<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->getKey();
        $accountId = $this->route('account')?->getKey();

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('accounts', 'name')
                    ->where('user_id', $userId)
                    ->ignore($accountId),
            ],
            'type' => ['required', Rule::in(AccountType::values())],
            // Wajib untuk akun bank & e-wallet; akun kas fisik tidak memerlukannya.
            'account_number' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), [
                    AccountType::Bank->value,
                    AccountType::EWallet->value,
                ], true)),
                'nullable', 'string', 'max:40', 'regex:/^[0-9A-Za-z.\- ]+$/',
            ],
            'opening_balance' => ['required', 'numeric', 'min:-999999999999.99', 'max:999999999999.99'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Akun tunai tidak menyimpan nomor rekening walau formnya sempat terisi.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('type') === AccountType::Cash->value) {
            $this->merge(['account_number' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_number.required' => 'Nomor rekening wajib diisi untuk akun bank dan e-wallet.',
            'account_number.regex' => 'Nomor rekening hanya boleh berisi angka, huruf, titik, spasi, atau strip.',
        ];
    }
}
