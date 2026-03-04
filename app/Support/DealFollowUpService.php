<?php

namespace App\Support;

use App\Mail\DealFollowUpMail;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealEmailLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

class DealFollowUpService
{
    public const STOP_STAGE_CHANGED = 'stage_changed';
    public const STOP_CUSTOMER_REPLIED = 'customer_replied';
    public const STOP_MANUAL = 'manual_cancel';
    public const STOP_MISSING_RECIPIENT = 'missing_recipient';

    private const FOLLOW_UP_TIMEZONE = 'Europe/Lisbon';
    private const BUSINESS_START_HOUR = 9;
    private const BUSINESS_END_HOUR = 18;

    /**
     * @return array<int, array{subject: string, body: string}>
     */
    public function templates(): array
    {
        return [
            [
                'subject' => 'Follow Up - {deal_title}',
                'body' => "Olá {entity_name},\n\nSó para confirmar se já tiveste oportunidade de analisar a proposta.\nSe precisares de ajuda, estou disponível.",
            ],
            [
                'subject' => 'Alguma novidade sobre a proposta?',
                'body' => "Olá {entity_name},\n\nQueria saber se tens alguma novidade sobre a proposta enviada.\nSe fizer sentido, posso ajustar os pontos necessários.",
            ],
            [
                'subject' => 'Posso ajudar com algum esclarecimento?',
                'body' => "Olá {entity_name},\n\nEstou disponível para esclarecer qualquer dúvida sobre a proposta.\nPosso também marcar uma chamada rápida, se preferires.",
            ],
            [
                'subject' => 'Acompanhamento da proposta',
                'body' => "Olá {entity_name},\n\nPasso para acompanhar a proposta e perceber se existe algum ponto a rever.\nFico a aguardar o teu feedback.",
            ],
            [
                'subject' => 'Revisão da proposta',
                'body' => "Olá {entity_name},\n\nSe precisares, posso rever a proposta contigo e adaptar ao que for prioritário.\nDiz-me o melhor horário para falar.",
            ],
            [
                'subject' => 'Seguimento comercial',
                'body' => "Olá {entity_name},\n\nQueria confirmar se a proposta está alinhada com o que precisas.\nSe não estiver, ajustamos rapidamente.",
            ],
            [
                'subject' => 'Disponível para próximos passos',
                'body' => "Olá {entity_name},\n\nQuando tiveres disponibilidade, podemos alinhar os próximos passos da proposta.\nEstou disponível para avançar contigo.",
            ],
            [
                'subject' => 'Check-in rápido da proposta',
                'body' => "Olá {entity_name},\n\nEste é um check-in rápido para perceber se há dúvidas sobre a proposta.\nSe quiseres, respondo por email ou chamada.",
            ],
            [
                'subject' => 'A tua opinião sobre a proposta',
                'body' => "Olá {entity_name},\n\nGostava de ter o teu feedback sobre a proposta enviada.\nÉ importante para alinharmos a melhor opção.",
            ],
            [
                'subject' => 'Último follow up desta fase',
                'body' => "Olá {entity_name},\n\nFecho este ciclo de acompanhamento para não gerar ruído.\nSe fizer sentido retomar, basta responder a este email.",
            ],
        ];
    }

    public function start(Deal $deal): void
    {
        $candidate = $this->localNow()->addDays(2);
        $nextLocal = $this->nextBusinessDateTime($candidate);

        $deal->forceFill([
            'follow_up_active' => true,
            'follow_up_started_at' => now(),
            'follow_up_next_send_at' => $this->toAppTimezone($nextLocal),
            'follow_up_last_sent_at' => null,
            'follow_up_template_index' => 0,
            'follow_up_customer_replied_at' => null,
            'follow_up_stopped_at' => null,
            'follow_up_stop_reason' => null,
        ])->save();
    }

    public function stop(Deal $deal, string $reason): void
    {
        $deal->forceFill([
            'follow_up_active' => false,
            'follow_up_next_send_at' => null,
            'follow_up_stopped_at' => now(),
            'follow_up_stop_reason' => $reason,
        ])->save();
    }

    public function markCustomerReplied(Deal $deal): void
    {
        $deal->forceFill([
            'follow_up_customer_replied_at' => now(),
        ])->save();

        $this->stop($deal, self::STOP_CUSTOMER_REPLIED);
    }

