<?php

namespace App\Http\Requests;

use App\Models\Deal;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DealRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Deal|null $deal */
        $deal = $this->route('deal');

        if ($deal !== null) {
            return $this->user()?->can('update', $deal) ?? false;
        }

        return $this->user()?->can('create', Deal::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'entity_id' => [
                'nullable',
                'integer',
                Rule::exists('entities', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0'],
            'stage' => ['required', Rule::in(Deal::stages())],
            'probability' => ['required', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($tenantId): void {
                    $query->whereExists(function ($subQuery) use ($tenantId): void {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from('tenant_user')
                            ->whereColumn('tenant_user.user_id', 'users.id')
                            ->where('tenant_user.tenant_id', $tenantId);
                    });
                }),
            ],
        ];
    }
}
