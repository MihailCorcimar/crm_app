<?php

namespace App\Http\Requests\Settings;

use App\Models\CalendarAction;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarActionRequest extends FormRequest
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
        /** @var CalendarAction|null $calendarAction */
        $calendarAction = $this->route('calendarAction');
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('calendar_actions', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($calendarAction?->id),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
