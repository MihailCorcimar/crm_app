<?php

namespace App\Support;

use App\Models\Deal;
use App\Models\TenantSetting;

class DealStageService
{
    /**
     * @return array<int, array{value: string, label: string, order: int}>
     */
    public function defaults(): array
    {
        return [
            ['value' => Deal::STAGE_LEAD, 'label' => 'Lead', 'order' => 10],
            ['value' => Deal::STAGE_PROPOSAL, 'label' => 'Proposta', 'order' => 20],
            ['value' => Deal::STAGE_NEGOTIATION, 'label' => 'Negociação', 'order' => 30],
            ['value' => Deal::STAGE_FOLLOW_UP, 'label' => 'Follow Up', 'order' => 40],
            ['value' => Deal::STAGE_WON, 'label' => 'Ganho', 'order' => 50],
            ['value' => Deal::STAGE_LOST, 'label' => 'Perdido', 'order' => 60],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, order: int}>
     */
    public function forTenant(?int $tenantId): array
    {
        $defaults = $this->defaults();

        if (! is_int($tenantId) || $tenantId <= 0) {
            return $defaults;
        }

        /** @var array<string, mixed>|null $raw */
        $raw = TenantSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->value('settings');

        $configured = is_array($raw['deal_stages'] ?? null)
            ? $raw['deal_stages']
            : [];

        /** @var array<string, array{value: string, label: string, order: int}> $defaultsByValue */
        $defaultsByValue = collect($defaults)
            ->keyBy('value')
            ->all();

        $merged = collect($configured)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item) use ($defaultsByValue): ?array {
                $value = isset($item['value']) ? (string) $item['value'] : '';
                if (! isset($defaultsByValue[$value])) {
                    return null;
                }

                $fallback = $defaultsByValue[$value];
                $label = trim((string) ($item['label'] ?? ''));
                $order = is_numeric($item['order'] ?? null) ? (int) $item['order'] : $fallback['order'];

                return [
                    'value' => $value,
                    'label' => $label !== '' ? mb_substr($label, 0, 50) : $fallback['label'],
                    'order' => $order > 0 ? $order : $fallback['order'],
                ];
            })
            ->filter(fn ($item): bool => is_array($item))
            ->values()
            ->all();

        /** @var array<string, array{value: string, label: string, order: int}> $mergedByValue */
        $mergedByValue = collect($merged)->keyBy('value')->all();

        $full = collect($defaults)
            ->map(fn (array $default): array => $mergedByValue[$default['value']] ?? $default)
            ->sortBy('order')
            ->values()
            ->all();

        return $full;
    }

    /**
     * @param  array<int, array{value: string, label: string, order: int}>  $stages
     * @return array<int, array{value: string, label: string, order: int}>
     */
    public function updateForTenant(?int $tenantId, array $stages): array
    {
        if (! is_int($tenantId) || $tenantId <= 0) {
            return $this->defaults();
        }

        $normalized = collect($stages)
            ->map(fn (array $stage): array => [
                'value' => (string) $stage['value'],
                'label' => mb_substr(trim((string) $stage['label']), 0, 50),
                'order' => max(1, (int) $stage['order']),
            ])
            ->sortBy('order')
            ->values()
            ->all();

        /** @var array<string, mixed> $current */
        $current = TenantSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->value('settings') ?? [];

        $payload = array_merge($current, [
            'deal_stages' => $normalized,
        ]);

        $setting = TenantSetting::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['settings' => $payload]
        );

        $setting->forceFill(['settings' => $payload])->save();

        return $this->forTenant($tenantId);
    }

    /**
     * @return list<string>
     */
    public function valuesForTenant(?int $tenantId): array
    {
        return collect($this->forTenant($tenantId))
            ->pluck('value')
            ->values()
            ->all();
    }
}
