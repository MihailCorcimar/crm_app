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
    ) {}

    public function index(): Response
    {
        $tenantId = TenantContext::id();
        /** @var User|null $user */
        $user = request()->user();

        return Inertia::render('ai/Chat', [
            'suggestedQuestions' => [
                'Qual o volume de negocios em negociacao?',
                'Qual o volume de negocios em follow up?',
                'Qual o telemovel do Antonio Pinheiro?',
                'Qual o email do Joao Silva?',
            ],
            'tenantId' => $tenantId,
            'historyMessages' => $tenantId !== null && $user !== null
                ? $this->historyMessagesFor($tenantId, (int) $user->getAuthIdentifier())
                : [],
            'suggestions' => $tenantId !== null && $user !== null
                ? $this->suggestionsFor($tenantId, (int) $user->getAuthIdentifier())
                : [],
        ]);
    }

    public function store(ChatQueryRequest $request): JsonResponse
    {
        $startedAt = microtime(true);

        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'An active tenant is required.');

        /** @var User $user */
        $user = $request->user();
        $userId = (int) $user->getAuthIdentifier();
        $message = (string) $request->validated('message');
        $inputLength = mb_strlen($message);

        $intentMs = 0;
        $queryMs = 0;
        $intent = 'unsupported';

        $this->storeHistoryMessage(
            tenantId: $tenantId,
            userId: $userId,
            role: 'user',
            text: $message,
        );

        try {
            $intentStart = microtime(true);
            $resolvedIntent = $this->intentService->resolve($message, $tenantId, $userId);
            $intentMs = (int) round((microtime(true) - $intentStart) * 1000);
            $intent = $resolvedIntent['intent'];
        } catch (RuntimeException $exception) {
            $totalMs = (int) round((microtime(true) - $startedAt) * 1000);

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
                role: 'assistant',
                text: $exception->getMessage(),
                intent: 'error',
                confidence: 0,
            );

            return response()->json([
                'message' => $message,
                'intent' => 'error',
                'confidence' => 0,
                'answer' => $exception->getMessage(),
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
                role: 'assistant',
                text: 'Nao foi possivel processar a pergunta neste momento.',
                intent: 'error',
                confidence: 0,
            );

            return response()->json([
                'message' => $message,
                'intent' => 'error',
                'confidence' => 0,
                'answer' => 'Nao foi possivel processar a pergunta neste momento.',
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

    public function stream(ChatQueryRequest $request): StreamedResponse|JsonResponse
    {
        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'An active tenant is required.');

        /** @var User $user */
        $user = $request->user();
        $userId = (int) $user->getAuthIdentifier();
        $message = (string) $request->validated('message');

        $this->storeHistoryMessage(
            tenantId: $tenantId,
            userId: $userId,
            role: 'user',
            text: $message,
        );

        try {
            $resolvedIntent = $this->intentService->resolve($message, $tenantId, $userId);
            $result = $this->queryService->execute($resolvedIntent, $user, $tenantId);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $message,
                'intent' => 'error',
                'confidence' => 0,
                'answer' => 'Nao foi possivel processar a pergunta neste momento.',
                'data' => [
                    'type' => 'error',
                ],
            ], 503);
        }

        $links = $this->extractLinks(is_array($result['data']) ? $result['data'] : []);

        return response()->stream(
            function () use ($message, $resolvedIntent, $result, $tenantId, $userId, $links): void {
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
     * @return array<int, array{id: string, role: string, text: string, created_at: string, links: array<int, array{label: string, href: string}>}>
     */
    private function historyMessagesFor(int $tenantId, int $userId): array
    {
        return AiChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(80)
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
                    'links' => $links,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array{label: string, href: string}>  $links
     * @param  array<string, mixed>|null  $contextData
     */
    private function storeHistoryMessage(
        int $tenantId,
        int $userId,
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
                        'label' => "Abrir negocio: {$dealTitle}",
                        'href' => '/deals/'.(int) $dealId,
                    ];
                }
            }

            $links[] = [
                'label' => 'Ver Kanban de negocios',
                'href' => '/deals',
            ];
        }

        return $links;
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
