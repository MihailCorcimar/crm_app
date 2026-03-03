<?php

namespace App\Http\Requests;

use App\Models\Deal;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DealQuickActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Deal|null $deal */
        $deal = $this->route('deal');

        return $deal !== null
            ? ($this->user()?->can('update', $deal) ?? false)
            : false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'activity_type' => ['required', Rule::in(['call', 'task', 'meeting', 'note'])],
            'activity_at' => ['required', 'date'],
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
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
