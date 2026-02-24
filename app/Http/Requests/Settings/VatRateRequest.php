<?php

namespace App\Http\Requests\Settings;

use App\Models\VatRate;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VatRateRequest extends FormRequest
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
        /** @var VatRate|null $vatRate */
        $vatRate = $this->route('vatRate');
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vat_rates', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($vatRate?->id),
            ],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
