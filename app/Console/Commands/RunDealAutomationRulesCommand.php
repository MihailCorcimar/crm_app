<?php

namespace App\Console\Commands;

use App\Support\DealAutomationEngineService;
use Illuminate\Console\Command;

class RunDealAutomationRulesCommand extends Command
{
    protected $signature = 'deals:run-automation-rules
                            {--tenant= : Run only one tenant id}
                            {--limit=300 : Max deals scanned per rule}';

    protected $description = 'Run inactivity automation rules for deals and create calendar activities';

    public function handle(DealAutomationEngineService $engineService): int
    {
        $tenantOption = $this->option('tenant');
        $tenantId = is_numeric($tenantOption) ? (int) $tenantOption : null;
        $limit = max(1, (int) $this->option('limit'));

        $summary = $engineService->run($tenantId, $limit);

        $this->info(sprintf(
            'Rules: %d | Deals scanned: %d | Activities: %d | Notifications: %d',
            $summary['rules_processed'],
            $summary['deals_scanned'],
            $summary['activities_created'],
            $summary['notifications_created']
        ));

        return self::SUCCESS;
    }
}
