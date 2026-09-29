<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ConfirmOrderRequest extends FormRequest
{
    /**
     * Authorize Request
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Validation Rules
     */
    public function rules(): array
    {
        $isAdvance = $this->payment_method === 'advance';
        $isBank = $isAdvance && $this->trans_payment_method === 'bank';
        $isMobile = $isAdvance && $this->trans_payment_method === 'mobile';

        return [

            /*
            |--------------------------------------------------------------------------
            | Order
            |--------------------------------------------------------------------------
            */

            'remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'same_address' => [
                'nullable',
                'boolean',
            ],

            'save_info' => [
                'nullable',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Shipping Address
            |--------------------------------------------------------------------------
            */

            'address_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('customer_addresses', 'id')
                    ->where('user_id', auth()->id()),
            ],

            /*
            |--------------------------------------------------------------------------
            | Payment
            |--------------------------------------------------------------------------
            */

            'payment_method' => [
                'bail',
                'required',
                Rule::in(['cod', 'advance']),
            ],

            /*
            |--------------------------------------------------------------------------
            | Advance Payment
            |--------------------------------------------------------------------------
            */

            'trans_payment_method' => [
                'bail',
                Rule::requiredIf($isAdvance),
                Rule::in(['mobile', 'bank']),
            ],

            'bank_name' => [
                'bail',
                Rule::requiredIf($isAdvance),
                'string',
                'max:255',
            ],

            'account_number' => [
                'bail',
                Rule::requiredIf($isAdvance),
                'string',
                'regex:/^[A-Za-z0-9\s\-]+$/',
                'max:100',
            ],

            'transaction_id' => [
                'bail',
                Rule::requiredIf($isMobile),
                'nullable',
                'string',
                'max:100',
                Rule::unique('order_payments', 'transaction_id'),
            ],

            'account_holder_name' => [
                'bail',
                Rule::requiredIf($isBank),
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Coupon
            |--------------------------------------------------------------------------
            */

            'coupon' => [
                'nullable',
                'string',
                'max:100',
                Rule::exists('coupons', 'code')->where(function ($query) {

                    $query->where('is_active', true);

                    $query->where(function ($q) {
                        $q->whereNull('start_date')
                            ->orWhere('start_date', '<=', now());
                    });

                    $query->where(function ($q) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', now());
                    });
                }),
            ],
        ];
    }

    /**
     * Custom Error Messages
     */
    public function messages(): array
    {
        return [

            'address_id.required' => 'Please select a shipping address.',
            'address_id.exists'   => 'Selected shipping address is invalid.',

            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in'       => 'Invalid payment method selected.',

            'remarks.max' => 'Remarks may not exceed 1000 characters.',

            'trans_payment_method.required' => 'Please select an advance payment method.',
            'trans_payment_method.in'       => 'Invalid advance payment method.',

            'bank_name.required' => 'Bank or Mobile Banking name is required.',

            'account_number.required' => 'Account number is required.',
            'account_number.regex'    => 'Invalid account number format.',

            'transaction_id.required' => 'Transaction ID is required.',
            'transaction_id.unique'   => 'This transaction ID has already been used.',

            'account_holder_name.required' => 'Account holder name is required.',

            'coupon.exists' => 'Invalid or expired coupon code.',
            'coupon.max'    => 'Coupon code may not exceed 100 characters.',
        ];
    }

    /**
     * Custom Attribute Names
     */
    public function attributes(): array
    {
        return [
            'address_id' => 'shipping address',
            'payment_method' => 'payment method',
            'trans_payment_method' => 'advance payment method',
            'bank_name' => 'bank/mobile banking',
            'account_number' => 'account number',
            'transaction_id' => 'transaction ID',
            'account_holder_name' => 'account holder name',
            'coupon' => 'coupon code',
        ];
    }

    /**
     * JSON Validation Response
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * JSON Unauthorized Response
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403)
        );
    }
}
