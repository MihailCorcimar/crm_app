<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class AiIntentService
{
    /**
     * @return array{
     *   intent: string,
     *   confidence: float,
     *   parameters: array{stage: string|null, name: string|null, field: string|null, entity_name: string|null}
     * }
     */
    public function resolve(string $message, ?int $tenantId = null, ?int $userId = null): array
    {
        $apiKey = (string) config('services.openai.api_key', '');

        if ($apiKey === '') {
            return $this->heuristicIntent($message);
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
            'ai:intent:v8:%s:%s:%s:%s',
            (string) ($tenantId ?? 'na'),
            (string) ($userId ?? 'na'),
            $model,
            hash('sha256', $this->normalizeForMatch($message)),
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
     * @return array{
     *   intent: string,
     *   confidence: float,
     *   parameters: array{stage: string|null, name: string|null, field: string|null, entity_name: string|null}
     * }
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
        try {
            $http = $this->openAiHttpClient($apiKey, $timeout, $connectTimeout, $retries, $retryDelayMs);
            $systemPrompt = $this->systemPrompt();

            $responsesResult = $http->post('responses', [
                'model' => $model,
                'input' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ],
                'max_output_tokens' => $maxOutputTokens,
            ]);

            if ($responsesResult->successful()) {
                $content = $this->extractResponsesText($responsesResult->json());

                return $this->normalizeIntentPayload($content, $message);
            }

            $chatResult = $http->post('chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ],
                'temperature' => 0,
                'max_tokens' => $maxOutputTokens,
            ]);

            if (! $chatResult->successful()) {
                return $this->heuristicIntent($message);
            }

            $content = (string) data_get($chatResult->json(), 'choices.0.message.content', '');

            return $this->normalizeIntentPayload($content, $message);
        } catch (Throwable) {
            return $this->heuristicIntent($message);
        }
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
Users write in Portuguese (pt-PT), with typos and short forms.
Classify the message into one intent and extract parameters.

Allowed intents:
- deal_summary
- contact_lookup
- entity_contacts
- entity_contacts_deal_products
- unsupported

Rules:
- Return STRICT JSON only.
- No markdown.
- If unsure, use "unsupported".
- Keep confidence between 0 and 1.
- For "deals in stage" queries, use deal_summary and set stage.
- For "phone/mobile/email of person", use contact_lookup with name + field.
- For "contacts/people of entity/company", use entity_contacts with entity_name.
- For combined requests (contacts + products/items + deals of an entity), use entity_contacts_deal_products with entity_name.

Output JSON schema:
{
  "intent": "deal_summary|contact_lookup|entity_contacts|entity_contacts_deal_products|unsupported",
  "confidence": 0.0,
  "parameters": {
    "stage": "lead|proposal|negotiation|follow_up|won|lost|null",
    "name": "string|null",
    "field": "phone|mobile|email|null",
    "entity_name": "string|null"
  }
}
PROMPT;
    }

    private function extractResponsesText(mixed $payload): string
    {
        if (! is_array($payload)) {
            return '';
        }

        $output = data_get($payload, 'output', []);
        if (is_array($output)) {
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
        }

        $fallbackText = data_get($payload, 'output_text');

        return is_string($fallbackText) ? $fallbackText : '';
    }

    /**
     * @return array{
     *   intent: string,
     *   confidence: float,
     *   parameters: array{stage: string|null, name: string|null, field: string|null, entity_name: string|null}
     * }
     */
    private function normalizeIntentPayload(string $raw, string $sourceMessage): array
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            $decoded = $this->decodeWrappedJson($raw);
        }

        if (! is_array($decoded)) {
            return $this->heuristicIntent($sourceMessage);
        }

        $intent = (string) ($decoded['intent'] ?? 'unsupported');
        if (! in_array($intent, ['deal_summary', 'contact_lookup', 'entity_contacts', 'entity_contacts_deal_products', 'unsupported'], true)) {
            $intent = 'unsupported';
        }

        $confidence = max(0.0, min(1.0, (float) ($decoded['confidence'] ?? 0.0)));
        $parameters = is_array($decoded['parameters'] ?? null) ? $decoded['parameters'] : [];

        $stage = $parameters['stage'] ?? null;
        if (! in_array($stage, ['lead', 'proposal', 'negotiation', 'follow_up', 'won', 'lost', null], true)) {
            $stage = null;
        }

        $field = $parameters['field'] ?? null;
        if (! in_array($field, ['phone', 'mobile', 'email', null], true)) {
            $field = null;
        }

        $name = $this->sanitizeNameCandidate($parameters['name'] ?? null);
        $entityName = $this->sanitizeEntityCandidate($parameters['entity_name'] ?? null);

        $resolved = [
            'intent' => $intent,
            'confidence' => $confidence,
            'parameters' => [
                'stage' => $stage,
                'name' => $name,
                'field' => $field,
                'entity_name' => $entityName,
            ],
        ];

        return $this->chooseBetterIntent($sourceMessage, $resolved);
    }

    /**
     * @param  array{
     *   intent: string,
     *   confidence: float,
     *   parameters: array{stage: string|null, name: string|null, field: string|null, entity_name: string|null}
     * }  $resolved
     * @return array{
     *   intent: string,
     *   confidence: float,
     *   parameters: array{stage: string|null, name: string|null, field: string|null, entity_name: string|null}
     * }
     */
    private function chooseBetterIntent(string $message, array $resolved): array
    {
        $heuristic = $this->heuristicIntent($message);

        if (
            $heuristic['intent'] === 'entity_contacts_deal_products'
            && $heuristic['parameters']['entity_name'] !== null
            && (
                $resolved['intent'] !== 'entity_contacts_deal_products'
                || $resolved['parameters']['entity_name'] === null
            )
        ) {
            return $heuristic;
        }

        if (
            $heuristic['intent'] === 'entity_contacts'
            && $heuristic['parameters']['entity_name'] !== null
            && (
                $resolved['intent'] !== 'entity_contacts'
                || $resolved['parameters']['entity_name'] === null
            )
        ) {
            return $heuristic;
        }

        if (
            in_array($resolved['intent'], ['contact_lookup', 'entity_contacts'], true)
            && $resolved['parameters']['field'] === null
            && in_array($heuristic['intent'], ['entity_contacts', 'entity_contacts_deal_products'], true)
            && $heuristic['parameters']['entity_name'] !== null
        ) {
            return $heuristic;
        }

        if ($resolved['intent'] === 'unsupported' && $heuristic['intent'] !== 'unsupported') {
            return $heuristic;
        }

        if (
            $resolved['intent'] === 'deal_summary'
            && ($resolved['parameters']['stage'] === null || $resolved['parameters']['entity_name'] === null)
            && $heuristic['intent'] === 'deal_summary'
            && ($heuristic['parameters']['stage'] !== null || $heuristic['parameters']['entity_name'] !== null)
        ) {
            return [
                'intent' => 'deal_summary',
                'confidence' => max($resolved['confidence'], $heuristic['confidence']),
                'parameters' => [
                    'stage' => $resolved['parameters']['stage'] ?? $heuristic['parameters']['stage'],
                    'name' => null,
                    'field' => null,
                    'entity_name' => $resolved['parameters']['entity_name'] ?? $heuristic['parameters']['entity_name'],
                ],
            ];
        }

        return $resolved;
    }

    /**
     * @return array{
     *   intent: string,
     *   confidence: float,
     *   parameters: array{stage: string|null, name: string|null, field: string|null, entity_name: string|null}
     * }
     */
    private function heuristicIntent(string $message): array
    {
        $normalized = $this->normalizeForMatch($message);

        if ($normalized === '') {
            return $this->unsupported(0.2);
        }

        $field = $this->inferFieldFromQuestion($normalized);
        $stage = $this->inferStageFromQuestion($normalized);
        $name = $this->extractLikelyNameFromQuestion($message);
        $entityName = $this->extractEntityNameFromQuestion($message);

        if ($field !== null && $name === null) {
            $name = $this->extractNameNearField($message);
        }

        if ($this->looksLikeCombinedEntityContactsProductsQuestion($normalized, $entityName)) {
            return [
                'intent' => 'entity_contacts_deal_products',
                'confidence' => $entityName === null ? 0.56 : 0.86,
                'parameters' => [
                    'stage' => null,
                    'name' => null,
                    'field' => null,
                    'entity_name' => $entityName,
                ],
            ];
        }

        if ($this->looksLikeEntityContactsQuestion($normalized, $entityName) && $field === null) {
            return [
                'intent' => 'entity_contacts',
                'confidence' => $entityName === null ? 0.55 : 0.84,
                'parameters' => [
                    'stage' => null,
                    'name' => null,
                    'field' => null,
                    'entity_name' => $entityName,
                ],
            ];
        }

        if ($field !== null) {
            return [
                'intent' => 'contact_lookup',
                'confidence' => $name === null ? 0.58 : 0.78,
                'parameters' => [
                    'stage' => null,
                    'name' => $name,
                    'field' => $field,
                    'entity_name' => null,
                ],
            ];
        }

        if ($stage !== null || $this->looksLikeDealQuestion($normalized)) {
            return [
                'intent' => 'deal_summary',
                'confidence' => $stage === null ? 0.64 : 0.82,
                'parameters' => [
                    'stage' => $stage,
                    'name' => null,
                    'field' => null,
                    'entity_name' => $entityName,
                ],
            ];
        }

        if ($this->looksLikeContactQuestion($normalized)) {
            return [
                'intent' => 'contact_lookup',
                'confidence' => 0.42,
                'parameters' => [
                    'stage' => null,
                    'name' => $name,
                    'field' => null,
                    'entity_name' => null,
                ],
            ];
        }

        return $this->unsupported(0.25);
    }

    /**
     * @return array{
     *   intent: string,
     *   confidence: float,
     *   parameters: array{stage: string|null, name: string|null, field: string|null, entity_name: string|null}
     * }
     */
    private function unsupported(float $confidence): array
    {
        return [
            'intent' => 'unsupported',
            'confidence' => $confidence,
            'parameters' => [
                'stage' => null,
                'name' => null,
                'field' => null,
                'entity_name' => null,
            ],
        ];
    }

    private function inferStageFromQuestion(string $normalizedMessage): ?string
    {
        if ($normalizedMessage === '') {
            return null;
        }

        if (str_contains($normalizedMessage, 'lead')) return 'lead';
        if (str_contains($normalizedMessage, 'proposta')) return 'proposal';
        if (str_contains($normalizedMessage, 'negociacao')) return 'negotiation';
        if (str_contains($normalizedMessage, 'follow up') || str_contains($normalizedMessage, 'follow-up') || str_contains($normalizedMessage, 'follow_up')) return 'follow_up';
        if (str_contains($normalizedMessage, 'ganho') || str_contains($normalizedMessage, 'won')) return 'won';
        if (str_contains($normalizedMessage, 'perdido') || str_contains($normalizedMessage, 'lost')) return 'lost';

        return null;
    }

    private function inferFieldFromQuestion(string $normalizedMessage): ?string
    {
        if ($normalizedMessage === '') {
            return null;
        }

        if (str_contains($normalizedMessage, 'telemovel')) {
            return 'mobile';
        }

        if (str_contains($normalizedMessage, 'telefone')) {
            return 'phone';
        }

        if (
            str_contains($normalizedMessage, 'email')
            || str_contains($normalizedMessage, 'e-mail')
            || str_contains($normalizedMessage, 'mail')
        ) {
            return 'email';
        }

        return null;
    }

    private function extractLikelyNameFromQuestion(string $message): ?string
    {
        $trimmed = trim($this->collapseWhitespace($message));
        if ($trimmed === '') {
            return null;
        }

        $patterns = [
            '/(?:mail|email|e-mail|telefone|telemovel|telemóvel)\s+(?:do|da|de)\s+(.+)$/ui',
            '/(.+?)\s+(?:mail|email|e-mail|telefone|telemovel|telemóvel)$/ui',
            '/(?:do|da|de)\s+(.+)$/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches) !== 1) {
                continue;
            }

            $candidate = $this->sanitizeNameCandidate($matches[1] ?? null);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractNameNearField(string $message): ?string
    {
        $candidate = trim($this->collapseWhitespace($message));
        $candidate = preg_replace('/^(?:qual|quais|que|diz|diga|mostra|mostrar|ver|quero|preciso|diz me|diz-me)\s+/ui', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/^e\s+/ui', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/^(?:o|a|os|as)\s+/ui', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/^(?:telefone|telemovel|telemóvel|email|e-mail|mail)\s+/ui', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s+(?:telefone|telemovel|telemóvel|email|e-mail|mail)$/ui', '', $candidate) ?? $candidate;

        return $this->sanitizeNameCandidate($candidate);
    }

    private function extractEntityNameFromQuestion(string $message): ?string
    {
        $trimmed = trim($this->collapseWhitespace($message));
        if ($trimmed === '') {
            return null;
        }

        $patterns = [
            '/(?:pessoas?|contactos?|contatos?)\s+(?:de\s+contacto\s+)?(?:da|do|de)\s+(.+?)\s+e\s+(?:os|as|o|a)?\s*(?:produt\w*|item\w*|negoc\w*|deal\w*|pipeline)\b/ui',
            '/(?:pessoas?|contactos?|contatos?)\s+(?:de\s+contacto\s+)?(?:da|do|de)\s+(.+)$/ui',
            '/(?:contacto|contato)\s+principal\s+(?:da|do|de)\s+(.+)$/ui',
            '/(?:neg\w*|deals?|pipeline)\s+.*\s+com\s+(.+)$/ui',
            '/(?:neg\w*|deals?|pipeline)\s+(?:da|do|de)\s+(.+)$/ui',
            '/(?:neg\w*|deals?|pipeline)\s+(?:da|do|de)\s+(?:entidade|empresa)\s+(.+)$/ui',
            '/(?:entidade|empresa)\s+(.+)$/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches) !== 1) {
                continue;
            }

            $candidate = $this->sanitizeEntityCandidate($matches[1] ?? null);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }
    private function sanitizeNameCandidate(mixed $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $clean = trim($candidate, " \t\n\r\0\x0B?.!,;:");
        $clean = preg_replace('/^(?:o|a)\s+/ui', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+(?:mail|email|e-mail|telefone|telemovel|telemóvel)$/ui', '', $clean) ?? $clean;
        $clean = trim($clean, " \t\n\r\0\x0B?.!,;:");

        if ($clean === '') {
            return null;
        }

        $normalized = $this->normalizeForMatch($clean);
        $containsReferencePronoun = preg_match('/\b(dela|dele|deles|delas|ele|ela)\b/ui', $clean) === 1;
        $containsFieldWord = preg_match('/\b(mail|email|e-mail|telefone|telemovel|telemóvel)\b/ui', $clean) === 1;

        if ($containsReferencePronoun && $containsFieldWord) {
            return null;
        }

        $invalid = [
            'mail', 'email', 'e-mail', 'telefone', 'telemovel',
            'contacto', 'contato', 'dela', 'dele', 'deles', 'delas', 'ele', 'ela',
        ];

        if (in_array($normalized, $invalid, true)) {
            return null;
        }

        return $clean;
    }

    private function sanitizeEntityCandidate(mixed $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $clean = trim($candidate, " \t\n\r\0\x0B?.!,;:");
        $clean = preg_replace('/^(?:a|o|as|os)\s+/ui', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+(?:no tenant ativo|na base de dados)$/ui', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+na\s+etapa\s+.+$/ui', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+em\s+(?:lead|proposta|negociacao|follow[\s_-]*up|ganho|perdido)\b.*$/ui', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+e\s+(?:os|as|o|a)?\s*(?:produt\w*|item\w*|negoc\w*|deal\w*|pipeline)\b.*$/ui', '', $clean) ?? $clean;
        $clean = preg_replace('/\b(?:com|da|do|de)\s+(?:eles|elas)\b.*$/ui', '', $clean) ?? $clean;
        $clean = trim($clean, " \t\n\r\0\x0B?.!,;:");

        if ($clean === '') {
            return null;
        }

        $normalized = $this->normalizeForMatch($clean);
        $genericFragments = [
            'negocio', 'negocios', 'deal', 'deals', 'pipeline',
            'estado', 'etapa', 'volume', 'total', 'filtro',
            'tenho', 'tem', 'que tenho', 'no estado', 'na etapa',
        ];

        foreach ($genericFragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return null;
            }
        }

        $invalid = [
            'entidade', 'empresa', 'contacto', 'contato', 'pessoa', 'pessoas',
            'contactos', 'contatos', 'dela', 'dele', 'ele', 'ela', 'eles', 'elas',
        ];

        if (in_array($normalized, $invalid, true)) {
            return null;
        }

        return $clean;
    }
    private function looksLikeEntityContactsQuestion(string $normalizedMessage, ?string $entityName): bool
    {
        $hasContactWord = str_contains($normalizedMessage, 'contact')
            || str_contains($normalizedMessage, 'pessoa')
            || str_contains($normalizedMessage, 'main contact');

        if (! $hasContactWord) {
            return false;
        }

        if ($entityName !== null) {
            return true;
        }

        return str_contains($normalizedMessage, 'entidade')
            || str_contains($normalizedMessage, 'empresa');
    }

    private function looksLikeCombinedEntityContactsProductsQuestion(string $normalizedMessage, ?string $entityName): bool
    {
        if (! $this->looksLikeProductsQuestion($normalizedMessage)) {
            return false;
        }

        if (! $this->looksLikeEntityContactsQuestion($normalizedMessage, $entityName)) {
            return false;
        }

        return $entityName !== null
            || str_contains($normalizedMessage, 'entidade')
            || str_contains($normalizedMessage, 'empresa')
            || str_contains($normalizedMessage, ' com ');
    }

    private function looksLikeDealQuestion(string $normalizedMessage): bool
    {
        $hints = [
            'negocio', 'negocios', 'pipeline', 'etapa', 'estado', 'valor',
            'volume', 'total', 'kanban', 'lead', 'proposta', 'negociacao',
            'follow up', 'ganho', 'perdido',
        ];

        foreach ($hints as $hint) {
            if (str_contains($normalizedMessage, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeContactQuestion(string $normalizedMessage): bool
    {
        $hints = ['contacto', 'contato', 'pessoa', 'telefone', 'telemovel', 'email', 'mail'];

        foreach ($hints as $hint) {
            if (str_contains($normalizedMessage, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeProductsQuestion(string $normalizedMessage): bool
    {
        $hints = ['produto', 'produtos', 'item', 'itens'];

        foreach ($hints as $hint) {
            if (str_contains($normalizedMessage, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeForMatch(string $value): string
    {
        $ascii = Str::lower(Str::ascii($value));
        $ascii = $this->collapseWhitespace($ascii);

        return trim($ascii);
    }

    private function collapseWhitespace(string $value): string
    {
        return (string) preg_replace('/\s+/u', ' ', $value);
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
}




