<?php

namespace App\Services\Ai;

use App\Models\AiSalesSuggestion;
use App\Models\AiSalesSuggestionFeedback;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealEmailLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiCommercialAgentService
{
    public function __construct(
        private readonly AiActivitySemanticService $semanticService,
    ) {}

    public function refreshSuggestions(int $tenantId, ?int $targetUserId = null): int
    {
        $users = User::query()
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenantId))
            ->where('status', 'active')
            ->when($targetUserId !== null, fn ($query) => $query->where('id', $targetUserId))
            ->get(['id']);

        $saved = 0;
        foreach ($users as $user) {
            $saved += $this->refreshForUser($tenantId, (int) $user->id);
        }

        return $saved;
    }

    public function refreshForUser(int $tenantId, int $userId): int
    {
        $deals = Deal::query()
            ->where('tenant_id', $tenantId)
            ->where('owner_id', $userId)
            ->whereNotIn('stage', [Deal::STAGE_WON, Deal::STAGE_LOST])
            ->get();

        $saved = 0;
        foreach ($deals as $deal) {
            $items = $this->buildSuggestionsForDeal($tenantId, $userId, $deal);
            foreach ($items as $item) {
                $this->upsertSuggestion($tenantId, $userId, $item);
                $saved++;
            }
        }

        return $saved;
    }

    /**
     * @return Collection<int, AiSalesSuggestion>
     */
    public function suggestionsForUser(int $tenantId, int $userId, int $limit = 30): Collection
    {
        return AiSalesSuggestion::query()
            ->with(['deal:id,title,stage,value,expected_close_date', 'contact:id,first_name,last_name'])
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereIn('status', [AiSalesSuggestion::STATUS_PENDING, AiSalesSuggestion::STATUS_DEFERRED])
            ->where(function ($query): void {
                $query->whereNull('deferred_until')
                    ->orWhere('deferred_until', '<=', now());
            })
            ->orderByDesc('priority_score')
            ->orderBy('suggested_for_at')
            ->limit($limit)
            ->get();
    }

    public function acceptSuggestion(AiSalesSuggestion $suggestion): void
    {
        DB::transaction(function () use ($suggestion): void {
            $this->createQuickActivity($suggestion);

            $suggestion->update([
                'status' => AiSalesSuggestion::STATUS_ACCEPTED,
                'deferred_until' => null,
            ]);

            $this->recordFeedback($suggestion, 'accepted');
        });
    }

    public function deferSuggestion(AiSalesSuggestion $suggestion, int $days = 2): void
    {
        $deferDays = max(1, min(14, $days));

        $suggestion->update([
            'status' => AiSalesSuggestion::STATUS_DEFERRED,
            'deferred_until' => now()->addDays($deferDays),
        ]);

        $this->recordFeedback($suggestion, 'deferred');
    }

    public function archiveSuggestion(AiSalesSuggestion $suggestion): void
    {
        $suggestion->update([
            'status' => AiSalesSuggestion::STATUS_ARCHIVED,
            'deferred_until' => null,
        ]);

        $this->recordFeedback($suggestion, 'archived');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildSuggestionsForDeal(int $tenantId, int $userId, Deal $deal): array
    {
        $suggestions = [];
        $now = CarbonImmutable::now();

        $latestTouch = $this->latestTouchAt($tenantId, $deal);
        $daysWithoutTouch = $latestTouch === null ? 999 : $latestTouch->diffInDays($now);

        if ($daysWithoutTouch >= 5) {
            $suggestions[] = [
                'deal_id' => $deal->id,
                'contact_id' => null,
                'source_type' => 'contact_gap',
                'action_type' => 'call',
                'title' => 'Negocio sem atividade recente',
                'reason' => "Sem interacao ha {$daysWithoutTouch} dias. Recomendo retomar contacto.",
                'next_step' => 'Criar chamada de acompanhamento para hoje.',
                'priority_score' => min(95, 65 + $daysWithoutTouch),
                'suggested_for_at' => now(),
            ];
        }

        if ($deal->stage === Deal::STAGE_PROPOSAL && ! is_string($deal->proposal_path)) {
            $suggestions[] = [
                'deal_id' => $deal->id,
                'contact_id' => null,
                'source_type' => 'proposal_missing',
                'action_type' => 'send_proposal',
                'title' => 'Enviar proposta ao cliente',
                'reason' => 'Negocio em proposta sem ficheiro de proposta carregado.',
                'next_step' => 'Carregar e enviar proposta atualizada.',
                'priority_score' => 80,
                'suggested_for_at' => now()->addHours(2),
            ];
        }

        if ($deal->expected_close_date !== null
            && $deal->expected_close_date->lte($now->addDays(3))
            && $deal->probability >= 60
            && in_array($deal->stage, [Deal::STAGE_NEGOTIATION, Deal::STAGE_PROPOSAL], true)
        ) {
            $suggestions[] = [
                'deal_id' => $deal->id,
                'contact_id' => null,
                'source_type' => 'close_date_risk',
                'action_type' => 'meeting',
                'title' => 'Validar expectativas antes do fecho',
                'reason' => 'Fecho previsto proximo com probabilidade alta e necessidade de alinhamento final.',
                'next_step' => 'Marcar reuniao curta para desbloquear decisao.',
                'priority_score' => 85,
                'suggested_for_at' => now()->addDay()->setHour(10)->setMinute(0),
            ];
        }

        $recentNotes = $this->recentDealActivityNotes($tenantId, $deal->id);
        $semantic = $this->semanticService->analyze($recentNotes);
        if ($semantic['needs_follow_up']) {
            $suggestions[] = [
                'deal_id' => $deal->id,
                'contact_id' => null,
                'source_type' => 'semantic_signal',
                'action_type' => 'follow_up_email',
                'title' => 'Follow up recomendado',
                'reason' => $semantic['reason'],
                'next_step' => 'Enviar follow up objetivo e propor proximo passo.',
                'priority_score' => 78,
                'suggested_for_at' => now(),
            ];
        }

        return array_map(function (array $item) use ($tenantId, $userId): array {
            $item['priority_score'] = $this->applyLearningScore(
                $tenantId,
                $userId,
                (string) $item['action_type'],
                (int) $item['priority_score'],
            );

            return $item;
        }, $suggestions);
    }

    private function upsertSuggestion(int $tenantId, int $userId, array $payload): void
    {
        $fingerprint = sha1(
            implode('|', [
                $tenantId,
                $userId,
                (string) ($payload['deal_id'] ?? 0),
                (string) ($payload['contact_id'] ?? 0),
                (string) $payload['source_type'],
                (string) $payload['action_type'],
            ])
        );

        $existing = AiSalesSuggestion::query()
            ->where('tenant_id', $tenantId)
            ->where('fingerprint', $fingerprint)
            ->first();

        $attributes = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'deal_id' => $payload['deal_id'] ?? null,
            'contact_id' => $payload['contact_id'] ?? null,
            'source_type' => $payload['source_type'],
            'action_type' => $payload['action_type'],
            'title' => $payload['title'],
            'reason' => $payload['reason'],
            'next_step' => $payload['next_step'] ?? null,
            'priority_score' => $payload['priority_score'],
            'suggested_for_at' => $payload['suggested_for_at'] ?? null,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
            'fingerprint' => $fingerprint,
        ];

        if ($existing === null) {
            AiSalesSuggestion::query()->create($attributes + [
                'status' => AiSalesSuggestion::STATUS_PENDING,
            ]);

            return;
        }

        $status = $existing->status;
        if ($status === AiSalesSuggestion::STATUS_DEFERRED && $existing->deferred_until?->isFuture()) {
            $existing->update($attributes);

            return;
        }

        $existing->update($attributes + [
            'status' => AiSalesSuggestion::STATUS_PENDING,
            'deferred_until' => null,
        ]);
    }

    private function applyLearningScore(int $tenantId, int $userId, string $actionType, int $baseScore): int
    {
        $accepted = AiSalesSuggestionFeedback::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('action_type', $actionType)
            ->where('decision', 'accepted')
            ->count();

        $ignored = AiSalesSuggestionFeedback::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('action_type', $actionType)
            ->whereIn('decision', ['archived', 'deferred'])
            ->count();

        $adjustment = max(-10, min(10, ($accepted - $ignored) * 2));

        return max(1, min(99, $baseScore + $adjustment));
    }

    private function recordFeedback(AiSalesSuggestion $suggestion, string $decision): void
    {
        AiSalesSuggestionFeedback::query()->create([
            'tenant_id' => $suggestion->tenant_id,
            'user_id' => $suggestion->user_id,
            'ai_sales_suggestion_id' => $suggestion->id,
            'action_type' => $suggestion->action_type,
            'decision' => $decision,
        ]);
    }

    private function createQuickActivity(AiSalesSuggestion $suggestion): void
    {
        $deal = $suggestion->deal;
        $startAt = $suggestion->suggested_for_at instanceof \Carbon\CarbonInterface
            ? CarbonImmutable::instance($suggestion->suggested_for_at)
            : now()->addDay()->setHour(10)->setMinute(0);

        $duration = match ($suggestion->action_type) {
            'meeting' => 45,
            'task' => 30,
            'note' => 10,
            'follow_up_email', 'request_docs', 'validate_expectations' => 20,
            default => 20,
        };

        $endAt = $startAt->addMinutes($duration);

        CalendarEvent::query()->create([
            'tenant_id' => $suggestion->tenant_id,
            'title' => $suggestion->title,
            'description' => trim($suggestion->reason."\n".$suggestion->next_step),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'location' => null,
            'owner_id' => $suggestion->user_id,
            'eventable_type' => $deal !== null ? Deal::class : null,
            'eventable_id' => $deal?->id,
            'status' => 'active',
            'event_date' => $startAt->format('Y-m-d'),
            'event_time' => $startAt->format('H:i:s'),
            'duration_minutes' => $duration,
            'share' => null,
            'knowledge' => $suggestion->action_type,
            'user_id' => $suggestion->user_id,
            'calendar_type_id' => null,
            'calendar_action_id' => null,
            'entity_id' => $deal?->entity_id,
        ]);
    }

    private function latestTouchAt(int $tenantId, Deal $deal): ?CarbonImmutable
    {
        $eventAt = CalendarEvent::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($deal): void {
                $query->where(function ($sub) use ($deal): void {
                    $sub->where('eventable_type', Deal::class)
                        ->where('eventable_id', $deal->id);
                });

                if ($deal->entity_id !== null) {
                    $query->orWhere('entity_id', $deal->entity_id);
                }
            })
            ->max('start_at');

        $emailAt = DealEmailLog::query()
            ->where('tenant_id', $tenantId)
            ->where('deal_id', $deal->id)
            ->max('sent_at');

        $timestamps = collect([$eventAt, $emailAt, $deal->updated_at])
            ->filter()
            ->map(fn ($value) => CarbonImmutable::parse($value))
            ->sortDesc()
            ->values();

        return $timestamps->first();
    }

    /**
     * @return list<string>
     */
    private function recentDealActivityNotes(int $tenantId, int $dealId): array
    {
        return CalendarEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('eventable_type', Deal::class)
            ->where('eventable_id', $dealId)
            ->where(function ($query): void {
                $query->where('start_at', '>=', now()->subDays(21))
                    ->orWhere('created_at', '>=', now()->subDays(21));
            })
            ->orderByDesc('start_at')
            ->limit(12)
            ->get(['title', 'description', 'knowledge'])
            ->map(function (CalendarEvent $event): string {
                return implode(' | ', array_filter([
                    (string) $event->title,
                    (string) $event->knowledge,
                    (string) $event->description,
                ]));
            })
            ->filter(fn (string $text): bool => trim($text) !== '')
            ->values()
            ->all();
    }
}
