<?php

namespace App\Http\Requests;

use App\Models\DealAutomationRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealAutomationRuleRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_PLACEHOLDERS = [
        '{deal_title}',
        '{entity_name}',
        '{owner_name}',
        '{days_without_activity}',
    ];

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
            'activity_title_template' => ['required', 'string', 'max:180', $this->placeholderRule()],
            'activity_description_template' => ['nullable', 'string', 'max:2000', $this->placeholderRule()],
            'notify_internal' => ['required', 'boolean'],
            'notification_message' => ['nullable', 'string', 'max:255', $this->placeholderRule()],
            'status' => ['required', Rule::in([DealAutomationRule::STATUS_ACTIVE, DealAutomationRule::STATUS_PAUSED])],
        ];
    }

    private function placeholderRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            preg_match_all('/\{[a-z_]+\}/', $value, $matches);
            $found = array_values(array_unique($matches[0] ?? []));

            $invalid = array_values(array_diff($found, self::ALLOWED_PLACEHOLDERS));
            if ($invalid !== []) {
                $fail(sprintf(
                    'Variavel nao suportada em %s: %s. Permitidas: %s',
                    $attribute,
                    implode(', ', $invalid),
                    implode(', ', self::ALLOWED_PLACEHOLDERS),
                ));
            }
        };
    }
}
