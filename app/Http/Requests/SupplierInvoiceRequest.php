<?php

namespace App\Http\Requests;

use App\Models\SupplierOrder;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierInvoiceRequest extends FormRequest
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
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('entities', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->whereIn('type', ['supplier', 'both'])),
            ],
            'supplier_order_id' => ['nullable', 'integer', Rule::exists('supplier_orders', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'total' => ['required', 'numeric', 'min:0'],
            'document' => ['nullable', 'file', 'max:10240'],
            'payment_proof' => ['nullable', 'file', 'max:10240'],
            'status' => ['required', Rule::in(['pending_payment', 'paid'])],
            'send_payment_proof_email' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $supplierOrderId = $this->integer('supplier_order_id');
            $supplierId = $this->integer('supplier_id');

            if ($supplierOrderId <= 0 || $supplierId <= 0) {
                return;
            }

            $belongsToSupplier = SupplierOrder::query()
                ->whereKey($supplierOrderId)
                ->where('tenant_id', TenantContext::id($this) ?? 0)
                ->where('supplier_id', $supplierId)
                ->exists();

            if (! $belongsToSupplier) {
                $validator->errors()->add('supplier_order_id', 'A encomenda selecionada nao pertence ao fornecedor.');
            }
        });
    }
}
