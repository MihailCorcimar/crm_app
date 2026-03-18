<?php

namespace App\Support;

use App\Models\Deal;

class LeadFormConversionSettings
{
    public function __construct(private readonly DealStageService $dealStageService)
    {
    }

    /**
     * @return array{
     *   create_deal: bool,
     *   entity_name_field_key: string|null,
     *   deal_title_field_key: string|null,
     *   deal_title_template: string,
     *   deal_value_field_key: string|null,
     *   deal_stage: string,
     *   deal_owner_id: int|null,
     *   deal_probability: int
     * }
     */
    public function defaults(?int $tenantId): array
    {
        $stageValues = $this->dealStageService->valuesForTenant($tenantId);

        return [
            'create_deal' => true,
            'entity_name_field_key' => 'company',
            'deal_title_field_key' => null,
            'deal_title_template' => 'Lead inbound - {entity_name}',
            'deal_value_field_key' => null,
            'deal_stage' => in_array(Deal::STAGE_LEAD, $stageValues, true) ? Deal::STAGE_LEAD : ($stageValues[0] ?? Deal::STAGE_LEAD),
            'deal_owner_id' => null,
            'deal_probability' => 20,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array{
     *   create_deal: bool,
     *   entity_name_field_key: string|null,
     *   deal_title_field_key: string|null,
     *   deal_title_template: string,
     *   deal_value_field_key: string|null,
     *   deal_stage: string,
     *   deal_owner_id: int|null,
     *   deal_probability: int
     * }
     */
    public function normalize(?array $raw, ?int $tenantId): array
    {
        $defaults = $this->defaults($tenantId);
        $input = is_array($raw) ? $raw : [];
        $stageValues = $this->dealStageService->valuesForTenant($tenantId);

        $dealStage = trim((string) ($input['deal_stage'] ?? $defaults['deal_stage']));
        if (! in_array($dealStage, $stageValues, true)) {
            $dealStage = $defaults['deal_stage'];
        }

        $dealProbability = is_numeric($input['deal_probability'] ?? null)
            ? (int) $input['deal_probability']
            : $defaults['deal_probability'];
        $dealProbability = max(0, min(100, $dealProbability));

        return [
            'create_deal' => (bool) ($input['create_deal'] ?? $defaults['create_deal']),
            'entity_name_field_key' => $this->nullableKey($input['entity_name_field_key'] ?? $defaults['entity_name_field_key']),
            'deal_title_field_key' => $this->nullableKey($input['deal_title_field_key'] ?? $defaults['deal_title_field_key']),
            'deal_title_template' => $this->stringOrDefault($input['deal_title_template'] ?? null, $defaults['deal_title_template'], 180),
            'deal_value_field_key' => $this->nullableKey($input['deal_value_field_key'] ?? $defaults['deal_value_field_key']),
            'deal_stage' => $dealStage,
            'deal_owner_id' => is_numeric($input['deal_owner_id'] ?? null) ? (int) $input['deal_owner_id'] : null,
            'deal_probability' => $dealProbability,
        ];
    }

    private function nullableKey(mixed $value): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, 64);
    }

    private function stringOrDefault(mixed $value, string $default, int $max): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return $default;
        }

        return mb_substr($text, 0, $max);
    }
}

