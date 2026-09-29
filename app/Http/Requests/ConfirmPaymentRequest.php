<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:99999999.99',
            ],

            'payment_method' => [
                'required',
                Rule::in([
                    'cod',
                    'cash',
                    'bank_transfer',
                    'mobile_banking',
                    'card',
                    'paypal',
                    'wallet',
                ]),
            ],

            'provider' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($this->payment_method, [
                    'mobile_banking',
                    'card',
                    'paypal',
                ])),
                Rule::in([
                    'manual',
                    'bkash',
                    'nagad',
                    'rocket',
                    'sslcommerz',
                    'stripe',
                    'paypal',
                ]),
            ],

            'transaction_id' => [
                'nullable',
                'required_if:payment_method,mobile_banking,card,paypal,bank_transfer',
                'string',
                'max:100',
            ],

            'bank_name' => [
                'nullable',
                'required_if:payment_method,bank_transfer',
                'string',
                'max:150',
            ],

            'account_number' => [
                'nullable',
                'required_if:payment_method,bank_transfer',
                'string',
                'max:100',
            ],

            'account_holder_name' => [
                'nullable',
                'required_if:payment_method,bank_transfer',
                'string',
                'max:150',
            ],

            'sender_mobile' => [
                'nullable',
                'required_if:payment_method,mobile_banking',
                'string',
                'max:20',
            ],

            'sender_name' => [
                'nullable',
                'required_if:payment_method,mobile_banking',
                'string',
                'max:150',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Payment amount is required.',
            'amount.numeric' => 'Payment amount must be a valid number.',
            'amount.min' => 'Payment amount must be greater than zero.',

            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'The selected payment method is invalid.',

            'provider.required' => 'Please select a payment provider.',

            'transaction_id.required_if' => 'Transaction ID is required.',

            'bank_name.required_if' => 'Bank name is required.',
            'account_number.required_if' => 'Account number is required.',
            'account_holder_name.required_if' => 'Account holder name is required.',

            'sender_mobile.required_if' => 'Sender mobile number is required.',
            'sender_name.required_if' => 'Sender name is required.',
        ];
    }

    /**
     * Attribute names
     */
    public function attributes(): array
    {
        return [
            'amount' => 'payment amount',
            'payment_method' => 'payment method',
            'provider' => 'payment provider',
            'transaction_id' => 'transaction ID',
            'bank_name' => 'bank name',
            'account_number' => 'account number',
            'account_holder_name' => 'account holder name',
            'sender_mobile' => 'sender mobile number',
            'sender_name' => 'sender name',
            'remarks' => 'remarks',
        ];
    }
}
