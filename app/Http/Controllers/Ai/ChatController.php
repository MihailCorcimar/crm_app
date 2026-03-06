<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ChatQueryRequest;
use App\Models\User;
use App\Services\Ai\AiIntentService;
use App\Services\Ai\AiSecureQueryService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ChatController extends Controller
{
    public function __construct(
        private readonly AiIntentService $intentService,
        private readonly AiSecureQueryService $queryService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('ai/Chat', [
            'suggestedQuestions' => [
                'Qual o volume de negócios em negociação?',
                'Qual o volume de negócios em follow up?',
                'Qual o telemóvel do António Pinheiro?',
                'Qual o email do João Silva?',
            ],
            'tenantId' => TenantContext::id(),
        ]);
    }

    public function store(ChatQueryRequest $request): JsonResponse
    {
        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'An active tenant is required.');

        /** @var User $user */
        $user = $request->user();
        $message = (string) $request->validated('message');

        try {
            $resolvedIntent = $this->intentService->resolve($message);
        } catch (RuntimeException $exception) {
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
                ],
            ], 503);
        }

        $result = $this->queryService->execute($resolvedIntent, $user, $tenantId);

        return response()->json([
            'message' => $message,
            'intent' => $resolvedIntent['intent'],
            'confidence' => $resolvedIntent['confidence'],
            'answer' => $result['answer'],
            'data' => $result['data'],
            'meta' => [
                'tenant_id' => $tenantId,
            ],
        ]);
    }
}
