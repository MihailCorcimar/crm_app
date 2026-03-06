<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ChatQueryRequest;
use App\Models\User;
use App\Services\Ai\AiIntentService;
use App\Services\Ai\AiSecureQueryService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(
        private readonly AiIntentService $intentService,
        private readonly AiSecureQueryService $queryService,
    ) {
    }

    public function store(ChatQueryRequest $request): JsonResponse
    {
        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'An active tenant is required.');

        /** @var User $user */
        $user = $request->user();
        $message = (string) $request->validated('message');

        $resolvedIntent = $this->intentService->resolve($message);
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
