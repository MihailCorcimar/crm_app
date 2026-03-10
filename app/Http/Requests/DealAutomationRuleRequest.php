<?php

namespace App\Http\Requests;

use App\Models\DealAutomationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealAutomationRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var DealAutomationRule|null $rule */
        $rule = $this->route('rule');

        if ($rule !== null) {
            return $this->user()?->can('update', $rule) ?? false;
        }

        return $this->user()?->can('create', DealAutomationRule::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'inactivity_days' => ['required', 'integer', 'min:1', 'max:180'],
            'activity_type' => ['required', Rule::in(['call', 'task', 'meeting', 'note'])],
            'activity_due_in_days' => ['required', 'integer', 'min:0', 'max:60'],
            'activity_priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'activity_title_template' => ['required', 'string', 'max:180'],
            'activity_description_template' => ['nullable', 'string', 'max:2000'],
            'notify_internal' => ['required', 'boolean'],
            'notification_message' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([DealAutomationRule::STATUS_ACTIVE, DealAutomationRule::STATUS_PAUSED])],
        ];
    }
}