    public function processDue(int $limit = 100, bool $ignoreBusinessWindow = false): int
    {
        $nowLocal = $this->localNow();
        if (! $ignoreBusinessWindow && ! $this->isWithinBusinessWindow($nowLocal)) {
            return 0;
        }

        $nowApp = $this->toAppTimezone($nowLocal);
        $templates = $this->templates();
        $templateCount = count($templates);
        $sent = 0;

        $dueDeals = Deal::query()
            ->where('follow_up_active', true)
            ->whereNotNull('follow_up_next_send_at')
            ->where('follow_up_next_send_at', '<=', $nowApp)
            ->orderBy('follow_up_next_send_at')
            ->with([
                'person:id,email',
                'entity:id,name,email',
                'entity.contacts:id,entity_id,email,status,number',
            ])
            ->limit($limit)
            ->get();

        foreach ($dueDeals as $deal) {
            if ($deal->stage !== Deal::STAGE_FOLLOW_UP) {
                $this->stop($deal, self::STOP_STAGE_CHANGED);
                continue;
            }

            if ($deal->follow_up_customer_replied_at !== null) {
                $this->stop($deal, self::STOP_CUSTOMER_REPLIED);
                continue;
            }

            $recipient = $this->resolveRecipient($deal);
            if ($recipient === null) {
                $this->stop($deal, self::STOP_MISSING_RECIPIENT);
                continue;
            }

            $index = ((int) $deal->follow_up_template_index) % max(1, $templateCount);
            $template = $templates[$index];

            $subject = $this->renderTemplate(
                $template['subject'],
                $deal
            );
            $body = $this->renderTemplate(
                $template['body'],
                $deal
            );

            try {
                Mail::to($recipient)->send(new DealFollowUpMail(
                    subjectLine: $subject,
                    bodyText: $body
                ));
            } catch (\Throwable $exception) {
                report($exception);

                // Avoid a rapid retry loop if provider is temporarily unavailable.
                $retryLocal = $this->nextBusinessDateTime($nowLocal->addHours(1));
                $deal->forceFill([
                    'follow_up_next_send_at' => $this->toAppTimezone($retryLocal),
                ])->save();
                continue;
            }

            DealEmailLog::query()->create([
                'tenant_id' => $deal->tenant_id,
                'deal_id' => $deal->id,
                'email_type' => 'follow_up',
                'to_email' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'attachment_name' => null,
                'sent_by' => $deal->owner_id,
                'sent_at' => now(),
            ]);

            $nextLocal = $this->nextBusinessDateTime($nowLocal->addDays(2));
            $deal->forceFill([
                'follow_up_last_sent_at' => now(),
                'follow_up_next_send_at' => $this->toAppTimezone($nextLocal),
                'follow_up_template_index' => ($index + 1) % max(1, $templateCount),
            ])->save();

            $sent++;
        }

        return $sent;
    }

    private function resolveRecipient(Deal $deal): ?string
    {
        $personRecipient = $this->normalizeEmail($deal->person?->email);
        if ($personRecipient !== null) {
            return $personRecipient;
        }

        $mainContactRecipient = $this->resolveEntityMainContactRecipient($deal);
        if ($mainContactRecipient !== null) {
            return $mainContactRecipient;
        }

        return $this->normalizeEmail($deal->entity?->email);
    }

    private function resolveEntityMainContactRecipient(Deal $deal): ?string
    {
        $contacts = $deal->entity?->contacts;
        if ($contacts === null || $contacts->isEmpty()) {
            return null;
        }

        $contactsWithEmail = $contacts
            ->filter(fn (Contact $contact): bool => $this->normalizeEmail($contact->email) !== null)
            ->sortBy(fn (Contact $contact): int => $contact->number !== null ? (int) $contact->number : PHP_INT_MAX)
            ->values();

        if ($contactsWithEmail->isEmpty()) {
            return null;
        }

        $activeContact = $contactsWithEmail->first(
            fn (Contact $contact): bool => strtolower((string) $contact->status) === 'active'
        );

        return $this->normalizeEmail(
            ($activeContact ?? $contactsWithEmail->first())?->email
        );
    }

    private function normalizeEmail(?string $email): ?string
    {
        $normalized = is_string($email) ? trim($email) : '';

        return $normalized !== '' ? $normalized : null;
    }

    private function renderTemplate(string $template, Deal $deal): string
    {
        $entityName = trim((string) ($deal->entity?->name ?? 'cliente'));
        $dealTitle = trim((string) $deal->title);

        return strtr($template, [
            '{entity_name}' => $entityName !== '' ? $entityName : 'cliente',
            '{deal_title}' => $dealTitle !== '' ? $dealTitle : 'negócio',
        ]);
    }

    private function localNow(): CarbonImmutable
    {
        return CarbonImmutable::now(self::FOLLOW_UP_TIMEZONE);
    }

    private function toAppTimezone(CarbonImmutable $dateTime): CarbonImmutable
    {
        $appTimezone = (string) config('app.timezone', 'UTC');

        return $dateTime->setTimezone($appTimezone);
    }

    private function nextBusinessDateTime(CarbonImmutable $candidate): CarbonImmutable
    {
        $dateTime = $candidate->setTimezone(self::FOLLOW_UP_TIMEZONE);

        while ($dateTime->isWeekend()) {
            $dateTime = $dateTime->addDay()->setTime(self::BUSINESS_START_HOUR, 0, 0);
        }

        if ((int) $dateTime->format('G') < self::BUSINESS_START_HOUR) {
            return $dateTime->setTime(self::BUSINESS_START_HOUR, 0, 0);
        }

        if ((int) $dateTime->format('G') >= self::BUSINESS_END_HOUR) {
            $dateTime = $dateTime->addDay()->setTime(self::BUSINESS_START_HOUR, 0, 0);

            while ($dateTime->isWeekend()) {
                $dateTime = $dateTime->addDay()->setTime(self::BUSINESS_START_HOUR, 0, 0);
            }
        }

        return $dateTime;
    }

    private function isWithinBusinessWindow(CarbonImmutable $dateTime): bool
    {
        if ($dateTime->isWeekend()) {
            return false;
        }

        $hour = (int) $dateTime->format('G');

        return $hour >= self::BUSINESS_START_HOUR && $hour < self::BUSINESS_END_HOUR;
    }
}
