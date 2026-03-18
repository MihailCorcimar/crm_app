<?php

namespace App\Services\Ai;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiSecureQueryService
{
    /**
     * @param  array{
     *   intent: string,
     *   confidence: float,
     *   parameters: array{stage: string|null, name: string|null, field: string|null, entity_name: string|null}
     * }  $resolvedIntent
     * @return array{answer: string, data: array<string, mixed>}
     */
    public function execute(array $resolvedIntent, User $user, int $tenantId): array
    {
        $this->assertTenantAccess($user, $tenantId);

        return match ($resolvedIntent['intent']) {
            'deal_summary' => $this->dealSummary(
                $tenantId,
                $resolvedIntent['parameters']['stage'],
                $resolvedIntent['parameters']['entity_name'],
                $user
            ),
            'contact_lookup' => $this->contactLookup(
                $tenantId,
                $resolvedIntent['parameters']['name'],
                $resolvedIntent['parameters']['field'],
                $user
            ),
            'entity_contacts' => $this->entityContacts(
                $tenantId,
                $resolvedIntent['parameters']['entity_name'],
                $user
            ),
            'entity_contacts_deal_products' => $this->entityContactsDealProducts(
                $tenantId,
                $resolvedIntent['parameters']['entity_name'],
                $user
            ),
            default => [
                'answer' => 'Ainda não consigo responder a esse tipo de pergunta. Podes perguntar por volume de negócios, negócios por entidade, contactos de uma entidade, ou contactos (telefone, telemóvel, email).',
                'data' => [
                    'type' => 'unsupported',
                ],
            ],
        };
    }

