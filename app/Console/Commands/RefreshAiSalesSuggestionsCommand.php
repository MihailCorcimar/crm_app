<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Ai\AiCommercialAgentService;
use Illuminate\Console\Command;

class RefreshAiSalesSuggestionsCommand extends Command
{
    protected $signature = 'ai:refresh-sales-suggestions
                            {--tenant= : Refresh only one tenant id}
                            {--user= : Refresh only one user id inside target tenant}';

    protected $description = 'Refresh AI commercial suggestions for tenant users';

    public function handle(AiCommercialAgentService $agentService): int
    {
        $tenantOption = $this->option('tenant');
        $targetTenantId = is_numeric($tenantOption) ? (int) $tenantOption : null;

        $userOption = $this->option('user');
        $targetUserId = is_numeric($userOption) ? (int) $userOption : null;

        $tenantIds = $targetTenantId !== null
            ? collect([$targetTenantId])
            : Tenant::query()->pluck('id');

        $totalSaved = 0;

        foreach ($tenantIds as $tenantId) {
            $saved = $agentService->refreshSuggestions((int) $tenantId, $targetUserId);
            $totalSaved += $saved;
            $this->line(sprintf('Tenant %d: %d suggestions refreshed.', (int) $tenantId, $saved));
        }

        $this->info(sprintf('Done. Total suggestions refreshed: %d', $totalSaved));

        return self::SUCCESS;
    }
}
