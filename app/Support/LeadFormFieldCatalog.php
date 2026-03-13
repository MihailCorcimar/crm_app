<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeadFormFieldCatalog
{
    /**
     * @var array<string, array{label: string, type: string, enabled: bool, required: bool, max: int}>
     */
    private const SYSTEM_FIELD_DEFINITIONS = [
        'full_name' => ['label' => 'Nome completo', 'type' => 'text', 'enabled' => true, 'required' => true, 'max' => 160],
        'email' => ['label' => 'Email', 'type' => 'email', 'enabled' => true, 'required' => true, 'max' => 255],
        'phone' => ['label' => 'Telefone', 'type' => 'tel', 'enabled' => false, 'required' => false, 'max' => 40],
        'company' => ['label' => 'Empresa', 'type' => 'text', 'enabled' => false, 'required' => false, 'max' => 255],
        'message' => ['label' => 'Mensagem', 'type' => 'textarea', 'enabled' => true, 'required' => false, 'max' => 4000],
    ];

    /**
     * @var array<int, string>
     */
    private const ALLOWED_TYPES = ['text', 'email', 'tel', 'textarea', 'number', 'date', 'select', 'checkbox'];

    /**
     * @var array<int, string>
     */
    private const RESERVED_KEYS = ['website', 'source_type', 'source_url', 'captcha_answer'];

    /**
     * @return array<int, string>
     */
    public function allowedTypes(): array
    {
        return self::ALLOWED_TYPES;
    }

    /**
     * @return array<int, string>
     */
    public function systemKeys(): array
    {
        return array_keys(self::SYSTEM_FIELD_DEFINITIONS);
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, enabled: bool, required: bool, is_system: bool, options?: array<int, string>}>
     */
    public function defaults(): array
    {
        $schema = [];

        foreach (self::SYSTEM_FIELD_DEFINITIONS as $key => $definition) {
            $schema[] = [
                'key' => $key,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'enabled' => $definition['enabled'],
                'required' => $definition['required'],
                'is_system' => true,
            ];
        }

        return $schema;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, array{key: string, label: string, type: string, enabled: bool, required: bool, is_system: bool, options?: array<int, string>}>
     */
    public function normalize(array $schema): array
    {
        $incoming = collect($schema)
            ->filter(fn (mixed $field): bool => is_array($field))
            ->map(fn (mixed $field): array => is_array($field) ? $field : [])
            ->values();
        $incomingByKey = $incoming->keyBy(fn (array $field): string => $this->normalizeKey((string) ($field['key'] ?? '')));
        $normalized = [];

        foreach (self::SYSTEM_FIELD_DEFINITIONS as $key => $definition) {
            /** @var array<string, mixed> $field */
            $field = $incomingByKey->get($key, []);
            $enabled = (bool) ($field['enabled'] ?? $definition['enabled']);
            $required = $enabled ? (bool) ($field['required'] ?? $definition['required']) : false;
            $label = $this->sanitizeLabel((string) ($field['label'] ?? $definition['label']), $definition['label']);

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'type' => $definition['type'],
                'enabled' => $enabled,
                'required' => $required,
                'is_system' => true,
            ];
        }

        $existingKeys = collect($normalized)->pluck('key')->all();

        foreach ($incoming as $field) {
            $rawKey = (string) ($field['key'] ?? '');
            $key = $this->normalizeKey($rawKey);
            if ($key === '' || in_array($key, $existingKeys, true) || in_array($key, self::RESERVED_KEYS, true)) {
                continue;
            }

            if (! preg_match('/^[a-z][a-z0-9_]{2,63}$/', $key)) {
                continue;
            }

            $type = strtolower(trim((string) ($field['type'] ?? 'text')));
            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                $type = 'text';
            }

            $enabled = (bool) ($field['enabled'] ?? true);
            $required = $enabled ? (bool) ($field['required'] ?? false) : false;
            $label = $this->sanitizeLabel((string) ($field['label'] ?? ''), $this->defaultLabelFromKey($key));

            $customField = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'enabled' => $enabled,
                'required' => $required,
                'is_system' => false,
            ];

            if ($type === 'select') {
                $options = $this->normalizeOptions($field['options'] ?? []);
                if (count($options) === 0) {
                    $options = ['Opção 1'];
                }
                $customField['options'] = $options;
            }

            $normalized[] = $customField;
            $existingKeys[] = $key;
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string, enabled: bool, required: bool, is_system?: bool, options?: array<int, string>}>  $schema
     * @return array<int, array{key: string, label: string, type: string, required: bool, is_system: bool, options?: array<int, string>}>
     */
    public function enabledFields(array $schema): array
    {
        return collect($schema)
            ->filter(fn (array $field): bool => (bool) ($field['enabled'] ?? false))
            ->map(function (array $field): array {
                $mapped = [
                    'key' => (string) $field['key'],
                    'label' => (string) $field['label'],
                    'type' => (string) $field['type'],
                    'required' => (bool) ($field['required'] ?? false),
                    'is_system' => (bool) ($field['is_system'] ?? false),
                ];

                if ((string) ($field['type'] ?? '') === 'select') {
                    $mapped['options'] = $this->normalizeOptions($field['options'] ?? []);
                }

                return $mapped;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string, enabled: bool, required: bool, is_system?: bool, options?: array<int, string>}>  $schema
     * @return array<string, array<int, mixed>>
     */
    public function submissionRules(array $schema): array
    {
        $rules = [];
        $normalized = $this->normalize($schema);

        foreach ($normalized as $field) {
            $rules[(string) $field['key']] = $this->rulesForField($field);
        }

        return $rules;
    }

    /**
     * @param  array{key: string, label: string, type: string, enabled: bool, required: bool, is_system?: bool, options?: array<int, string>}  $field
     * @return array<int, mixed>
     */
    private function rulesForField(array $field): array
    {
        $enabled = (bool) ($field['enabled'] ?? false);
        if (! $enabled) {
            return ['nullable'];
        }

        $type = (string) ($field['type'] ?? 'text');
        $required = (bool) ($field['required'] ?? false);
        $requiredRule = $required ? 'required' : 'nullable';
        $max = $this->maxLengthForField($field);

        return match ($type) {
            'email' => [$requiredRule, 'email', 'max:'.$max],
            'tel' => [$requiredRule, 'string', 'max:'.$max],
            'textarea' => [$requiredRule, 'string', 'max:'.$max],
            'number' => [$requiredRule, 'numeric'],
            'date' => [$requiredRule, 'date'],
            'select' => [$requiredRule, 'string', Rule::in($this->normalizeOptions($field['options'] ?? []))],
            'checkbox' => $required ? ['required', 'accepted'] : ['nullable', 'boolean'],
            default => [$requiredRule, 'string', 'max:'.$max],
        };
    }

    /**
     * @param  array{key?: string, type?: string}  $field
     */
    private function maxLengthForField(array $field): int
    {
        $key = (string) ($field['key'] ?? '');
        if (isset(self::SYSTEM_FIELD_DEFINITIONS[$key]['max'])) {
            return (int) self::SYSTEM_FIELD_DEFINITIONS[$key]['max'];
        }

        $type = (string) ($field['type'] ?? 'text');

        return $type === 'textarea' ? 4000 : 255;
    }

    private function normalizeKey(string $key): string
    {
        $normalized = Str::of($key)
            ->lower()
            ->replace('-', '_')
            ->replace(' ', '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->toString();

        return trim($normalized, '_');
    }

    private function sanitizeLabel(string $label, string $fallback): string
    {
        $cleaned = trim($label);

        if ($cleaned === '') {
            return $fallback;
        }

        return mb_substr($cleaned, 0, 60);
    }

    private function defaultLabelFromKey(string $key): string
    {
        $label = str_replace('_', ' ', $key);
        $label = trim($label);

        return $label === '' ? 'Campo personalizado' : Str::title($label);
    }

    /**
     * @param  mixed  $options
     * @return array<int, string>
     */
    private function normalizeOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->map(fn (mixed $option): string => mb_substr(trim((string) $option), 0, 80))
            ->filter(fn (string $option): bool => $option !== '')
            ->unique()
            ->values()
            ->take(40)
            ->all();
    }
}
