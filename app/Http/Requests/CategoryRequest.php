<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->getKey();
        $categoryId = $this->route('category')?->getKey();

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->where('user_id', $userId)->where('type', $this->input('type')))
                    ->ignore($categoryId),
            ],
            'type' => ['required', Rule::in(TransactionType::values())],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:40'],
        ];
    }
}
