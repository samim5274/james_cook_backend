<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'sku' => [
                'nullable',
                'string',
                'max:100',
                'unique:products,sku',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:255',
                'unique:products,slug',
            ],


            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            'brand' => [
                'required',
                'integer',
                'exists:brands,id',
            ],

            'category' => [
                'required',
                'integer',
                'exists:product_categories,id',
            ],

            'subcategory' => [
                'required',
                'integer',
                'exists:product_sub_categories,id',
            ],


            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            'purchase_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'discount' => [
                'nullable',
                'numeric',
                'min:0',
            ],


            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            // 'stock_quantity' => [
            //     'required',
            //     'integer',
            //     'min:0',
            // ],

            'min_stock' => [
                'nullable',
                'integer',
                'min:0',
            ],


            /*
            |--------------------------------------------------------------------------
            | Content
            |--------------------------------------------------------------------------
            */

            'summary' => [
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:10000',
            ],


            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            'is_active' => [
                'nullable',
                'boolean',
            ],


            /*
            |--------------------------------------------------------------------------
            | Point System
            |--------------------------------------------------------------------------
            */

            'point' => [
                'required',
                'integer',
                'min:0',
            ],


            /*
            |--------------------------------------------------------------------------
            | Images
            |--------------------------------------------------------------------------
            */

            'images' => [
                'nullable',
                'array',
            ],

            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,gif,webp',
                'max:2048',
            ],
        ];
    }


    public function messages(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            'name.required' => 'Product name is required.',
            'name.string' => 'Product name must be a valid text.',
            'name.max' => 'Product name cannot exceed 255 characters.',

            'sku.string' => 'SKU must be a valid text.',
            'sku.max' => 'SKU cannot exceed 100 characters.',
            'sku.unique' => 'This SKU already exists.',

            'slug.string' => 'Slug must be a valid text.',
            'slug.max' => 'Slug cannot exceed 255 characters.',
            'slug.unique' => 'This slug already exists.',


            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            'brand.required' => 'Please select a brand.',
            'brand.integer' => 'Invalid brand selected.',
            'brand.exists' => 'Selected brand does not exist.',

            'category.required' => 'Please select a category.',
            'category.integer' => 'Invalid category selected.',
            'category.exists' => 'Selected category does not exist.',

            'subcategory.required' => 'Please select a subcategory.',
            'subcategory.integer' => 'Invalid subcategory selected.',
            'subcategory.exists' => 'Selected subcategory does not exist.',


            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            'purchase_price.required' => 'Purchase price is required.',
            'purchase_price.numeric' => 'Purchase price must be a number.',
            'purchase_price.min' => 'Purchase price cannot be negative.',

            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a number.',
            'price.min' => 'Price cannot be negative.',

            'discount.numeric' => 'Discount must be a number.',
            'discount.min' => 'Discount cannot be negative.',


            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            // 'stock_quantity.required' => 'Stock quantity is required.',
            // 'stock_quantity.integer' => 'Stock quantity must be a whole number.',
            // 'stock_quantity.min' => 'Stock quantity cannot be negative.',

            'min_stock.integer' => 'Minimum stock must be a whole number.',
            'min_stock.min' => 'Minimum stock cannot be negative.',


            /*
            |--------------------------------------------------------------------------
            | Content
            |--------------------------------------------------------------------------
            */

            'summary.string' => 'Summary must be a valid text.',
            'summary.max' => 'Summary cannot exceed 255 characters.',

            'description.string' => 'Description must be a valid text.',
            'description.max' => 'Description cannot exceed 10000 characters.',


            /*
            |--------------------------------------------------------------------------
            | Point
            |--------------------------------------------------------------------------
            */

            'point.required' => 'Product point is required.',
            'point.integer' => 'Product point must be a whole number.',
            'point.min' => 'Product point cannot be negative.',


            /*
            |--------------------------------------------------------------------------
            | Images
            |--------------------------------------------------------------------------
            */

            'images.array' => 'Images must be uploaded as an array.',

            'images.*.file' => 'Each uploaded item must be a valid file.',
            'images.*.image' => 'Each uploaded file must be an image.',
            'images.*.mimes' => 'Allowed image types: jpg, jpeg, png, gif, webp.',
            'images.*.max' => 'Each image must be under 2MB.',
        ];
    }
}
