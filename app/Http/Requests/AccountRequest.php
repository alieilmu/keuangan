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
            'opening_balance' => ['required', 'numeric', 'min:-999999999999.99', 'max:999999999999.99'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['boolean'],
        ];
    }
}
