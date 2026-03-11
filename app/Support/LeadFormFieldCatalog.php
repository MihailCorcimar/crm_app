<?php

namespace App\Support;

class LeadFormFieldCatalog
{
    /**
     * @var array<string, array{label: string, type: string, enabled: bool, required: bool, max: int}>
     */
    private const FIELD_DEFINITIONS = [
        'full_name' => ['label' => 'Nome completo', 'type' => 'text', 'enabled' => true, 'required' => true, 'max' => 160],
        'email' => ['label' => 'Email', 'type' => 'email', 'enabled' => true, 'required' => true, 'max' => 255],
        'phone' => ['label' => 'Telefone', 'type' => 'tel', 'enabled' => false, 'required' => false, 'max' => 40],
        'company' => ['label' => 'Empresa', 'type' => 'text', 'enabled' => false, 'required' => false, 'max' => 255],
        'message' => ['label' => 'Mensagem', 'type' => 'textarea', 'enabled' => true, 'required' => false, 'max' => 4000],
    ];

    /**
     * @return array<int, array{key: string, label: string, type: string, enabled: bool, required: bool}>
     */
    public function defaults(): array
    {
        $schema = [];

        foreach (self::FIELD_DEFINITIONS as $key => $definition) {
            $schema[] = [
                'key' => $key,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'enabled' => $definition['enabled'],
                'required' => $definition['required'],
            ];
        }

        return $schema;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, array{key: string, label: string, type: string, enabled: bool, required: bool}>
     */
    public function normalize(array $schema): array
    {
        $incoming = collect($schema)->keyBy(
            fn (array $field): string => (string) ($field['key'] ?? '')
        );

        $normalized = [];

        foreach (self::FIELD_DEFINITIONS as $key => $definition) {
            /** @var array<string, mixed> $field */
            $field = $incoming->get($key, []);
            $enabled = (bool) ($field['enabled'] ?? $definition['enabled']);
            $required = $enabled ? (bool) ($field['required'] ?? $definition['required']) : false;

            $normalized[] = [
                'key' => $key,
                'label' => trim((string) ($field['label'] ?? $definition['label'])),
                'type' => $definition['type'],
                'enabled' => $enabled,
                'required' => $required,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string, enabled: bool, required: bool}>  $schema
     * @return array<int, array{key: string, label: string, type: string, required: bool}>
     */
    public function enabledFields(array $schema): array
    {
        return collect($schema)
            ->filter(fn (array $field): bool => (bool) ($field['enabled'] ?? false))
            ->map(fn (array $field): array => [
                'key' => (string) $field['key'],
                'label' => (string) $field['label'],
                'type' => (string) $field['type'],
                'required' => (bool) ($field['required'] ?? false),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string, enabled: bool, required: bool}>  $schema
     * @return array<string, array<int, mixed>>
     */
    public function submissionRules(array $schema): array
    {
        $rules = [];
        $normalized = $this->normalize($schema);
        $enabledFields = collect($this->enabledFields($normalized))->keyBy('key');

        foreach (self::FIELD_DEFINITIONS as $key => $definition) {
            $enabledField = $enabledFields->get($key);

            if ($enabledField === null) {
                $rules[$key] = ['nullable'];

                continue;
            }

            $requiredRule = (bool) ($enabledField['required'] ?? false) ? 'required' : 'nullable';
            $fieldRules = [$requiredRule, 'string', 'max:'.$definition['max']];

            if ($key === 'email') {
                $fieldRules = [$requiredRule, 'email', 'max:'.$definition['max']];
            }

            $rules[$key] = $fieldRules;
        }

        return $rules;
    }
}

