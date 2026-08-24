<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->getKey();
        $budgetId = $this->route('budget')?->getKey();

        return [
            'category_id' => [
                'required', 'integer',
                // Anggaran hanya masuk akal untuk kategori pengeluaran.
                Rule::exists('categories', 'id')
                    ->where('user_id', $userId)
                    ->where('type', TransactionType::Expense->value),
                // Satu kategori hanya boleh punya satu plafon per periode.
                Rule::unique('budgets', 'category_id')
                    ->where(fn ($query) => $query
                        ->where('user_id', $userId)
                        ->where('period_month', $this->integer('period_month'))
                        ->where('period_year', $this->integer('period_year')))
                    ->ignore($budgetId),
            ],
            'limit_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.unique' => 'Kategori ini sudah punya anggaran pada periode tersebut.',
            'category_id.exists' => 'Kategori pengeluaran tidak ditemukan.',
        ];
    }
}
