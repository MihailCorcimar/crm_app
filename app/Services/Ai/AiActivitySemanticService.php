<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

class AiActivitySemanticService
{
    /**
     * @param  list<string>  $activityNotes
     * @return array{needs_follow_up: bool, reason: string}
     */
    public function analyze(array $activityNotes): array
    {
        $normalized = collect($activityNotes)
            ->map(fn (string $text): string => trim($text))
            ->filter(fn (string $text): bool => $text !== '')
            ->values()
            ->all();

        if ($normalized === []) {
            return [
                'needs_follow_up' => false,
                'reason' => 'Sem atividade recente para analise.',
            ];
        }

        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            return $this->localFallback($normalized);
        }

        try {
            $payload = [
                'model' => (string) config('services.openai.model', 'gpt-5-nano'),
                'temperature' => 0,
                'max_tokens' => 120,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You classify CRM notes. Return strict JSON {"needs_follow_up":bool,"reason":string}.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Analyze notes and detect if customer expects a response.\n".json_encode($normalized),
                    ],
                ],
            ];

            $response = Http::baseUrl('https://api.openai.com/v1')
                ->acceptJson()
                ->asJson()
                ->timeout(max(1, (int) config('services.openai.timeout', 20)))
                ->connectTimeout(max(1, (int) config('services.openai.connect_timeout', 10)))
                ->withToken($apiKey)
                ->post('chat/completions', $payload);

            if (! $response->successful()) {
                return $this->localFallback($normalized);
            }

            $rawContent = (string) data_get($response->json(), 'choices.0.message.content', '');
            $decoded = json_decode($rawContent, true);
            if (! is_array($decoded)) {
                return $this->localFallback($normalized);
            }

            return [
                'needs_follow_up' => (bool) ($decoded['needs_follow_up'] ?? false),
                'reason' => (string) ($decoded['reason'] ?? 'Analise sem motivo especifico.'),
            ];
        } catch (\Throwable) {
            return $this->localFallback($normalized);
        }
    }

    /**
     * @param  list<string>  $activityNotes
     * @return array{needs_follow_up: bool, reason: string}
     */
    private function localFallback(array $activityNotes): array
    {
        $text = mb_strtolower(implode(' ', $activityNotes));

        $requestSignals = [
            'pediu',
            'solicitou',
            'enviar',
            'documentacao',
            'proposta',
            'orcamento',
            'duvida',
            'aguarda',
        ];

        $riskSignals = [
            'sem resposta',
            'nao respondeu',
            'sem retorno',
            'pendente',
            'urgente',
        ];

        $hasRequest = collect($requestSignals)->contains(fn (string $signal): bool => str_contains($text, $signal));
        $hasRisk = collect($riskSignals)->contains(fn (string $signal): bool => str_contains($text, $signal));

        if ($hasRequest && $hasRisk) {
            return [
                'needs_follow_up' => true,
                'reason' => 'Notas recentes indicam pedido do cliente ainda sem resposta clara.',
            ];
        }

        if ($hasRisk) {
            return [
                'needs_follow_up' => true,
                'reason' => 'Notas recentes mostram risco de atraso no contacto.',
            ];
        }

        return [
            'needs_follow_up' => false,
            'reason' => 'Nao foram detetados sinais criticos.',
        ];
    }
}
