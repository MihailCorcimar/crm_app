<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiIntentService
{
    /**
     * @return array{intent: string, confidence: float, parameters: array<string, string|null>}
     */
    public function resolve(string $message, ?int $tenantId = null, ?int $userId = null): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $model = (string) config('services.openai.model', 'gpt-5-nano');
        $timeout = max(1, (int) config('services.openai.timeout', 20));
        $maxOutputTokens = max(32, (int) config('services.openai.max_output_tokens', 120));
        $connectTimeout = max(1, (int) config('services.openai.connect_timeout', 10));
        $retries = max(0, (int) config('services.openai.retries', 2));
        $retryDelayMs = max(0, (int) config('services.openai.retry_delay_ms', 300));
        $cacheSeconds = max(0, (int) config('services.openai.intent_cache_seconds', 120));

        if ($cacheSeconds === 0) {
            return $this->resolveFromOpenAi(
                message: $message,
                apiKey: $apiKey,
                model: $model,
                timeout: $timeout,
                maxOutputTokens: $maxOutputTokens,
                connectTimeout: $connectTimeout,
                retries: $retries,
                retryDelayMs: $retryDelayMs,
            );
        }

        $cacheKey = sprintf(
            'ai:intent:v1:%s:%s:%s:%s',
            (string) ($tenantId ?? 'na'),
            (string) ($userId ?? 'na'),
            $model,
            hash('sha256', mb_strtolower(trim($message))),
        );

        return Cache::remember($cacheKey, now()->addSeconds($cacheSeconds), function () use (
            $message,
            $apiKey,
            $model,
            $timeout,
            $maxOutputTokens,
            $connectTimeout,
            $retries,
            $retryDelayMs,
        ): array {
            return $this->resolveFromOpenAi(
                message: $message,
                apiKey: $apiKey,
                model: $model,
                timeout: $timeout,
                maxOutputTokens: $maxOutputTokens,
                connectTimeout: $connectTimeout,
                retries: $retries,
                retryDelayMs: $retryDelayMs,
            );
        });
    }

    /**
     * @return array{intent: string, confidence: float, parameters: array<string, string|null>}
     */
    private function resolveFromOpenAi(
        string $message,
        string $apiKey,
        string $model,
        int $timeout,
        int $maxOutputTokens,
        int $connectTimeout,
        int $retries,
        int $retryDelayMs,
    ): array {
        $http = $this->openAiHttpClient($apiKey, $timeout, $connectTimeout, $retries, $retryDelayMs);
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
            'max_output_tokens' => $maxOutputTokens,
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
            'max_tokens' => $maxOutputTokens,
        ];

        $chatResult = $http->post('chat/completions', $chatCompletionsRequest);
        if (! $chatResult->successful()) {
            $this->throwOpenAiError($chatResult);
        }

        $content = (string) data_get($chatResult->json(), 'choices.0.message.content', '');

        return $this->normalizeIntentPayload($content);
    }

    private function openAiHttpClient(
        string $apiKey,
        int $timeout,
        int $connectTimeout,
        int $retries,
        int $retryDelayMs,
    ): PendingRequest {
        $request = Http::baseUrl('https://api.openai.com/v1')
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withToken($apiKey);

        if ($retries === 0) {
            return $request;
        }

        return $request->retry($retries, $retryDelayMs, null, false);
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
            $decoded = $this->decodeWrappedJson($raw);
        }

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

    /**
     * @return array<string, mixed>|null
     */
    private function decodeWrappedJson(string $raw): ?array
    {
        $trimmed = trim($raw);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = substr($trimmed, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function throwOpenAiError(Response $response): never
    {
        $status = $response->status();
        $message = (string) data_get($response->json(), 'error.message', 'OpenAI request failed.');

        throw new RuntimeException("OpenAI error ({$status}): {$message}");
    }
}
