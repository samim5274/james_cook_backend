<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.cart_id' => ['required', 'integer', 'exists:carts,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required to process a return.',
            'items.*.cart_id.exists' => 'One of the selected items is invalid.',
            'items.*.quantity.min' => 'Return quantity must be at least 1.',
        ];
    }
}
