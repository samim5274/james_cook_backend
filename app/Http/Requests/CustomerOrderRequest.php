<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CustomerOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $isAdvance = $this->payment_method === 'advance';
        $isDeliveryPayment = filter_var($this->is_delivery_charge_payment, FILTER_VALIDATE_BOOLEAN);

        $this->merge([
            // ---------------------------------------------------------
            // Product Payment (Advance) fields
            // ---------------------------------------------------------
            'trans_payment_method' => $isAdvance ? $this->trans_payment_method : null,
            'account_number'       => $isAdvance ? $this->account_number : null,
            'transaction_id'       => $isAdvance && filled($this->transaction_id) ? $this->transaction_id : null,
            'bank_name'            => $isAdvance ? $this->bank_name : null,
            'account_holder_name'  => $isAdvance ? $this->account_holder_name : null,

            // ---------------------------------------------------------
            // Delivery Charge Payment fields
            // ---------------------------------------------------------
            'delivery_trans_payment_method' => $isDeliveryPayment ? $this->delivery_trans_payment_method : null,
            'delivery_account_number'       => $isDeliveryPayment ? $this->delivery_account_number : null,
            'delivery_transaction_id'       => $isDeliveryPayment && filled($this->delivery_transaction_id) ? $this->delivery_transaction_id : null,
            'delivery_bank_name'            => $isDeliveryPayment ? $this->delivery_bank_name : null,
            'delivery_account_holder_name'  => $isDeliveryPayment ? $this->delivery_account_holder_name : null,
        ]);
    }

    /**
     * Validation Rules
     */
    public function rules(): array
    {
        $isAdvance = $this->payment_method === 'advance';
        $isBank    = $isAdvance && $this->trans_payment_method === 'bank';
        $isMobile  = $isAdvance && $this->trans_payment_method === 'mobile';

        $isDeliveryPayment = filter_var($this->is_delivery_charge_payment, FILTER_VALIDATE_BOOLEAN);
        $isDeliveryMobile  = $isDeliveryPayment && $this->delivery_trans_payment_method === 'mobile';
        $isDeliveryBank    = $isDeliveryPayment && $this->delivery_trans_payment_method === 'bank';

        return [

            /*
            |--------------------------------------------------------------------------
            | Order Items
            |--------------------------------------------------------------------------
            */

            'items' => ['bail', 'required', 'array', 'min:1'],
            'items.*.product_id' => ['bail', 'required', 'integer', Rule::exists('products', 'id')],
            'items.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'items.*.quantity'   => ['bail', 'required', 'integer', 'min:1'],
            'items.*.price'      => ['bail', 'required', 'numeric', 'min:0'],
            'items.*.discount'   => ['nullable', 'numeric', 'min:0'],
            'items.*.point'      => ['nullable', 'numeric', 'min:0'],

            /*
            |--------------------------------------------------------------------------
            | Customer Information
            |--------------------------------------------------------------------------
            */

            'recipient_name' => ['bail', 'required', 'string', 'max:255'],
            'phone' => ['bail', 'required', 'regex:/^(?:\+8801|01)[3-9]\d{8}$/'],

            /*
            |--------------------------------------------------------------------------
            | Shipping Address
            |--------------------------------------------------------------------------
            */

            'division_id' => ['bail', 'required', Rule::exists('divisions', 'id')],
            'district_id' => ['bail', 'required', Rule::exists('districts', 'id')],
            'upazila_id'  => ['bail', 'required', Rule::exists('upazilas', 'id')],
            'police_station_id' => ['nullable', Rule::exists('police_stations', 'id')],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'label' => ['nullable', Rule::in(['Home', 'Office', 'Other'])],
            'address' => ['bail', 'required', 'string', 'max:1000'],

            /*
            |--------------------------------------------------------------------------
            | Order
            |--------------------------------------------------------------------------
            */

            'remarks' => ['nullable', 'string', 'max:1000'],

            /*
            |--------------------------------------------------------------------------
            | Payment
            |--------------------------------------------------------------------------
            */

            'payment_method' => ['bail', 'required', Rule::in(['cod', 'advance'])],
            'trans_payment_method' => [
                'nullable',
                Rule::requiredIf($isAdvance),
                Rule::in(['mobile', 'bank']),
            ],

            /*
            |--------------------------------------------------------------------------
            | Advance Payment
            |--------------------------------------------------------------------------
            */

            'bank_name' => ['nullable', Rule::requiredIf($isMobile || $isBank), 'string', 'max:255'],
            'account_number' => [
                'nullable',
                Rule::requiredIf($isMobile || $isBank),
                'string', 'max:100',
                'regex:/^[A-Za-z0-9\s\-]+$/',
            ],
            'transaction_id' => [
                'nullable',
                Rule::requiredIf($isMobile),
                'string', 'max:100',
                Rule::unique('order_payments', 'transaction_id')
                    ->where(fn ($q) => $q->whereNotNull('transaction_id')->where('transaction_id', '!=', '')),
            ],
            'account_holder_name' => ['nullable', Rule::requiredIf($isBank), 'string', 'max:255'],

            /*
            |--------------------------------------------------------------------------
            | Delivery Charge Payment
            |--------------------------------------------------------------------------
            */

            'is_delivery_charge_payment' => [
                'nullable',
                'boolean',
            ],

            'delivery_charge_amount' => [
                'nullable',
                Rule::requiredIf($isDeliveryPayment),
                'numeric',
                'min:0',
            ],

            'delivery_trans_payment_method' => [
                'nullable',
                Rule::requiredIf($isDeliveryPayment),
                Rule::in([
                    'mobile',
                    'bank',
                ]),
            ],

            'delivery_bank_name' => [
                'nullable',
                Rule::requiredIf($isDeliveryMobile || $isDeliveryBank),
                'string',
                'max:255',
            ],

            'delivery_account_number' => [
                'nullable',
                Rule::requiredIf($isDeliveryMobile || $isDeliveryBank),
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9\s\-]+$/',
            ],

            'delivery_transaction_id' => [
                'nullable',
                Rule::requiredIf($isDeliveryMobile),
                'string',
                'max:100',
                Rule::unique('delivery_charge_payments', 'transaction_id')
                    ->where(fn ($q) => $q->whereNotNull('transaction_id')->where('transaction_id', '!=', '')),
            ],

            'delivery_account_holder_name' => [
                'nullable',
                Rule::requiredIf($isDeliveryBank),
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Coupon
            |--------------------------------------------------------------------------
            */

            'coupon' => [
                'nullable', 'string', 'max:100',
                Rule::exists('coupons', 'code')->where(function ($query) {
                    $query->where('is_active', true)
                        ->where(function ($q) {
                            $q->whereNull('start_date')->orWhere('start_date', '<=', now());
                        })
                        ->where(function ($q) {
                            $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                        });
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [

            'recipient_name.required' => 'Recipient name is required.',

            'phone.required' => 'Phone number is required.',
            'phone.regex'    => 'Please enter a valid Bangladeshi phone number.',

            'division_id.required' => 'Please select a division.',
            'division_id.exists'   => 'Invalid division selected.',

            'district_id.required' => 'Please select a district.',
            'district_id.exists'   => 'Invalid district selected.',

            'upazila_id.required' => 'Please select an upazila.',
            'upazila_id.exists'   => 'Invalid upazila selected.',

            'police_station_id.exists' => 'Invalid police station selected.',

            'address.required' => 'Street address is required.',
            'address.max'      => 'Address may not exceed 1000 characters.',

            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in'       => 'Invalid payment method.',

            'trans_payment_method.required' => 'Please select an advance payment method.',
            'trans_payment_method.in'       => 'Invalid advance payment method.',

            'bank_name.required' => 'Bank / Mobile Banking name is required.',

            'account_number.required' => 'Account number is required.',
            'account_number.regex'    => 'Invalid account number format.',

            'transaction_id.required' => 'Transaction ID is required.',
            'transaction_id.unique'   => 'Transaction ID already exists.',

            'account_holder_name.required' => 'Account holder name is required.',

            // Delivery Charge Payment messages
            'delivery_charge_amount.required' => 'Delivery charge amount is required.',
            'delivery_charge_amount.numeric'  => 'Delivery charge amount must be a number.',

            'delivery_trans_payment_method.required' => 'Please select a delivery charge payment method.',
            'delivery_trans_payment_method.in'       => 'Invalid delivery charge payment method.',

            'delivery_bank_name.required' => 'Bank / Mobile Banking name is required for delivery charge.',

            'delivery_account_number.required' => 'Account number is required for delivery charge.',
            'delivery_account_number.regex'    => 'Invalid delivery charge account number format.',

            'delivery_transaction_id.required' => 'Transaction ID is required for delivery charge.',
            'delivery_transaction_id.unique'   => 'This transaction ID already exists.',

            'delivery_account_holder_name.required' => 'Account holder name is required for delivery charge.',

            'coupon.exists' => 'Invalid or expired coupon code.',

            'remarks.max' => 'Remarks may not exceed 1000 characters.',

            'items.required' => 'Please add at least one product to the cart.',
            'items.array'    => 'Invalid items format.',
            'items.min'      => 'Please add at least one product to the cart.',

            'items.*.product_id.required' => 'Each item must have a product.',
            'items.*.product_id.exists'   => 'One or more selected products are invalid.',
            'items.*.quantity.required'   => 'Each item must have a quantity.',
            'items.*.quantity.min'        => 'Quantity must be at least 1.',
            'items.*.price.required'      => 'Each item must have a price.',
        ];
    }

    public function attributes(): array
    {
        return [

            'recipient_name' => 'recipient name',
            'phone' => 'phone number',

            'division_id' => 'division',
            'district_id' => 'district',
            'upazila_id' => 'upazila',
            'police_station_id' => 'police station',

            'address' => 'street address',

            'payment_method' => 'payment method',
            'trans_payment_method' => 'advance payment method',

            'bank_name' => 'bank name',
            'account_number' => 'account number',
            'transaction_id' => 'transaction ID',
            'account_holder_name' => 'account holder name',

            'is_delivery_charge_payment' => 'delivery charge payment',
            'delivery_charge_amount' => 'delivery charge amount',
            'delivery_trans_payment_method' => 'delivery charge payment method',
            'delivery_bank_name' => 'delivery charge bank name',
            'delivery_account_number' => 'delivery charge account number',
            'delivery_transaction_id' => 'delivery charge transaction ID',
            'delivery_account_holder_name' => 'delivery charge account holder name',

            'coupon' => 'coupon code',
        ];
    }

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
