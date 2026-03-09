<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Support\DealInboundReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealInboundReplyWebhookController extends Controller
{
    public function __invoke(Request $request, DealInboundReplyService $replyService): JsonResponse
    {
        $expectedSecret = trim((string) config('services.mail_inbound.secret', ''));
        if ($expectedSecret !== '') {
            $providedSecret = trim((string) ($request->header('X-Inbound-Secret') ?: $request->input('secret', '')));

            if (! hash_equals($expectedSecret, $providedSecret)) {
                return response()->json([
                    'message' => 'Unauthorized inbound webhook request.',
                ], 401);
            }
        }

        $result = $replyService->process($request->all());

        return response()->json($result);
    }
}