    /**
     * @return array{answer: string, data: array<string, mixed>}
     */
    private function dealSummary(int $tenantId, ?string $stage, ?string $entityName, User $user): array
    {
        if (! $user->can('viewAny', Deal::class)) {
            throw new AuthorizationException('You do not have permission to read deals.');
        }

        $baseQuery = Deal::query()->where('tenant_id', $tenantId);

        if ($stage !== null) {
            $baseQuery->where('stage', $stage);
        }

        if ($entityName !== null && trim($entityName) !== '') {
            $baseQuery->whereHas('entity', function ($query) use ($entityName): void {
                $query->where('name', 'like', "%{$entityName}%");
            });
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
        $byStage = (clone $baseQuery)
            ->selectRaw('stage, COUNT(*) as count_rows, COALESCE(SUM(value), 0) as total_value')
            ->groupBy('stage')
            ->orderBy('stage')
            ->get();

        $stageLabel = $stage === null ? 'todas as etapas' : $this->stageLabel($stage);
        $entityFilterLabel = ($entityName === null || trim($entityName) === '')
            ? null
            : "entidade {$entityName}";
        $filterLabel = collect([$stageLabel, $entityFilterLabel])->filter()->implode(' | ');

        if ($count === 0) {
            return [
                'answer' => "Não existem negócios para o filtro {$filterLabel}.",
                'data' => [
                    'type' => 'deal_summary',
                    'stage' => $stage,
                    'entity_name' => $entityName,
                    'count' => 0,
                    'total' => 0,
                    'by_stage' => $this->dealStageRows($byStage),
                    'top_deals' => [],
                ],
            ];
        }

        $amountLabel = number_format($total, 2, ',', '.');

        return [
            'answer' => "Existem {$count} negócios ({$filterLabel}) no valor total de {$amountLabel} EUR.",
            'data' => [
                'type' => 'deal_summary',
                'stage' => $stage,
                'entity_name' => $entityName,
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
                'answer' => 'Preciso do nome do contacto e do campo pretendido (telefone, telemóvel ou email).',
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
                'answer' => "Não encontrei nenhum contacto com o nome '{$name}' no tenant ativo.",
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
            'mobile' => 'telemóvel',
            'phone' => 'telefone',
            'email' => 'email',
            default => 'campo',
        };

        if ($value === null || trim((string) $value) === '') {
            return [
                'answer' => "O contacto {$fullName} não tem {$fieldLabel} registado.",
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
            'answer' => "O {$fieldLabel} de {$fullName} é {$value}.",
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

    /**
     * @return array{answer: string, data: array<string, mixed>}
     */
    private function entityContacts(int $tenantId, ?string $entityName, User $user): array
    {
        if (! $user->can('viewAny', Contact::class)) {
            throw new AuthorizationException('You do not have permission to read contacts.');
        }

        if ($entityName === null || trim($entityName) === '') {
            return [
                'answer' => 'Preciso do nome da entidade para listar as pessoas de contacto.',
                'data' => [
                    'type' => 'entity_contacts',
                    'found' => false,
                ],
            ];
        }

        $entity = Entity::query()
            ->where('tenant_id', $tenantId)
            ->where('name', 'like', "%{$entityName}%")
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$entityName])
            ->orderBy('name')
            ->first(['id', 'name']);

        if ($entity === null) {
            return [
                'answer' => "Não encontrei a entidade '{$entityName}' no tenant ativo.",
                'data' => [
                    'type' => 'entity_contacts',
                    'found' => false,
                    'entity_name' => $entityName,
                ],
            ];
        }

        $contacts = Contact::query()
            ->where('tenant_id', $tenantId)
            ->where('entity_id', $entity->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'mobile', 'phone']);

        if ($contacts->isEmpty()) {
            return [
                'answer' => "A entidade {$entity->name} não tem pessoas de contacto associadas.",
                'data' => [
                    'type' => 'entity_contacts',
                    'found' => true,
                    'entity' => [
                        'id' => $entity->id,
                        'name' => $entity->name,
                    ],
                    'count' => 0,
                    'contacts' => [],
                ],
            ];
        }

        $contactRows = $contacts->map(function (Contact $contact): array {
            $fullName = trim($contact->first_name.' '.($contact->last_name ?? ''));

            return [
                'id' => $contact->id,
                'name' => $fullName,
                'email' => $contact->email,
                'mobile' => $contact->mobile,
                'phone' => $contact->phone,
            ];
        })->values()->all();

        $previewNames = collect($contactRows)
            ->take(5)
            ->pluck('name')
            ->filter(fn (?string $value): bool => is_string($value) && $value !== '')
            ->implode(', ');

        $count = count($contactRows);
        $suffix = $count > 5 ? '...' : '.';

        return [
            'answer' => "A entidade {$entity->name} tem {$count} pessoa(s) de contacto: {$previewNames}{$suffix}",
            'data' => [
                'type' => 'entity_contacts',
                'found' => true,
                'entity' => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                ],
                'count' => $count,
                'contacts' => $contactRows,
            ],
        ];
    }

    /**
     * @return array{answer: string, data: array<string, mixed>}
     */
    private function entityContactsDealProducts(int $tenantId, ?string $entityName, User $user): array
    {
        if (! $user->can('viewAny', Contact::class) || ! $user->can('viewAny', Deal::class)) {
            throw new AuthorizationException('You do not have permission to read entity contacts and deal products.');
        }

        if ($entityName === null || trim($entityName) === '') {
            return [
                'answer' => 'Preciso do nome da entidade para listar contactos e produtos associados aos negócios.',
                'data' => [
                    'type' => 'entity_contacts_deal_products',
                    'found' => false,
                ],
            ];
        }

        $entity = Entity::query()
            ->where('tenant_id', $tenantId)
            ->where('name', 'like', "%{$entityName}%")
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$entityName])
            ->orderBy('name')
            ->first(['id', 'name']);

        if ($entity === null) {
            return [
                'answer' => "Não encontrei a entidade '{$entityName}' no tenant ativo.",
                'data' => [
                    'type' => 'entity_contacts_deal_products',
                    'found' => false,
                    'entity_name' => $entityName,
                ],
            ];
        }

