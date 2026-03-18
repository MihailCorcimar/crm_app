<?php

namespace App\Http\Requests;

use App\Models\LeadForm;
use App\Support\DealStageService;
use App\Support\LeadFormFieldCatalog;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeadFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var LeadForm|null $leadForm */
        $leadForm = $this->route('leadForm');

        if ($leadForm !== null) {
            return $this->user()?->can('update', $leadForm) ?? false;
        }

        return $this->user()?->can('create', LeadForm::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug', ''));
        if ($slug === '') {
            $slug = Str::slug((string) $this->input('name', ''));
        }

        $this->merge([
            'slug' => $slug,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var LeadForm|null $leadForm */
        $leadForm = $this->route('leadForm');
        $tenantId = TenantContext::id($this) ?? (int) ($this->user()?->current_tenant_id ?? 0);
        $fieldCatalog = app(LeadFormFieldCatalog::class);
        $allowedTypes = $fieldCatalog->allowedTypes();
        $allowedStages = app(DealStageService::class)->valuesForTenant($tenantId);

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:140',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('lead_forms', 'slug')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($leadForm?->id),
            ],
            'status' => ['required', Rule::in([LeadForm::STATUS_ACTIVE, LeadForm::STATUS_INACTIVE])],
            'requires_captcha' => ['required', 'boolean'],
            'confirmation_message' => ['required', 'string', 'max:1000'],
            'field_schema' => ['required', 'array', 'min:1'],
            'field_schema.*.key' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]{2,63}$/',
                'distinct',
                Rule::notIn(['website', 'source_type', 'source_url', 'captcha_answer']),
            ],
            'field_schema.*.label' => ['required', 'string', 'max:60'],
            'field_schema.*.type' => ['required', Rule::in($allowedTypes)],
            'field_schema.*.enabled' => ['required', 'boolean'],
            'field_schema.*.required' => ['required', 'boolean'],
            'field_schema.*.options' => ['sometimes', 'array'],
            'field_schema.*.options.*' => ['nullable', 'string', 'max:80'],
            'conversion_settings' => ['required', 'array'],
            'conversion_settings.create_deal' => ['required', 'boolean'],
            'conversion_settings.entity_name_field_key' => ['nullable', 'string', 'max:64'],
            'conversion_settings.deal_title_field_key' => ['nullable', 'string', 'max:64'],
            'conversion_settings.deal_title_template' => ['nullable', 'string', 'max:180'],
            'conversion_settings.deal_value_field_key' => ['nullable', 'string', 'max:64'],
            'conversion_settings.deal_stage' => ['required', 'string', Rule::in($allowedStages)],
            'conversion_settings.deal_owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'conversion_settings.deal_probability' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $tenantId = TenantContext::id($this) ?? (int) ($this->user()?->current_tenant_id ?? 0);

        $validator->after(function (Validator $validator): void {
            /** @var array<int, array<string, mixed>> $schema */
            $schema = $this->input('field_schema', []);

            $enabledCount = collect($schema)->filter(
                fn (array $field): bool => (bool) ($field['enabled'] ?? false)
            )->count();

            if ($enabledCount === 0) {
                $validator->errors()->add('field_schema', 'Tem de ativar pelo menos um campo.');
            }

            $invalidRequired = collect($schema)->first(
                fn (array $field): bool => (bool) ($field['required'] ?? false) && ! (bool) ($field['enabled'] ?? false)
            );

            if ($invalidRequired !== null) {
                $validator->errors()->add('field_schema', 'Um campo marcado como obrigatorio tem de estar ativo.');
            }

            foreach ($schema as $index => $field) {
                $type = strtolower(trim((string) ($field['type'] ?? '')));
                $options = $field['options'] ?? [];
                $enabled = (bool) ($field['enabled'] ?? false);

                if ($type === 'select' && $enabled) {
                    $validOptions = collect(is_array($options) ? $options : [])
                        ->map(fn (mixed $option): string => trim((string) $option))
                        ->filter(fn (string $option): bool => $option !== '')
                        ->unique()
                        ->values();

                    if ($validOptions->count() < 1) {
                        $validator->errors()->add("field_schema.{$index}.options", 'Campos do tipo select precisam de pelo menos uma opcao.');
                    }
                }
            }

            $schemaKeys = collect($schema)
                ->map(fn (array $field): string => (string) ($field['key'] ?? ''))
                ->filter(fn (string $key): bool => $key !== '')
                ->values();

            /** @var array<string, mixed> $conversion */
            $conversion = is_array($this->input('conversion_settings')) ? $this->input('conversion_settings') : [];
            $mappedKeys = collect([
                $conversion['entity_name_field_key'] ?? null,
                $conversion['deal_title_field_key'] ?? null,
                $conversion['deal_value_field_key'] ?? null,
            ])
                ->filter(fn (mixed $key): bool => is_string($key) && trim($key) !== '')
                ->map(fn (mixed $key): string => trim((string) $key))
                ->values();

            foreach ($mappedKeys as $mappedKey) {
                if (! $schemaKeys->contains($mappedKey)) {
                    $validator->errors()->add('conversion_settings', "O campo mapeado '{$mappedKey}' nao existe no formulario.");
                }
            }

            $ownerId = $conversion['deal_owner_id'] ?? null;
            if (is_numeric($ownerId) && (int) $ownerId > 0 && ! DB::table('tenant_user')
                ->where('tenant_id', $tenantId)
                ->where('user_id', (int) $ownerId)
                ->exists()
            ) {
                $validator->errors()->add('conversion_settings.deal_owner_id', 'O responsavel selecionado nao pertence ao tenant ativo.');
            }
        });
    }
}

