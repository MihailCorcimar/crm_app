<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class CompanySettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'regex:/^[0-9]{4}-[0-9]{3}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
