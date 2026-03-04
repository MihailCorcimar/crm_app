<?php

namespace App\Console\Commands;

use App\Support\DealFollowUpService;
use Illuminate\Console\Command;

class ProcessDealFollowUpsCommand extends Command
{
    protected $signature = 'deals:process-follow-ups
                            {--limit=100 : Maximum deals to process per run}
                            {--force : Ignore business-hours restriction (testing only)}';

    protected $description = 'Process automatic follow-up emails for deals in Follow Up stage';

    public function handle(DealFollowUpService $followUpService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $sent = $followUpService->processDue($limit, $force);

        if ($force) {
            $this->warn('Business-hours restriction bypassed (--force).');
        }

        $this->info(sprintf('Follow-up processed. Emails sent: %d', $sent));

        return self::SUCCESS;
    }
}
