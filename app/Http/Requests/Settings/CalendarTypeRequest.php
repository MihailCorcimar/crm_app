<?php

namespace App\Http\Requests\Settings;

use App\Models\CalendarType;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarTypeRequest extends FormRequest
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
        /** @var CalendarType|null $calendarType */
        $calendarType = $this->route('calendarType');
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('calendar_types', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($calendarType?->id),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
