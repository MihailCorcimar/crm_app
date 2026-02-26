<?php

namespace App\Http\Requests;

use App\Models\CalendarEvent;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var CalendarEvent|null $calendarEvent */
        $calendarEvent = $this->route('calendar');

        if ($calendarEvent !== null) {
            return $this->user()?->can('update', $calendarEvent) ?? false;
        }

        return $this->user()?->can('create', CalendarEvent::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::id($this) ?? 0;

        return [
            'event_date' => ['required', 'date'],
            'event_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'share' => ['nullable', 'string', 'max:255'],
            'knowledge' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', Rule::exists('entities', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 'active')),
                Rule::exists('tenant_user', 'user_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'calendar_type_id' => ['nullable', Rule::exists('calendar_types', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'calendar_action_id' => ['nullable', Rule::exists('calendar_actions', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
