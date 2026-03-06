<?php

namespace App\Services\Ai;

use App\Models\Deal;
use Illuminate\Support\Str;

class AiIntentService
{
    /**
     * @return array{intent: string, confidence: float, parameters: array<string, string|null>}
     */
    public function resolve(string $message): array
    {
        $normalized = Str::of($message)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        $dealStage = $this->extractDealStage($normalized);
        if (
            (str_contains($normalized, 'negocio') || str_contains($normalized, 'pipeline'))
            && (
                str_contains($normalized, 'volume')
                || str_contains($normalized, 'total')
                || str_contains($normalized, 'valor')
                || str_contains($normalized, 'quant')
            )
        ) {
            return [
                'intent' => 'deal_summary',
                'confidence' => 0.92,
                'parameters' => [
                    'stage' => $dealStage,
                    'name' => null,
                    'field' => null,
                ],
            ];
        }

        $field = $this->extractContactField($normalized);
        $name = $this->extractName($message, $normalized);

        if ($field !== null && $name !== null) {
            return [
                'intent' => 'contact_lookup',
                'confidence' => 0.9,
                'parameters' => [
                    'stage' => null,
                    'name' => $name,
                    'field' => $field,
                ],
            ];
        }

        return [
            'intent' => 'unsupported',
            'confidence' => 0.3,
            'parameters' => [
                'stage' => null,
                'name' => null,
                'field' => null,
            ],
        ];
    }

    private function extractDealStage(string $normalized): ?string
    {
        if (str_contains($normalized, 'lead')) {
            return Deal::STAGE_LEAD;
        }

        if (str_contains($normalized, 'proposta')) {
            return Deal::STAGE_PROPOSAL;
        }

        if (str_contains($normalized, 'negociacao') || str_contains($normalized, 'negociar')) {
            return Deal::STAGE_NEGOTIATION;
        }

        if (str_contains($normalized, 'follow up') || str_contains($normalized, 'followup')) {
            return Deal::STAGE_FOLLOW_UP;
        }

        if (str_contains($normalized, 'ganho') || str_contains($normalized, 'won')) {
            return Deal::STAGE_WON;
        }

        if (str_contains($normalized, 'perdido') || str_contains($normalized, 'lost')) {
            return Deal::STAGE_LOST;
        }

        return null;
    }

    private function extractContactField(string $normalized): ?string
    {
        if (str_contains($normalized, 'telemovel') || str_contains($normalized, 'mobile') || str_contains($normalized, 'celular')) {
            return 'mobile';
        }

        if (str_contains($normalized, 'telefone') || str_contains($normalized, 'phone')) {
            return 'phone';
        }

        if (str_contains($normalized, 'email') || str_contains($normalized, 'e-mail')) {
            return 'email';
        }

        return null;
    }

    private function extractName(string $originalMessage, string $normalized): ?string
    {
        if (preg_match('/["\']([^"\']{2,})["\']/', $originalMessage, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('/(?:do|da|de)\s+(.+)$/', $normalized, $matches) === 1) {
            $raw = trim((string) $matches[1], " \t\n\r\0\x0B?.!,;");

            if ($raw !== '') {
                return $raw;
            }
        }

        return null;
    }
}