        $contacts = Contact::query()
            ->where('tenant_id', $tenantId)
            ->where('entity_id', $entity->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'mobile', 'phone']);

        $contactRows = $contacts->map(function (Contact $contact): array {
            return [
                'id' => $contact->id,
                'name' => trim($contact->first_name.' '.($contact->last_name ?? '')),
                'email' => $contact->email,
                'mobile' => $contact->mobile,
                'phone' => $contact->phone,
            ];
        })->values()->all();

        $deals = Deal::query()
            ->where('tenant_id', $tenantId)
            ->where('entity_id', $entity->id)
            ->orderByDesc('value')
            ->get(['id', 'title', 'stage', 'value']);

        $dealIds = $deals->pluck('id');
        $topDeals = $deals
            ->take(5)
            ->map(fn (Deal $deal): array => [
                'id' => $deal->id,
                'title' => $deal->title,
                'stage' => $deal->stage,
                'stage_label' => $this->stageLabel($deal->stage),
                'value' => round((float) $deal->value, 2),
            ])
            ->values()
            ->all();

        $productRows = collect();
        if ($dealIds->isNotEmpty()) {
            $productRows = DB::table('deal_products')
                ->join('items', 'items.id', '=', 'deal_products.item_id')
                ->where('deal_products.tenant_id', $tenantId)
                ->whereIn('deal_products.deal_id', $dealIds->all())
                ->groupBy('items.id', 'items.name')
                ->orderByDesc(DB::raw('SUM(deal_products.total_value)'))
                ->select([
                    'items.id',
                    'items.name',
                    DB::raw('SUM(deal_products.quantity) as total_quantity'),
                    DB::raw('SUM(deal_products.total_value) as total_value'),
                    DB::raw('COUNT(DISTINCT deal_products.deal_id) as deals_count'),
                ])
                ->limit(8)
                ->get()
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'total_quantity' => round((float) $row->total_quantity, 2),
                    'total_value' => round((float) $row->total_value, 2),
                    'deals_count' => (int) $row->deals_count,
                ]);
        }

        $contactsCount = count($contactRows);
        $dealsCount = $deals->count();
        $productsCount = $productRows->count();

        $contactsPreview = collect($contactRows)->take(4)->pluck('name')->filter()->implode(', ');
        $productsPreview = $productRows
            ->take(4)
            ->map(fn (array $product): string => $product['name'])
            ->implode(', ');

        $answerParts = [];
        $answerParts[] = "A entidade {$entity->name} tem {$contactsCount} contacto(s)";
        $answerParts[] = "e {$dealsCount} negócio(s)";
        $answerParts[] = "com {$productsCount} produto(s) associado(s).";

        if ($contactsCount > 0 && $contactsPreview !== '') {
            $answerParts[] = "Contactos: {$contactsPreview}".($contactsCount > 4 ? '...' : '.');
        }

        if ($productsCount > 0 && $productsPreview !== '') {
            $answerParts[] = "Produtos: {$productsPreview}".($productsCount > 4 ? '...' : '.');
        }

        if ($dealsCount === 0) {
            $answerParts[] = 'Não existem negócios desta entidade para calcular produtos associados.';
        } elseif ($productsCount === 0) {
            $answerParts[] = 'Os negócios desta entidade ainda não têm produtos associados.';
        }

        return [
            'answer' => implode(' ', $answerParts),
            'data' => [
                'type' => 'entity_contacts_deal_products',
                'found' => true,
                'entity' => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                ],
                'count_contacts' => $contactsCount,
                'count_deals' => $dealsCount,
                'count_products' => $productsCount,
                'contacts' => $contactRows,
                'products' => $productRows->values()->all(),
                'top_deals' => $topDeals,
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
            Deal::STAGE_NEGOTIATION => 'Negociação',
            Deal::STAGE_FOLLOW_UP => 'Follow Up',
            Deal::STAGE_WON => 'Ganho',
            Deal::STAGE_LOST => 'Perdido',
            default => $stage,
        };
    }
}
