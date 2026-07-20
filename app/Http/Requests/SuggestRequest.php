<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuggestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:queries,brands,categories,products'],
        ];
    }
}
