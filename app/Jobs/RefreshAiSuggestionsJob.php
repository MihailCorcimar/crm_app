<?php

namespace App\Jobs;

use App\Services\Ai\AiCommercialAgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshAiSuggestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public ?int $userId = null,
    ) {}

    public function handle(AiCommercialAgentService $agentService): void
    {
        $agentService->refreshSuggestions($this->tenantId, $this->userId);
    }
}
