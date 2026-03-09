<?php

namespace App\Console\Commands;

use App\Models\DealEmailLog;
use App\Support\DealInboundReplyService;
use Illuminate\Console\Command;

class SimulateDealInboundReplyCommand extends Command
{
    protected $signature = 'deals:simulate-inbound-reply
                            {deal_id : Deal id}
                            {--from= : Customer email used as sender}
                            {--subject= : Optional subject override}
                            {--body= : Optional body override}';

    protected $description = 'Simulate a customer inbound reply to stop follow-up automatically';

    public function handle(DealInboundReplyService $replyService): int
    {
        $dealId = (int) $this->argument('deal_id');

        $latestFollowUp = DealEmailLog::withoutGlobalScopes()
            ->where('deal_id', $dealId)
            ->where('email_type', 'follow_up')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if ($latestFollowUp === null) {
            $this->error('No follow-up email log found for this deal.');

            return self::FAILURE;
        }

        $from = trim((string) ($this->option('from') ?? $latestFollowUp->to_email));
        $token = (string) ($latestFollowUp->tracking_token ?? '');
        $subject = trim((string) ($this->option('subject') ?? "Re: {$latestFollowUp->subject}"));
        $body = trim((string) ($this->option('body') ?? 'Obrigado, vamos avançar.'));

        if ($token !== '' && stripos($subject, 'FU:') === false && stripos($body, 'FU:') === false) {
            $body .= "\n\nRef: FU:{$token}";
        }

        $result = $replyService->process([
            'from' => $from,
            'subject' => $subject,
            'text' => $body,
        ]);

        $this->line('Inbound simulation processed.');
        $this->line(sprintf('Matched: %s', $result['matched'] ? 'yes' : 'no'));
        $this->line(sprintf('Stopped: %s', $result['stopped'] ? 'yes' : 'no'));
        $this->line(sprintf('Deal id: %s', $result['deal_id'] ?? 'n/a'));
        $this->line(sprintf('Reason: %s', (string) $result['reason']));

        return $result['matched'] ? self::SUCCESS : self::FAILURE;
    }
}
