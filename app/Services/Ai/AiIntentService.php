<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiIntentService
{
    /**
     * @return array{intent: string, confidence: float, parameters: array<string, string|null>}
     */
    public function resolve(string $message): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $model = (string) config('services.openai.model', 'gpt-5-nano');
        $timeout = (int) config('services.openai.timeout', 20);

        $http = Http::baseUrl('https://api.openai.com/v1')
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->withToken($apiKey);

        $systemPrompt = $this->systemPrompt();

        $responsesRequest = [
            'model' => $model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ],
            ],
            'max_output_tokens' => 180,
        ];

        $responsesResult = $http->post('responses', $responsesRequest);
        if ($responsesResult->successful()) {
            $content = $this->extractResponsesText($responsesResult);

            return $this->normalizeIntentPayload($content);
        }

        // OpenAI-only fallback for compatibility across endpoints.
        $chatCompletionsRequest = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => 0,
            'max_tokens' => 180,
        ];

        $chatResult = $http->post('chat/completions', $chatCompletionsRequest);
        if (! $chatResult->successful()) {
            $this->throwOpenAiError($chatResult);
        }

        $content = (string) data_get($chatResult->json(), 'choices.0.message.content', '');

        return $this->normalizeIntentPayload($content);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are an intent resolver for a CRM assistant.
Classify the user message into one intent and extract parameters.

Allowed intents:
- deal_summary
- contact_lookup
- unsupported

Rules:
- Return STRICT JSON only.
- No markdown.
- If unsure, use "unsupported".
- Keep confidence between 0 and 1.

Output JSON schema:
{
  "intent": "deal_summary|contact_lookup|unsupported",
  "confidence": 0.0,
  "parameters": {
    "stage": "lead|proposal|negotiation|follow_up|won|lost|null",
    "name": "string|null",
    "field": "phone|mobile|email|null"
  }
}
PROMPT;
    }

    private function extractResponsesText(Response $response): string
    {
        $payload = $response->json();

        $output = data_get($payload, 'output', []);
        if (! is_array($output)) {
            return '';
        }

        foreach ($output as $item) {
            if (! is_array($item)) {
                continue;
            }

            $content = data_get($item, 'content', []);
            if (! is_array($content)) {
                continue;
            }

            foreach ($content as $contentItem) {
                if (! is_array($contentItem)) {
                    continue;
                }

                $text = data_get($contentItem, 'text');
                if (is_string($text) && trim($text) !== '') {
                    return $text;
                }
            }
        }

        $fallbackText = data_get($payload, 'output_text');

        return is_string($fallbackText) ? $fallbackText : '';
    }

    /**
     * @return array{intent: string, confidence: float, parameters: array<string, string|null>}
     */
    private function normalizeIntentPayload(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI did not return valid JSON for intent resolution.');
        }

        $intent = (string) ($decoded['intent'] ?? 'unsupported');
        if (! in_array($intent, ['deal_summary', 'contact_lookup', 'unsupported'], true)) {
            $intent = 'unsupported';
        }

        $confidence = (float) ($decoded['confidence'] ?? 0);
        $confidence = max(0, min(1, $confidence));

        $parameters = is_array($decoded['parameters'] ?? null) ? $decoded['parameters'] : [];

        $stage = $parameters['stage'] ?? null;
        if (! in_array($stage, ['lead', 'proposal', 'negotiation', 'follow_up', 'won', 'lost', null], true)) {
            $stage = null;
        }

        $field = $parameters['field'] ?? null;
        if (! in_array($field, ['phone', 'mobile', 'email', null], true)) {
            $field = null;
        }

        $name = $parameters['name'] ?? null;
        $name = is_string($name) && trim($name) !== '' ? trim($name) : null;

        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'parameters' => [
                'stage' => $stage,
                'name' => $name,
                'field' => $field,
            ],
        ];
    }

    private function throwOpenAiError(Response $response): never
    {
        $status = $response->status();
        $message = (string) data_get($response->json(), 'error.message', 'OpenAI request failed.');

        throw new RuntimeException("OpenAI error ({$status}): {$message}");
    }
}
