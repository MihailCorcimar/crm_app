<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProposalRequest extends FormRequest
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
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'proposal_date' => ['nullable', 'date'],
            'valid_until' => ['required', 'date'],
            'customer_id' => ['required', 'integer', Rule::exists('entities', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'status' => ['required', Rule::in(['draft', 'closed'])],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'lines.*.supplier_id' => ['nullable', 'integer', Rule::exists('entities', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.sale_price' => ['required', 'numeric', 'min:0'],
            'lines.*.cost_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
