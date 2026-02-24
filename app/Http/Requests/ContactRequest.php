<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'entity_id' => ['required', 'integer', Rule::exists('entities', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'role_id' => ['required', 'integer', Rule::exists('contact_roles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'phone' => ['nullable', 'string', 'regex:/^(?:\+351\s?)?2\d{8}$/'],
            'mobile' => ['nullable', 'string', 'regex:/^(?:\+351\s?)?9\d{8}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'gdpr_consent' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
