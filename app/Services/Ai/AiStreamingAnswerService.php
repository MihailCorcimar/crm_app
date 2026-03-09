<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiStreamingAnswerService
{
    /**
     * @param  array{intent: string, confidence: float, parameters: array<string, string|null>}  $resolvedIntent
     * @param  array{answer: string, data: array<string, mixed>}  $queryResult
     * @param  callable(string): void  $onChunk
     */
    public function stream(
        string $message,
        array $resolvedIntent,
        array $queryResult,
        callable $onChunk,
    ): string {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured for streaming.');
        }

        $model = (string) config('services.openai.model', 'gpt-5-nano');
        $timeout = max(1, (int) config('services.openai.timeout', 20));
        $connectTimeout = max(1, (int) config('services.openai.connect_timeout', 10));
        $maxOutputTokens = max(32, (int) config('services.openai.max_output_tokens', 120));

        $requestPayload = [
            'model' => $model,
            'stream' => true,
            'temperature' => 0.2,
            'max_tokens' => $maxOutputTokens,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $this->userPrompt($message, $resolvedIntent, $queryResult),
                ],
            ],
        ];

        $response = Http::baseUrl('https://api.openai.com/v1')
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withToken($apiKey)
            ->withOptions(['stream' => true])
            ->post('chat/completions', $requestPayload);

        if (! $response->successful()) {
            $status = $response->status();
            $error = (string) data_get($response->json(), 'error.message', 'Unknown streaming error.');

            throw new RuntimeException("OpenAI streaming error ({$status}): {$error}");
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $accumulated = '';
        $done = false;

        while (! $body->eof() && ! $done) {
            $chunk = $body->read(1024);
            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            while (($lineBreakPos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $lineBreakPos));
                $buffer = substr($buffer, $lineBreakPos + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));
                if ($payload === '[DONE]') {
                    $done = true;
                    break;
                }

                $decoded = json_decode($payload, true);
                if (! is_array($decoded)) {
                    continue;
                }

                $delta = data_get($decoded, 'choices.0.delta.content');
                if (! is_string($delta) || $delta === '') {
                    continue;
                }

                $accumulated .= $delta;
                $onChunk($delta);
            }
        }

        $final = trim($accumulated);
        if ($final === '') {
            return (string) $queryResult['answer'];
        }

        return $final;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a CRM sales assistant.
Answer in Portuguese (Portugal), with short and clear sentences.
Use only the provided context data.
If data is missing, state it explicitly.
Do not invent values.
PROMPT;
    }

    /**
     * @param  array{intent: string, confidence: float, parameters: array<string, string|null>}  $resolvedIntent
     * @param  array{answer: string, data: array<string, mixed>}  $queryResult
     */
    private function userPrompt(string $message, array $resolvedIntent, array $queryResult): string
    {
        $context = [
            'intent' => $resolvedIntent['intent'],
            'confidence' => $resolvedIntent['confidence'],
            'parameters' => $resolvedIntent['parameters'],
            'query_answer' => $queryResult['answer'],
            'query_data' => $queryResult['data'],
        ];

        $contextJson = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return "Pergunta original: {$message}\n\nContexto estruturado:\n{$contextJson}";
    }
}

