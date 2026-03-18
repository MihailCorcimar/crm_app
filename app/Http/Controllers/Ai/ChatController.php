<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ChatQueryRequest;
use App\Models\AiChatMessage;
use App\Models\AiSalesSuggestion;
use App\Models\User;
use App\Services\Ai\AiCommercialAgentService;
use App\Services\Ai\AiIntentService;
use App\Services\Ai\AiPerformanceService;
use App\Services\Ai\AiSecureQueryService;
use App\Services\Ai\AiStreamingAnswerService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatController extends Controller
{
    public function __construct(
        private readonly AiIntentService $intentService,
        private readonly AiSecureQueryService $queryService,
        private readonly AiPerformanceService $performanceService,
        private readonly AiStreamingAnswerService $streamingAnswerService,
        private readonly AiCommercialAgentService $commercialAgentService,
    ) {
        $this->middleware('module.permission:chat,read')->only(['index', 'history']);
        $this->middleware('module.permission:chat,create')->only(['store', 'stream']);
    }

    public function index(): Response
    {
        $tenantId = TenantContext::id();
        /** @var User|null $user */
        $user = request()->user();

        $historyData = ['messages' => [], 'sessions' => [], 'active_session_id' => null];
        if ($tenantId !== null && $user !== null) {
            $historyData = $this->chatHistoryDataFor($tenantId, (int) $user->getAuthIdentifier());
        }

        return Inertia::render('ai/Chat', [
            'suggestedQuestions' => [
                'Qual o volume de negócios em negociação?',
                'Qual o volume de negócios em follow-up?',
                'Qual o telemóvel do António Pinheiro?',
                'Qual o email do João Silva?',
            ],
            'tenantId' => $tenantId,
            'historyMessages' => $historyData['messages'],
            'chatSessions' => $historyData['sessions'],
            'activeSessionId' => $historyData['active_session_id'],
            'suggestions' => $tenantId !== null && $user !== null
                ? $this->suggestionsFor($tenantId, (int) $user->getAuthIdentifier())
                : [],
        ]);
    }

    public function store(ChatQueryRequest $request): JsonResponse
    {
        $startedAt = microtime(true);

        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'É necessário um tenant ativo.');

        /** @var User $user */
        $user = $request->user();
        $userId = (int) $user->getAuthIdentifier();
        $sessionId = $this->resolveSessionId(
            is_string($request->validated('session_id')) ? (string) $request->validated('session_id') : null,
            $tenantId,
            $userId,
        );
        $message = (string) $request->validated('message');
        $inputLength = mb_strlen($message);

        $intentMs = 0;
        $queryMs = 0;
        $intent = 'unsupported';

        $this->storeHistoryMessage(
            tenantId: $tenantId,
            userId: $userId,
            sessionId: $sessionId,
            role: 'user',
            text: $message,
        );

        try {
            $intentStart = microtime(true);
            $resolvedIntent = $this->intentService->resolve($message, $tenantId, $userId);
            $resolvedIntent = $this->applyConversationContext($resolvedIntent, $message, $tenantId, $userId, $sessionId);
            $intentMs = (int) round((microtime(true) - $intentStart) * 1000);
            $intent = $resolvedIntent['intent'];
        } catch (RuntimeException $exception) {
            $totalMs = (int) round((microtime(true) - $startedAt) * 1000);
            $friendlyError = $this->userFacingTemporaryError();

            $this->performanceService->record([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'intent' => $intent,
                'success' => false,
                'intent_ms' => $intentMs,
                'query_ms' => $queryMs,
                'total_ms' => $totalMs,
                'input_length' => $inputLength,
                'error' => $exception->getMessage(),
            ]);

            $this->storeHistoryMessage(
                tenantId: $tenantId,
                userId: $userId,
                sessionId: $sessionId,
                role: 'assistant',
                text: $friendlyError,
                intent: 'error',
                confidence: 0,
            );

            return response()->json([
                'message' => $message,
                'intent' => 'error',
                'confidence' => 0,
                'answer' => $friendlyError,
                'data' => [
                    'type' => 'error',
                ],
                'meta' => [
                    'tenant_id' => $tenantId,
                    'timings_ms' => [
                        'intent' => $intentMs,
                        'query' => $queryMs,
                        'total' => $totalMs,
                    ],
                ],
            ], 503);
        }

        try {
            $queryStart = microtime(true);
            $result = $this->queryService->execute($resolvedIntent, $user, $tenantId);
            $queryMs = (int) round((microtime(true) - $queryStart) * 1000);
        } catch (Throwable $exception) {
            $totalMs = (int) round((microtime(true) - $startedAt) * 1000);
            $friendlyError = $this->userFacingTemporaryError();

            $this->performanceService->record([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'intent' => $intent,
                'success' => false,
                'intent_ms' => $intentMs,
                'query_ms' => $queryMs,
                'total_ms' => $totalMs,
                'input_length' => $inputLength,
                'error' => $exception->getMessage(),
            ]);

            $this->storeHistoryMessage(
                tenantId: $tenantId,
                userId: $userId,
                sessionId: $sessionId,
                role: 'assistant',
                text: $friendlyError,
                intent: 'error',
                confidence: 0,
            );

            return response()->json([
                'message' => $message,
                'intent' => 'error',
                'confidence' => 0,
                'answer' => $friendlyError,
                'data' => [
                    'type' => 'error',
                ],
                'meta' => [
                    'tenant_id' => $tenantId,
                    'timings_ms' => [
                        'intent' => $intentMs,
                        'query' => $queryMs,
                        'total' => $totalMs,
                    ],
                ],
            ], 500);
        }

        $totalMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->performanceService->record([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'intent' => $intent,
            'success' => true,
            'intent_ms' => $intentMs,
            'query_ms' => $queryMs,
            'total_ms' => $totalMs,
            'input_length' => $inputLength,
        ]);

        $links = $this->extractLinks(is_array($result['data']) ? $result['data'] : []);

        $this->storeHistoryMessage(
            tenantId: $tenantId,
            userId: $userId,
            sessionId: $sessionId,
            role: 'assistant',
            text: (string) $result['answer'],
            intent: (string) $resolvedIntent['intent'],
            confidence: (float) $resolvedIntent['confidence'],
            links: $links,
            contextData: is_array($result['data']) ? $result['data'] : [],
        );

        return response()->json([
            'message' => $message,
            'intent' => $resolvedIntent['intent'],
            'confidence' => $resolvedIntent['confidence'],
            'answer' => $result['answer'],
            'data' => $result['data'],
            'meta' => [
                'tenant_id' => $tenantId,
                'timings_ms' => [
                    'intent' => $intentMs,
                    'query' => $queryMs,
                    'total' => $totalMs,
                ],
            ],
        ]);
    }

    public function history(): JsonResponse
    {
        $tenantId = TenantContext::id();
        abort_if($tenantId === null, 422, 'É necessário um tenant ativo.');

        /** @var User|null $user */
        $user = request()->user();
        abort_if($user === null, 401, 'Não autenticado.');

        return response()->json(
            $this->chatHistoryDataFor($tenantId, (int) $user->getAuthIdentifier())
        );
    }

    public function stream(ChatQueryRequest $request): StreamedResponse|JsonResponse
    {
        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'É necessário um tenant ativo.');

        /** @var User $user */
        $user = $request->user();
        $userId = (int) $user->getAuthIdentifier();
        $sessionId = $this->resolveSessionId(
            is_string($request->validated('session_id')) ? (string) $request->validated('session_id') : null,
            $tenantId,
            $userId,
        );
        $message = (string) $request->validated('message');

        $this->storeHistoryMessage(
            tenantId: $tenantId,
            userId: $userId,
            sessionId: $sessionId,
            role: 'user',
            text: $message,
        );

        try {
            $resolvedIntent = $this->intentService->resolve($message, $tenantId, $userId);
            $resolvedIntent = $this->applyConversationContext($resolvedIntent, $message, $tenantId, $userId, $sessionId);
            $result = $this->queryService->execute($resolvedIntent, $user, $tenantId);
        } catch (Throwable $exception) {
            $friendlyError = $this->userFacingTemporaryError();

            return response()->json([
                'message' => $message,
                'intent' => 'error',
                'confidence' => 0,
                'answer' => $friendlyError,
                'data' => [
                    'type' => 'error',
                ],
            ], 503);
        }

        $links = $this->extractLinks(is_array($result['data']) ? $result['data'] : []);

        return response()->stream(
            function () use ($message, $resolvedIntent, $result, $tenantId, $userId, $sessionId, $links): void {
                try {
                    $finalAnswer = $this->streamingAnswerService->stream(
                        message: $message,
                        resolvedIntent: $resolvedIntent,
                        queryResult: $result,
                        onChunk: function (string $delta): void {
                            $this->streamPacket([
                                'type' => 'chunk',
                                'delta' => $delta,
                            ]);
                        },
                    );

                    $this->storeHistoryMessage(
                        tenantId: $tenantId,
                        userId: $userId,
                        sessionId: $sessionId,
                        role: 'assistant',
                        text: $finalAnswer,
                        intent: (string) $resolvedIntent['intent'],
                        confidence: (float) $resolvedIntent['confidence'],
                        links: $links,
                        contextData: is_array($result['data']) ? $result['data'] : [],
                    );

                    $this->streamPacket([
                        'type' => 'done',
                        'answer' => $finalAnswer,
                        'intent' => $resolvedIntent['intent'],
                        'confidence' => $resolvedIntent['confidence'],
                        'data' => $result['data'],
                        'links' => $links,
                    ]);

                    return;
                } catch (Throwable) {
                    $fallbackAnswer = (string) $result['answer'];

                    $this->storeHistoryMessage(
                        tenantId: $tenantId,
                        userId: $userId,
                        sessionId: $sessionId,
                        role: 'assistant',
                        text: $fallbackAnswer,
                        intent: (string) $resolvedIntent['intent'],
                        confidence: (float) $resolvedIntent['confidence'],
                        links: $links,
                        contextData: is_array($result['data']) ? $result['data'] : [],
                    );

                    $this->streamPacket([
                        'type' => 'fallback',
                        'answer' => $fallbackAnswer,
                        'intent' => $resolvedIntent['intent'],
                        'confidence' => $resolvedIntent['confidence'],
                        'data' => $result['data'],
                        'links' => $links,
                    ]);
                }
            },
            200,
            [
                'Content-Type' => 'application/x-ndjson',
                'Cache-Control' => 'no-cache, no-transform',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function streamPacket(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }

    /**
     * @return array{
     *   messages: array<int, array{id: string, role: string, text: string, created_at: string, session_id: string|null, links: array<int, array{label: string, href: string}>}>,
     *   sessions: array<int, array{id: string, title: string, preview: string, updated_at: string, message_count: int, is_legacy: bool}>,
     *   active_session_id: string|null
     * }
     */
    private function chatHistoryDataFor(int $tenantId, int $userId): array
    {
        $messages = $this->historyMessagesFor($tenantId, $userId);
        $sessions = $this->buildChatSessionsFromMessages($messages);

        $activeSessionId = null;
        if (count($sessions) > 0) {
            $activeSessionId = (string) ($sessions[0]['id'] ?? '');
            if ($activeSessionId === '') {
                $activeSessionId = null;
            }
        }

        return [
            'messages' => $messages,
            'sessions' => $sessions,
            'active_session_id' => $activeSessionId,
        ];
    }

    /**
     * @return array<int, array{id: string, role: string, text: string, created_at: string, session_id: string|null, links: array<int, array{label: string, href: string}>}>
     */
    private function historyMessagesFor(int $tenantId, int $userId): array
    {
        return AiChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->reverse()
            ->values()
            ->map(function (AiChatMessage $message): array {
                $rawLinks = is_array($message->links) ? $message->links : [];
                $links = collect($rawLinks)
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->map(fn (array $item): array => [
                        'label' => (string) ($item['label'] ?? ''),
                        'href' => (string) ($item['href'] ?? ''),
                    ])
                    ->filter(fn (array $item): bool => $item['label'] !== '' && $item['href'] !== '')
                    ->values()
                    ->all();

                return [
                    'id' => (string) $message->id,
                    'role' => $message->role,
                    'text' => $message->text,
                    'created_at' => $message->created_at?->toIso8601String() ?? '',
                    'session_id' => is_string($message->session_id) && trim($message->session_id) !== ''
                        ? $message->session_id
                        : null,
                    'links' => $links,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array{id: string, role: string, text: string, created_at: string, session_id: string|null, links: array<int, array{label: string, href: string}>}>  $messages
     * @return array<int, array{id: string, title: string, preview: string, updated_at: string, message_count: int, is_legacy: bool}>
     */
    private function buildChatSessionsFromMessages(array $messages): array
    {
        $grouped = collect($messages)->groupBy(function (array $message): string {
            $sessionId = $message['session_id'] ?? null;

            return is_string($sessionId) && $sessionId !== '' ? $sessionId : 'legacy';
        });

        return $grouped
            ->map(function ($sessionMessages, string $sessionId): array {
                /** @var array<int, array{id: string, role: string, text: string, created_at: string, session_id: string|null, links: array<int, array{label: string, href: string}>}> $sessionRows */
                $sessionRows = collect($sessionMessages)->values()->all();
                $last = $sessionRows[count($sessionRows) - 1] ?? null;

                $firstUserText = collect($sessionRows)
                    ->first(fn (array $item): bool => $item['role'] === 'user' && trim((string) ($item['text'] ?? '')) !== '');

                $title = $sessionId === 'legacy'
                    ? 'Histórico anterior'
                    : $this->truncateText((string) (($firstUserText['text'] ?? '') ?: 'Novo chat'), 46);

                return [
                    'id' => $sessionId,
                    'title' => $title,
                    'preview' => $this->truncateText((string) ($last['text'] ?? ''), 78),
                    'updated_at' => (string) ($last['created_at'] ?? ''),
                    'message_count' => count($sessionRows),
                    'is_legacy' => $sessionId === 'legacy',
                ];
            })
            ->sortByDesc(fn (array $session): string => (string) ($session['updated_at'] ?? ''))
            ->values()
            ->all();
    }

    private function truncateText(string $text, int $limit): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($clean === '') {
            return '';
        }

        if (mb_strlen($clean) <= $limit) {
            return $clean;
        }

        return mb_substr($clean, 0, $limit - 1).'…';
    }

    private function resolveSessionId(?string $requestedSessionId, int $tenantId, int $userId): string
    {
        if (is_string($requestedSessionId) && Str::isUuid($requestedSessionId)) {
            return $requestedSessionId;
        }

        $latestSessionId = $this->latestSessionId($tenantId, $userId);
        if ($latestSessionId !== null) {
            return $latestSessionId;
        }

        return (string) Str::uuid();
    }

    private function latestSessionId(int $tenantId, int $userId): ?string
    {
        $sessionId = AiChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNotNull('session_id')
            ->orderByDesc('id')
            ->value('session_id');

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    /**
     * @param  array<int, array{label: string, href: string}>  $links
     * @param  array<string, mixed>|null  $contextData
     */
    private function storeHistoryMessage(
        int $tenantId,
        int $userId,
        string $sessionId,
        string $role,
        string $text,
        ?string $intent = null,
        ?float $confidence = null,
        array $links = [],
        ?array $contextData = null,
    ): void {
        AiChatMessage::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'role' => $role,
            'text' => $text,
            'intent' => $intent,
            'confidence' => $confidence,
            'links' => $links,
            'context_data' => $contextData,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{label: string, href: string}>
     */
    private function extractLinks(array $data): array
    {
        $links = [];
        $dataType = (string) ($data['type'] ?? '');

        if ($dataType === 'contact_lookup') {
            $contactId = data_get($data, 'contact.id');
            $contactName = (string) data_get($data, 'contact.name', '');

            if (is_numeric($contactId) && $contactName !== '') {
                $links[] = [
                    'label' => "Abrir pessoa: {$contactName}",
                    'href' => '/people/'.(int) $contactId,
                ];
            }
        }

        if ($dataType === 'deal_summary') {
            $topDeals = data_get($data, 'top_deals', []);

            if (is_array($topDeals)) {
                foreach (array_slice($topDeals, 0, 3) as $deal) {
                    if (! is_array($deal)) {
                        continue;
                    }

                    $dealId = $deal['id'] ?? null;
                    $dealTitle = (string) ($deal['title'] ?? '');

                    if (! is_numeric($dealId) || $dealTitle === '') {
                        continue;
                    }

                    $links[] = [
                        'label' => "Abrir negócio: {$dealTitle}",
                        'href' => '/deals/'.(int) $dealId,
                    ];
                }
            }

            $links[] = [
                'label' => 'Ver Kanban de negócios',
                'href' => '/deals',
            ];
        }

        if ($dataType === 'entity_contacts') {
            $entityId = data_get($data, 'entity.id');
            $entityName = (string) data_get($data, 'entity.name', '');

            if (is_numeric($entityId) && $entityName !== '') {
                $links[] = [
                    'label' => "Abrir entidade: {$entityName}",
                    'href' => '/entities/'.(int) $entityId,
                ];
            }

            $contacts = data_get($data, 'contacts', []);
            if (is_array($contacts)) {
                foreach (array_slice($contacts, 0, 3) as $contact) {
                    if (! is_array($contact)) {
                        continue;
                    }

                    $contactId = $contact['id'] ?? null;
                    $contactName = (string) ($contact['name'] ?? '');

                    if (! is_numeric($contactId) || $contactName === '') {
                        continue;
                    }

                    $links[] = [
                        'label' => "Abrir pessoa: {$contactName}",
                        'href' => '/people/'.(int) $contactId,
                    ];
                }
            }
        }

        if ($dataType === 'entity_contacts_deal_products') {
            $entityId = data_get($data, 'entity.id');
            $entityName = (string) data_get($data, 'entity.name', '');

            if (is_numeric($entityId) && $entityName !== '') {
                $links[] = [
                    'label' => "Abrir entidade: {$entityName}",
                    'href' => '/entities/'.(int) $entityId,
                ];
            }

            $contacts = data_get($data, 'contacts', []);
            if (is_array($contacts)) {
                foreach (array_slice($contacts, 0, 2) as $contact) {
                    if (! is_array($contact)) {
                        continue;
                    }

                    $contactId = $contact['id'] ?? null;
                    $contactName = (string) ($contact['name'] ?? '');

                    if (! is_numeric($contactId) || $contactName === '') {
                        continue;
                    }

                    $links[] = [
                        'label' => "Abrir pessoa: {$contactName}",
                        'href' => '/people/'.(int) $contactId,
                    ];
                }
            }

            $topDeals = data_get($data, 'top_deals', []);
            if (is_array($topDeals)) {
                foreach (array_slice($topDeals, 0, 2) as $deal) {
                    if (! is_array($deal)) {
                        continue;
                    }

                    $dealId = $deal['id'] ?? null;
                    $dealTitle = (string) ($deal['title'] ?? '');

                    if (! is_numeric($dealId) || $dealTitle === '') {
                        continue;
                    }

                    $links[] = [
                        'label' => "Abrir negócio: {$dealTitle}",
                        'href' => '/deals/'.(int) $dealId,
                    ];
                }
            }

            $links[] = [
                'label' => 'Ver Kanban de negócios',
                'href' => '/deals',
            ];
        }

        return $links;
    }

    private function userFacingTemporaryError(): string
    {
        return 'Não foi possível processar a pergunta neste momento. Tenta novamente daqui a instantes.';
    }

    /**
     * @param  array{intent: string, confidence: float, parameters: array<string, string|null>}  $resolvedIntent
     * @return array{intent: string, confidence: float, parameters: array<string, string|null>}
     */
    private function applyConversationContext(
        array $resolvedIntent,
        string $message,
        int $tenantId,
        int $userId,
        string $sessionId,
    ): array {
        $intent = (string) ($resolvedIntent['intent'] ?? 'unsupported');
        $parameters = is_array($resolvedIntent['parameters'] ?? null) ? $resolvedIntent['parameters'] : [];
        $entityFromIntent = $this->sanitizePotentialEntityName(is_string($parameters['entity_name'] ?? null) ? $parameters['entity_name'] : null);

        $explicitField = $this->normalizeRequestedField(
            message: $message,
            fieldFromIntent: is_string($parameters['field'] ?? null) ? $parameters['field'] : null,
        );
        $nameFromIntent = $this->sanitizePotentialName(is_string($parameters['name'] ?? null) ? $parameters['name'] : null);

        $isFollowUp = $this->looksLikeFollowUpQuestion($message);
        if (! $isFollowUp && ! in_array($intent, ['contact_lookup', 'entity_contacts'], true)) {
            return $resolvedIntent;
        }

        $previousEntity = $this->lastEntityContactsContext($tenantId, $userId, $sessionId);
        $entityFromMessage = $this->sanitizePotentialEntityName($this->extractLikelyEntityFromFollowUp($message));
        $resolvedEntity = $entityFromIntent ?? $entityFromMessage;

        if (
            $explicitField === null
            && in_array($intent, ['entity_contacts', 'contact_lookup', 'unsupported'], true)
            && $this->looksLikeEntityFollowUpQuestion($message)
            && $this->looksLikeProductsFollowUpQuestion($message)
            && ($resolvedEntity !== null || $previousEntity !== null)
        ) {
            return [
                'intent' => 'entity_contacts_deal_products',
                'confidence' => max((float) ($resolvedIntent['confidence'] ?? 0), 0.73),
                'parameters' => [
                    'stage' => null,
                    'name' => null,
                    'field' => null,
                    'entity_name' => $resolvedEntity ?? $previousEntity,
                ],
            ];
        }

        if (
            $explicitField === null
            && in_array($intent, ['entity_contacts', 'contact_lookup', 'unsupported'], true)
            && $this->looksLikeEntityFollowUpQuestion($message)
            && ($resolvedEntity !== null || $previousEntity !== null)
        ) {
            return [
                'intent' => 'entity_contacts',
                'confidence' => max((float) ($resolvedIntent['confidence'] ?? 0), 0.72),
                'parameters' => [
                    'stage' => null,
                    'name' => null,
                    'field' => null,
                    'entity_name' => $resolvedEntity ?? $previousEntity,
                ],
            ];
        }

        $previous = $this->lastContactContext($tenantId, $userId, $sessionId);
        $nameFromMessage = $this->sanitizePotentialName($this->extractLikelyNameFromFollowUp($message));
        $referencesPrevious = $this->referencesPreviousContact($message);

        $resolvedField = $explicitField ?? ($previous['field'] ?? null);
        $resolvedName = $nameFromIntent ?? $nameFromMessage;

        if ($resolvedName === null && $referencesPrevious && $previous !== null) {
            $resolvedName = $previous['name'];
        }

        $canPromote = in_array($intent, ['contact_lookup', 'unsupported'], true) && $isFollowUp;
        if (! $canPromote || $resolvedField === null || $resolvedName === null) {
            return $resolvedIntent;
        }

        return [
            'intent' => 'contact_lookup',
            'confidence' => max((float) ($resolvedIntent['confidence'] ?? 0), 0.7),
            'parameters' => [
                'stage' => null,
                'name' => $resolvedName,
                'field' => $resolvedField,
                'entity_name' => null,
            ],
        ];
    }

    private function looksLikeEntityFollowUpQuestion(string $message): bool
    {
        $normalized = Str::lower(Str::ascii(trim($message)));
        if ($normalized === '') {
            return false;
        }

        if (preg_match('/^(?:e\\s+)?(?:da|do|de)\\s+/u', $normalized) === 1) {
            return true;
        }

        return preg_match('/\\b(contact|pessoa|entidade|empresa)\\b/u', $normalized) === 1;
    }

    private function looksLikeProductsFollowUpQuestion(string $message): bool
    {
        $normalized = Str::lower(Str::ascii(trim($message)));
        if ($normalized === '') {
            return false;
        }

        return preg_match('/\\b(produto|produtos|item|itens|negocio|negocios|deal|deals)\\b/u', $normalized) === 1;
    }

    private function looksLikeFollowUpQuestion(string $message): bool
    {
        $normalized = Str::lower(trim($message));
        if ($normalized === '') {
            return false;
        }

        $mentionsField = str_contains($normalized, 'telemovel')
            || str_contains($normalized, 'telemóvel')
            || str_contains($normalized, 'telefone')
            || str_contains($normalized, 'mail')
            || str_contains($normalized, 'email')
            || str_contains($normalized, 'e-mail');

        if ($mentionsField) {
            return true;
        }

        if (str_starts_with($normalized, 'e ')) {
            return true;
        }

        $wordCount = count(array_filter(preg_split('/\s+/u', $normalized) ?: []));

        return $wordCount > 0 && $wordCount <= 6;
    }

    private function extractLikelyNameFromFollowUp(string $message): ?string
    {
        $candidate = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
        if ($candidate === '') {
            return null;
        }

        $candidate = preg_replace('/^[Ee]\s+/u', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B?.!,;:");

        if (preg_match('/(?:mail|email|e-mail|telefone|telemovel|telemóvel)\s+(?:d[aeo]?)\s+(.+)$/ui', $candidate, $matches) === 1) {
            $candidate = trim((string) ($matches[1] ?? ''));
        }

        if (preg_match('/(?:do|da|de)\s+(.+)$/ui', $candidate, $matches) === 1) {
            $candidate = trim((string) ($matches[1] ?? ''));
        }

        $candidate = preg_replace('/^(o|a)\s+/ui', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B?.!,;:");

        return $candidate !== '' ? $candidate : null;
    }

    private function extractLikelyEntityFromFollowUp(string $message): ?string
    {
        $candidate = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
        if ($candidate === '') {
            return null;
        }

        $candidate = preg_replace('/^[Ee]\s+/u', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B?.!,;:");

        if (preg_match('/(?:do|da|de)\\s+(.+)$/ui', $candidate, $matches) === 1) {
            $candidate = trim((string) ($matches[1] ?? ''));
        }

        return $candidate !== '' ? $candidate : null;
    }

    private function normalizeRequestedField(string $message, ?string $fieldFromIntent): ?string
    {
        if (in_array($fieldFromIntent, ['phone', 'mobile', 'email'], true)) {
            return $fieldFromIntent;
        }

        $normalized = Str::lower(trim($message));

        if (str_contains($normalized, 'telemovel') || str_contains($normalized, 'telemóvel')) {
            return 'mobile';
        }

        if (str_contains($normalized, 'telefone')) {
            return 'phone';
        }

        if (str_contains($normalized, 'email') || str_contains($normalized, 'e-mail') || str_contains($normalized, 'mail')) {
            return 'email';
        }

        return null;
    }

    private function referencesPreviousContact(string $message): bool
    {
        return preg_match('/\b(dela|dele|deles|delas|deste|desta|desse|dessa|ele|ela)\b/ui', $message) === 1;
    }

    /**
     * @return array{name: string, field: 'phone'|'mobile'|'email'}|null
     */
    private function lastContactContext(int $tenantId, int $userId, string $sessionId): ?array
    {
        $messages = AiChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->where('role', 'assistant')
            ->where('intent', 'contact_lookup')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($messages as $assistantMessage) {
            $field = data_get($assistantMessage->context_data, 'field');
            if (! in_array($field, ['phone', 'mobile', 'email'], true)) {
                continue;
            }

            $name = data_get($assistantMessage->context_data, 'contact.name');
            if (! is_string($name) || trim($name) === '') {
                $name = data_get($assistantMessage->context_data, 'name');
            }

            $name = $this->sanitizePotentialName(is_string($name) ? $name : null);
            if ($name === null) {
                continue;
            }

            return [
                'name' => $name,
                'field' => $field,
            ];
        }

        return null;
    }

    private function lastEntityContactsContext(int $tenantId, int $userId, string $sessionId): ?string
    {
        $messages = AiChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->where('role', 'assistant')
            ->whereIn('intent', ['entity_contacts', 'entity_contacts_deal_products'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($messages as $assistantMessage) {
            $entityName = data_get($assistantMessage->context_data, 'entity.name');
            if (! is_string($entityName) || trim($entityName) === '') {
                $entityName = data_get($assistantMessage->context_data, 'entity_name');
            }

            $entityName = $this->sanitizePotentialEntityName(is_string($entityName) ? $entityName : null);
            if ($entityName !== null) {
                return $entityName;
            }
        }

        return null;
    }

    private function sanitizePotentialName(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $candidate = trim($value);
        $candidate = trim($candidate, " \t\n\r\0\x0B?.!,;:");
        if ($candidate === '') {
            return null;
        }

        $candidate = preg_replace('/^(?:o|a)?\s*(?:mail|email|e-mail|telefone|telemovel|telemóvel)\s+/ui', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s+(?:mail|email|e-mail|telefone|telemovel|telemóvel)$/ui', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B?.!,;:");

        if ($candidate === '') {
            return null;
        }

        $normalized = Str::lower($candidate);
        if (in_array($normalized, ['mail', 'email', 'e-mail', 'telefone', 'telemovel', 'telemóvel', 'contacto'], true)) {
            return null;
        }

        if (in_array($normalized, ['dela', 'dele', 'deles', 'delas', 'ele', 'ela', 'desse', 'dessa', 'deste', 'desta'], true)) {
            return null;
        }

        if (
            $this->referencesPreviousContact($candidate)
            && preg_match('/\b(mail|email|e-mail|telefone|telemovel|telemóvel)\b/ui', $candidate) === 1
        ) {
            return null;
        }

        return $candidate;
    }

    private function sanitizePotentialEntityName(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $candidate = trim($value);
        $candidate = trim($candidate, " \t\n\r\0\x0B?.!,;:");
        if ($candidate === '') {
            return null;
        }

        $candidate = preg_replace('/^(?:a|o|as|os)\s+/ui', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s+(?:no tenant ativo|na base de dados)$/ui', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B?.!,;:");
        if ($candidate === '') {
            return null;
        }

        $normalized = Str::lower(Str::ascii($candidate));
        $invalid = ['entidade', 'empresa', 'contacto', 'contato', 'pessoa', 'pessoas', 'contactos', 'contatos'];
        if (in_array($normalized, $invalid, true)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function suggestionsFor(int $tenantId, int $userId): array
    {
        return $this->commercialAgentService
            ->suggestionsForUser($tenantId, $userId)
            ->map(function (AiSalesSuggestion $suggestion): array {
                return [
                    'id' => (int) $suggestion->id,
                    'title' => (string) $suggestion->title,
                    'reason' => (string) $suggestion->reason,
                    'next_step' => $suggestion->next_step,
                    'action_type' => (string) $suggestion->action_type,
                    'source_type' => (string) $suggestion->source_type,
                    'priority_score' => (int) $suggestion->priority_score,
                    'status' => (string) $suggestion->status,
                    'deferred_until' => $suggestion->deferred_until?->toIso8601String(),
                    'suggested_for_at' => $suggestion->suggested_for_at?->toIso8601String(),
                    'deal' => $suggestion->deal === null ? null : [
                        'id' => (int) $suggestion->deal->id,
                        'title' => (string) $suggestion->deal->title,
                        'stage' => (string) $suggestion->deal->stage,
                    ],
                    'contact' => $suggestion->contact === null ? null : [
                        'id' => (int) $suggestion->contact->id,
                        'name' => trim(
                            implode(' ', array_filter([
                                (string) $suggestion->contact->first_name,
                                (string) $suggestion->contact->last_name,
                            ]))
                        ),
                    ],
                ];
            })
            ->values()
            ->all();
    }
}




