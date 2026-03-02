<?php

namespace App\Http\Requests\Settings;

use App\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DealStageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stages' => ['required', 'array', 'size:'.count(Deal::stages())],
            'stages.*.value' => ['required', 'string', Rule::in(Deal::stages())],
            'stages.*.label' => ['required', 'string', 'max:50'],
            'stages.*.order' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $stages = $this->input('stages', []);
            if (! is_array($stages)) {
                return;
            }

            $values = collect($stages)
                ->pluck('value')
                ->filter(fn ($value): bool => is_string($value) && $value !== '')
                ->values();

            $orders = collect($stages)
                ->pluck('order')
                ->filter(fn ($order): bool => is_numeric($order))
                ->map(fn ($order): int => (int) $order)
                ->values();

            if ($values->unique()->count() !== $values->count()) {
                $validator->errors()->add('stages', 'As etapas não podem repetir o mesmo identificador.');
            }

            if ($orders->unique()->count() !== $orders->count()) {
                $validator->errors()->add('stages', 'A ordem das etapas deve ser única.');
            }

            $required = collect(Deal::stages())->sort()->values();
            if ($values->sort()->values()->all() !== $required->all()) {
                $validator->errors()->add('stages', 'Tens de configurar todas as etapas base do pipeline.');
            }
        });
    }
}
