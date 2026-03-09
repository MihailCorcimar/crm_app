<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiSalesSuggestion;
use App\Services\Ai\AiCommercialAgentService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SuggestionController extends Controller
{
    public function index(Request $request, AiCommercialAgentService $agentService): JsonResponse
    {
        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'An active tenant is required.');

        $userId = (int) $request->user()->getAuthIdentifier();

        return response()->json([
            'suggestions' => $this->toPayload(
                $agentService->suggestionsForUser($tenantId, $userId)
            ),
        ]);
    }

    public function refresh(Request $request, AiCommercialAgentService $agentService): JsonResponse
    {
        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'An active tenant is required.');

        $userId = (int) $request->user()->getAuthIdentifier();
        $saved = $agentService->refreshSuggestions($tenantId, $userId);

        return response()->json([
            'message' => 'Sugestoes atualizadas.',
            'saved' => $saved,
            'suggestions' => $this->toPayload(
                $agentService->suggestionsForUser($tenantId, $userId)
            ),
        ]);
    }

    public function accept(AiSalesSuggestion $suggestion, AiCommercialAgentService $agentService): JsonResponse
    {
        $this->authorize('update', $suggestion);
        $agentService->acceptSuggestion($suggestion);

        return response()->json([
            'message' => 'Sugestao aceite e atividade criada.',
        ]);
    }

    public function defer(Request $request, AiSalesSuggestion $suggestion, AiCommercialAgentService $agentService): JsonResponse
    {
        $this->authorize('update', $suggestion);

        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:14'],
        ]);

        $days = isset($validated['days']) ? (int) $validated['days'] : 2;
        $agentService->deferSuggestion($suggestion, $days);

        return response()->json([
            'message' => 'Sugestao adiada.',
            'deferred_days' => $days,
        ]);
    }

    public function archive(AiSalesSuggestion $suggestion, AiCommercialAgentService $agentService): JsonResponse
    {
        $this->authorize('update', $suggestion);
        $agentService->archiveSuggestion($suggestion);

        return response()->json([
            'message' => 'Sugestao arquivada.',
        ]);
    }

    /**
     * @param  Collection<int, AiSalesSuggestion>  $suggestions
     * @return array<int, array<string, mixed>>
     */
    private function toPayload(Collection $suggestions): array
    {
        return $suggestions
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
