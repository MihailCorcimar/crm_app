<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantMemberStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');

        return $tenant !== null && (bool) $this->user()?->can('authorizeMember', $tenant);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Tenant|null $tenant */
        $tenant = $this->route('tenant');

        return [
            'email' => [
                'required',
                'email',
                Rule::exists('users', 'email'),
            ],
            'member_user_id' => [
                'required',
                Rule::exists('users', 'id'),
                Rule::unique('tenant_user', 'user_id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant?->id)),
            ],
            'can_create_tenants' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = strtolower(trim((string) $this->input('email')));

        $this->merge([
            'email' => $email,
            'member_user_id' => User::query()->where('email', $email)->value('id'),
            'can_create_tenants' => $this->boolean('can_create_tenants'),
        ]);
    }
}
