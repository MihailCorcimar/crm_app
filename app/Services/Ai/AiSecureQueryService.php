<?php

namespace App\Services\Ai;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class AiSecureQueryService
{
    /**
     * @param  array{intent: string, confidence: float, parameters: array<string, string|null>}  $resolvedIntent
     * @return array{answer: string, data: array<string, mixed>}
     */
    public function execute(array $resolvedIntent, User $user, int $tenantId): array
    {
        $this->assertTenantAccess($user, $tenantId);

        return match ($resolvedIntent['intent']) {
            'deal_summary' => $this->dealSummary(
                $tenantId,
                $resolvedIntent['parameters']['stage'],
                $user
            ),
            'contact_lookup' => $this->contactLookup(
                $tenantId,
                $resolvedIntent['parameters']['name'],
                $resolvedIntent['parameters']['field'],
                $user
            ),
            default => [
                'answer' => 'Ainda nao consigo responder a esse tipo de pergunta. Podes perguntar por volume de negocios ou contacto (telefone, telemovel, email).',
                'data' => [
                    'type' => 'unsupported',
                ],
            ],
        };
    }

    /**
     * @return array{answer: string, data: array<string, mixed>}
     */
    private function dealSummary(int $tenantId, ?string $stage, User $user): array
    {
        if (! $user->can('viewAny', Deal::class)) {
            throw new AuthorizationException('You do not have permission to read deals.');
        }

        $baseQuery = Deal::query()->where('tenant_id', $tenantId);

        if ($stage !== null) {
            $baseQuery->where('stage', $stage);
        }

        $count = (clone $baseQuery)->count();
        $total = (float) (clone $baseQuery)->sum('value');
        $topDeals = (clone $baseQuery)
            ->orderByDesc('value')
            ->limit(5)
            ->get(['id', 'title', 'stage', 'value'])
            ->map(fn (Deal $deal): array => [
                'id' => $deal->id,
                'title' => $deal->title,
                'stage' => $deal->stage,
                'stage_label' => $this->stageLabel($deal->stage),
                'value' => round((float) $deal->value, 2),
            ])
            ->all();

        /** @var Collection<int, object{stage: string, count_rows: int|string, total_value: float|string}> $byStage */
        $byStage = Deal::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('stage, COUNT(*) as count_rows, COALESCE(SUM(value), 0) as total_value')
            ->groupBy('stage')
            ->orderBy('stage')
            ->get();

        $stageLabel = $stage === null ? 'todas as etapas' : $this->stageLabel($stage);

        if ($count === 0) {
            return [
                'answer' => "Nao existem negocios para o filtro {$stageLabel}.",
                'data' => [
                    'type' => 'deal_summary',
                    'stage' => $stage,
                    'count' => 0,
                    'total' => 0,
                    'by_stage' => $this->dealStageRows($byStage),
                    'top_deals' => [],
                ],
            ];
        }

        $amountLabel = number_format($total, 2, ',', '.');

        return [
            'answer' => "Existem {$count} negocios ({$stageLabel}) no valor total de {$amountLabel} EUR.",
            'data' => [
                'type' => 'deal_summary',
                'stage' => $stage,
                'count' => $count,
                'total' => round($total, 2),
                'by_stage' => $this->dealStageRows($byStage),
                'top_deals' => $topDeals,
            ],
        ];
    }

    /**
     * @return array<int, array{stage: string, count: int, total: float, stage_label: string}>
     */
    private function dealStageRows(Collection $rows): array
    {
        return $rows
            ->map(fn (object $row): array => [
                'stage' => (string) $row->stage,
                'stage_label' => $this->stageLabel((string) $row->stage),
                'count' => (int) $row->count_rows,
                'total' => round((float) $row->total_value, 2),
            ])
            ->all();
    }

    /**
     * @return array{answer: string, data: array<string, mixed>}
     */
    private function contactLookup(int $tenantId, ?string $name, ?string $field, User $user): array
    {
        if (! $user->can('viewAny', Contact::class)) {
            throw new AuthorizationException('You do not have permission to read contacts.');
        }

        if ($name === null || $field === null) {
            return [
                'answer' => 'Preciso do nome do contacto e do campo pretendido (telefone, telemovel ou email).',
                'data' => [
                    'type' => 'contact_lookup',
                    'found' => false,
                ],
            ];
        }

        $contact = Contact::query()
            ->where('tenant_id', $tenantId)
            ->with('entity:id,name')
            ->where(function ($query) use ($name): void {
                $query->whereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$name}%"])
                    ->orWhere('first_name', 'like', "%{$name}%")
                    ->orWhere('last_name', 'like', "%{$name}%");
            })
            ->orderBy('first_name')
            ->first();

        if ($contact === null) {
            return [
                'answer' => "Nao encontrei nenhum contacto com o nome '{$name}' no tenant ativo.",
                'data' => [
                    'type' => 'contact_lookup',
                    'found' => false,
                    'name' => $name,
                    'field' => $field,
                ],
            ];
        }

        $value = match ($field) {
            'mobile' => $contact->mobile,
            'phone' => $contact->phone,
            'email' => $contact->email,
            default => null,
        };

        $fullName = trim($contact->first_name.' '.($contact->last_name ?? ''));
        $fieldLabel = match ($field) {
            'mobile' => 'telemovel',
            'phone' => 'telefone',
            'email' => 'email',
            default => 'campo',
        };

        if ($value === null || trim((string) $value) === '') {
            return [
                'answer' => "O contacto {$fullName} nao tem {$fieldLabel} registado.",
                'data' => [
                    'type' => 'contact_lookup',
                    'found' => true,
                    'field' => $field,
                    'value' => null,
                    'contact' => [
                        'id' => $contact->id,
                        'name' => $fullName,
                        'entity' => $contact->entity?->name,
                    ],
                ],
            ];
        }

        return [
            'answer' => "O {$fieldLabel} de {$fullName} e {$value}.",
            'data' => [
                'type' => 'contact_lookup',
                'found' => true,
                'field' => $field,
                'value' => (string) $value,
                'contact' => [
                    'id' => $contact->id,
                    'name' => $fullName,
                    'entity' => $contact->entity?->name,
                ],
            ],
        ];
    }

    private function assertTenantAccess(User $user, int $tenantId): void
    {
        if ((string) $user->status !== '' && $user->status !== 'active') {
            throw new AuthorizationException('Your account is inactive.');
        }

        $belongsToTenant = $user->tenants()
            ->where('tenants.id', $tenantId)
            ->exists();

        if (! $belongsToTenant) {
            throw new AuthorizationException('You do not have access to the active tenant.');
        }
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            Deal::STAGE_LEAD => 'Lead',
            Deal::STAGE_PROPOSAL => 'Proposta',
            Deal::STAGE_NEGOTIATION => 'Negociacao',
            Deal::STAGE_FOLLOW_UP => 'Follow Up',
            Deal::STAGE_WON => 'Ganho',
            Deal::STAGE_LOST => 'Perdido',
            default => $stage,
        };
    }
}
