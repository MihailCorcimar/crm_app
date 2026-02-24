<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Tenant::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('tenants', 'slug'),
            ],
            'settings' => ['nullable', 'array'],
            'settings.brand_name' => ['nullable', 'string', 'max:120'],
            'settings.brand_primary_color' => ['nullable', 'string', 'regex:/^#[A-Fa-f0-9]{6}$/'],
            'settings.default_user_role' => ['nullable', Rule::in(['member', 'manager'])],
            'settings.allow_member_invites' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug'));
        $brandName = trim((string) $this->input('settings.brand_name'));
        $brandPrimaryColor = strtoupper(trim((string) $this->input('settings.brand_primary_color')));
        $defaultUserRole = strtolower(trim((string) $this->input('settings.default_user_role')));

        $this->merge([
            'slug' => $slug !== '' ? Str::slug($slug) : Str::slug((string) $this->input('name')),
            'settings' => [
                'brand_name' => $brandName !== '' ? $brandName : null,
                'brand_primary_color' => $brandPrimaryColor !== '' ? $brandPrimaryColor : null,
                'default_user_role' => $defaultUserRole !== '' ? $defaultUserRole : 'member',
                'allow_member_invites' => $this->boolean('settings.allow_member_invites'),
            ],
        ]);
    }
}
