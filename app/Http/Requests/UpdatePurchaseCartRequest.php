<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'price' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'sale_price' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}
