<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SliderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tag'           => 'nullable|string|max:100',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',

            // Only one image
            'image'         => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',

            'button_text'   => 'required|string|max:100',
            'button_link'   => 'nullable|url|max:255',

            'status'        => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'        => 'Title is required.',
            'image.required'        => 'Please select an image.',
            'image.image'           => 'The uploaded file must be an image.',
            'image.mimes'           => 'Only JPG, JPEG, PNG and WEBP images are allowed.',
            'image.max'             => 'Image size must not exceed 2 MB.',
            'button_text.required'  => 'Button text is required.',
            'button_link.url'       => 'Please enter a valid URL.',
            'status.required'       => 'Status is required.',
            'status.boolean'        => 'Status must be active or inactive.',
        ];
    }
}
