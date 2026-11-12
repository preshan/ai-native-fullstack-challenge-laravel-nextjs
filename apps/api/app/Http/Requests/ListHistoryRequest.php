<?php

namespace App\Http\Requests;

use App\Enums\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ListHistoryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Enum>>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', Rule::enum(Category::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('pagination.max_per_page')],
        ];
    }

    public function category(): ?Category
    {
        $category = $this->input('category');

        return is_string($category) && $category !== '' ? Category::from($category) : null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', config('pagination.default_per_page'));
    }
}
