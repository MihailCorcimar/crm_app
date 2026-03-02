<?php

namespace App\Http\Requests;

use App\Models\CalendarEvent;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
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
            'calendar_type_id' => ['nullable', Rule::exists('calendar_types', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'calendar_action_id' => ['nullable', Rule::exists('calendar_actions', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'eventable_type' => ['nullable', Rule::in(['entity', 'person', 'deal'])],
            'eventable_id' => ['nullable', 'integer'],
            'attendee_entity_ids' => ['nullable', 'array'],
            'attendee_entity_ids.*' => ['integer', Rule::exists('entities', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'attendee_person_ids' => ['nullable', 'array'],
            'attendee_person_ids.*' => ['integer', Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'attendee_deal_ids' => ['nullable', 'array'],
            'attendee_deal_ids.*' => ['integer', Rule::exists('deals', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenantId = TenantContext::id($this) ?? 0;
            $eventableType = $this->input('eventable_type');
            $eventableId = $this->input('eventable_id');

            if (($eventableType === null) !== ($eventableId === null)) {
                $validator->errors()->add(
                    'eventable_id',
                    'Quando defines associação principal tens de indicar tipo e registo.'
                );

                return;
            }

            if (! is_string($eventableType) || ! is_numeric($eventableId)) {
                return;
            }

            $id = (int) $eventableId;
            $exists = match ($eventableType) {
                'entity' => DB::table('entities')
                    ->where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->exists(),
                'person' => DB::table('contacts')
                    ->where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->exists(),
                'deal' => DB::table('deals')
                    ->where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->exists(),
                default => false,
            };

            if (! $exists) {
                $validator->errors()->add('eventable_id', 'O registo associado não é válido neste tenant.');
            }
        });
    }
}
