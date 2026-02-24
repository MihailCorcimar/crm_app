<?php

namespace App\Http\Requests\Settings;

use App\Models\ContactRole;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRoleRequest extends FormRequest
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
        /** @var ContactRole|null $contactRole */
        $contactRole = $this->route('contactRole');
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contact_roles', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($contactRole?->id),
            ],
        ];
    }
}
