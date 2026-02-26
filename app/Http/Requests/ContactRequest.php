<?php

namespace App\Http\Requests;

use App\Models\Contact;
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
        /** @var Contact|null $contact */
        $contact = $this->route('contact');

        if ($contact !== null) {
            return $this->user()?->can('update', $contact) ?? false;
        }

        return $this->user()?->can('create', Contact::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Contact|null $contact */
        $contact = $this->route('contact');
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'entity_id' => ['required', 'integer', Rule::exists('entities', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'role_id' => ['required', 'integer', Rule::exists('contact_roles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'phone' => ['nullable', 'string', 'regex:/^(?:\+351\s?)?2\d{8}$/'],
            'mobile' => ['nullable', 'string', 'regex:/^(?:\+351\s?)?9\d{8}$/'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('contacts', 'email')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($contact?->id),
            ],
            'gdpr_consent' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
