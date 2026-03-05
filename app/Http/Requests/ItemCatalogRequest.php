<?php

namespace App\Http\Requests;

use App\Models\Item;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemCatalogRequest extends FormRequest
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
        /** @var Item|null $item */
        $item = $this->route('item');
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'reference' => [
                'required',
                'string',
                'max:255',
                Rule::unique('items', 'reference')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($item?->id),
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('items', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($item?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'vat' => ['required', 'numeric', 'min:0', 'max:100'],
            'vat_rate_id' => [
                'nullable',
                'integer',
                Rule::exists('vat_rates', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}

