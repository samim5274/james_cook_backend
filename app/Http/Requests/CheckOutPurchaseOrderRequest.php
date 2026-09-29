<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrderPayment;
use App\Models\Supplyer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CheckOutPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists((new Supplyer)->getTable(), 'id'),
            ],
            'supplier_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'supplier_phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
            ],
            'payment_method' => [
                'required',
                'string',
                Rule::in(PurchaseOrderPayment::PAYMENT_METHODS),
            ],
            'vat' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'discount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'received_amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
