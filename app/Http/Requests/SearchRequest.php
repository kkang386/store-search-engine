<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . config('search.per_page_max', 100)],
            'sort' => ['nullable', 'string', 'in:relevance,price_asc,price_desc,newest,rating,best_selling'],
            'brand' => ['nullable', 'array'],
            'brand.*' => ['string', 'max:100'],
            'category' => ['nullable', 'array'],
            'category.*' => ['integer'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['array'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'facets' => ['nullable', 'boolean'],
            'explain' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sort.in' => 'Sort must be one of: relevance, price_asc, price_desc, newest, rating, best_selling',
            'per_page.max' => 'Per page maximum is ' . config('search.per_page_max', 100),
        ];
    }
}
