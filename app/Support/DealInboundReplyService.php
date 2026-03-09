<?php

namespace App\Support;

use App\Models\Deal;
use App\Models\DealEmailLog;

class DealInboundReplyService
{
    public function __construct(
        private readonly DealFollowUpService $followUpService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{matched: bool, stopped: bool, deal_id: int|null, reason: string}
     */
    public function process(array $payload): array
    {
        $fromEmail = $this->extractFromEmail($payload);
        $subject = $this->extractSubject($payload);
        $body = $this->extractBody($payload);
        $token = $this->extractTrackingToken([$subject, $body]);

        $matchedLog = $this->findMatchedOutboundLog($token, $fromEmail);
        if ($matchedLog === null) {
            return [
                'matched' => false,
                'stopped' => false,
                'deal_id' => null,
                'reason' => 'No follow-up correlation found.',
            ];
        }

        /** @var Deal|null $deal */
        $deal = Deal::withoutGlobalScopes()->find($matchedLog->deal_id);
        if ($deal === null) {
            return [
                'matched' => false,
                'stopped' => false,
                'deal_id' => null,
                'reason' => 'Deal not found.',
            ];
        }

        $this->storeInboundLog($deal, $fromEmail, $subject, $body, $matchedLog->tracking_token);

        if ($deal->follow_up_active) {
            $this->followUpService->markCustomerReplied($deal);

            return [
                'matched' => true,
                'stopped' => true,
                'deal_id' => (int) $deal->id,
                'reason' => 'Follow-up stopped automatically (customer replied).',
            ];
        }

        return [
            'matched' => true,
            'stopped' => false,
            'deal_id' => (int) $deal->id,
            'reason' => 'Reply matched, but follow-up was already inactive.',
        ];
    }

    /**
     * @param  list<string>  $texts
     */
    private function extractTrackingToken(array $texts): ?string
    {
        foreach ($texts as $text) {
            if (! is_string($text) || trim($text) === '') {
                continue;
            }

            if (preg_match('/FU:([A-Z0-9]{8,64})/i', $text, $matches) === 1) {
                return strtoupper((string) $matches[1]);
            }
        }

        return null;
    }

    private function findMatchedOutboundLog(?string $token, ?string $fromEmail): ?DealEmailLog
    {
        if ($token !== null) {
            $matchedByToken = DealEmailLog::withoutGlobalScopes()
                ->where('email_type', 'follow_up')
                ->where('tracking_token', $token)
                ->orderByDesc('id')
                ->first();

            if ($matchedByToken !== null) {
                return $matchedByToken;
            }
        }

        if ($fromEmail === null) {
            return null;
        }

        return DealEmailLog::withoutGlobalScopes()
            ->where('email_type', 'follow_up')
            ->where('to_email', $fromEmail)
            ->where('sent_at', '>=', now()->subDays(21))
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();
    }

    private function storeInboundLog(Deal $deal, ?string $fromEmail, string $subject, string $body, ?string $trackingToken): void
    {
        DealEmailLog::withoutGlobalScopes()->create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'email_type' => 'follow_up_reply',
            'to_email' => $fromEmail ?? 'unknown@unknown',
            'from_email' => $fromEmail,
            'subject' => $subject !== '' ? $subject : 'Resposta do cliente',
            'body' => $body !== '' ? $body : 'Sem corpo de mensagem.',
            'attachment_name' => null,
            'tracking_token' => $trackingToken,
            'sent_by' => null,
            'sent_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractFromEmail(array $payload): ?string
    {
        $candidates = [
            $payload['from'] ?? null,
            $payload['sender'] ?? null,
            data_get($payload, 'envelope.from'),
            data_get($payload, 'mail.from'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $value = trim($candidate);
            if ($value === '') {
                continue;
            }

            if (preg_match('/<([^>]+)>/', $value, $matches) === 1) {
                $value = trim((string) $matches[1]);
            }

            if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                return strtolower($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractSubject(array $payload): string
    {
        $subject = $payload['subject'] ?? data_get($payload, 'mail.subject') ?? '';

        return is_string($subject) ? trim($subject) : '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractBody(array $payload): string
    {
        $body = $payload['text']
            ?? $payload['body-plain']
            ?? $payload['stripped-text']
            ?? $payload['body']
            ?? '';

        return is_string($body) ? trim($body) : '';
    }
}
