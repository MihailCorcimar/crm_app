<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;

class AiPerformanceService
{
    /**
     * @param  array{
     *     tenant_id:int,
     *     user_id:int,
     *     intent:string,
     *     success:bool,
     *     intent_ms:int,
     *     query_ms:int,
     *     total_ms:int,
     *     input_length:int,
     *     error?:string|null
     * }  $metrics
     */
    public function record(array $metrics): void
    {
        Log::info('ai.chat.performance', [
            'tenant_id' => $metrics['tenant_id'],
            'user_id' => $metrics['user_id'],
            'intent' => $metrics['intent'],
            'success' => $metrics['success'],
            'timings_ms' => [
                'intent' => $metrics['intent_ms'],
                'query' => $metrics['query_ms'],
                'total' => $metrics['total_ms'],
            ],
            'input_length' => $metrics['input_length'],
            'error' => $metrics['error'] ?? null,
        ]);
    }
}
