<?php

namespace App\Http\Requests;

use App\Models\Entity;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $rawVat = $this->input('vat', $this->input('tax_id'));

        if (! is_string($rawVat)) {
            return;
        }

        $this->merge([
            'vat' => preg_replace('/\D+/', '', $rawVat) ?? $rawVat,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Entity|null $entity */
        $entity = $this->route('entity');

        if ($entity !== null) {
            return $this->user()?->can('update', $entity) ?? false;
        }

        return $this->user()?->can('create', Entity::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Entity|null $entity */
        $entity = $this->route('entity');
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'type' => ['required', Rule::in(['customer', 'supplier', 'both'])],
            'vat' => [
                'required',
                'string',
                'regex:/^\d{9}$/',
                Rule::unique('entities', 'vat')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($entity?->id),
                Rule::unique('entities', 'tax_id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($entity?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^(?:\+351\s?)?2\d{8}$/'],
            'mobile' => ['nullable', 'string', 'regex:/^(?:\+351\s?)?9\d{8}$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'regex:/^\d{4}-\d{3}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'notes' => ['nullable', 'string'],
            'gdpr_consent' => ['nullable', 'boolean'],
        ];
    }
}
