<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Item;
use App\Models\User;
use App\Support\DealStageService;
use App\Support\TenantContext;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DealProductStatsController extends Controller
{
    public function __construct(
        private readonly DealStageService $dealStageService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Deal::class);

        $tenantId = TenantContext::id($request) ?? 0;
        $stageOptions = $this->stageOptions($tenantId);
        $filters = $this->normalizedFilters(
            $this->validatedFilters($request, $tenantId, $stageOptions)
        );

        if (! Schema::hasTable('deal_products')) {
            return Inertia::render('deals/product-stats/Index', [
                'products' => [],
                'summary' => [
                    'total_products' => 0,
                    'total_quantity' => 0.0,
                    'total_value' => 0.0,
                ],
                'filters' => $filters,
                'owners' => $this->owners($tenantId),
                'stageOptions' => $stageOptions,
                'moduleReady' => false,
            ]);
        }

        $rows = $this->applyProductOrdering(
            $this->aggregatedProductsQuery($filters, $tenantId),
            $filters
        )->get();

        $products = $rows
            ->map(static fn (object $row): array => [
                'item_id' => (int) $row->item_id,
                'name' => (string) $row->item_name,
                'reference' => $row->reference !== null ? (string) $row->reference : null,
                'code' => $row->code !== null ? (string) $row->code : null,
                'total_quantity' => (float) $row->total_quantity,
                'total_value' => (float) $row->total_value,
                'deals_count' => (int) $row->deals_count,
            ])
            ->values()
            ->all();

        return Inertia::render('deals/product-stats/Index', [
            'products' => $products,
            'summary' => [
                'total_products' => count($products),
                'total_quantity' => $rows->sum(fn (object $row): float => (float) $row->total_quantity),
                'total_value' => $rows->sum(fn (object $row): float => (float) $row->total_value),
            ],
            'filters' => $filters,
            'owners' => $this->owners($tenantId),
            'stageOptions' => $stageOptions,
            'moduleReady' => true,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Deal::class);

        $tenantId = TenantContext::id($request) ?? 0;
        $stageOptions = $this->stageOptions($tenantId);
        $filters = $this->normalizedFilters(
            $this->validatedFilters($request, $tenantId, $stageOptions)
        );

        if (! Schema::hasTable('deal_products')) {
            abort(404);
        }

        $rows = $this->applyProductOrdering(
            $this->aggregatedProductsQuery($filters, $tenantId),
            $filters
        )->get();

        $filename = sprintf('estatisticas_produtos_negocios_%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');

            if (! is_resource($handle)) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Produto', 'Referência', 'Código', 'Quantidade total', 'Valor total', 'Negócios'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    (string) $row->item_name,
                    $row->reference !== null ? (string) $row->reference : '',
                    $row->code !== null ? (string) $row->code : '',
                    number_format((float) $row->total_quantity, 2, ',', ''),
                    number_format((float) $row->total_value, 2, ',', ''),
                    (int) $row->deals_count,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function show(Request $request, Item $item): Response
    {
        $this->authorize('viewAny', Deal::class);

        $tenantId = TenantContext::id($request) ?? 0;
        $stageOptions = $this->stageOptions($tenantId);
        $filters = $this->normalizedFilters(
            $this->validatedFilters($request, $tenantId, $stageOptions)
        );

        if (! Schema::hasTable('deal_products')) {
            abort(404);
        }

        $dealsQuery = $this->baseQuery($filters, $tenantId)
            ->leftJoin('entities', 'entities.id', '=', 'deals.entity_id')
            ->leftJoin('users as owners', 'owners.id', '=', 'deals.owner_id')
            ->where('items.id', $item->id)
            ->select([
                'deals.id as deal_id',
                'deals.title as deal_title',
                'deals.stage as deal_stage',
                'deals.value as deal_value',
                'deals.expected_close_date',
                'entities.name as entity_name',
                'owners.name as owner_name',
                DB::raw('SUM(deal_products.quantity) as total_quantity'),
                DB::raw('SUM(deal_products.total_value) as total_value'),
            ])
            ->groupBy([
                'deals.id',
                'deals.title',
                'deals.stage',
                'deals.value',
                'deals.expected_close_date',
                'entities.name',
                'owners.name',
            ]);

        $this->applyDealsOrdering($dealsQuery, $filters);

        $totalsQuery = $this->baseQuery($filters, $tenantId)
            ->where('items.id', $item->id)
            ->selectRaw('COALESCE(SUM(deal_products.quantity), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM(deal_products.total_value), 0) as total_value')
            ->selectRaw('COUNT(DISTINCT deals.id) as deals_count')
            ->first();

        $deals = $dealsQuery
            ->paginate(20)
            ->withQueryString();

        $deals->setCollection(
            $deals->getCollection()->map(static function (object $row): array {
                return [
                    'deal_id' => (int) $row->deal_id,
                    'deal_title' => (string) $row->deal_title,
                    'deal_stage' => (string) $row->deal_stage,
                    'deal_value' => (float) $row->deal_value,
                    'expected_close_date' => $row->expected_close_date !== null ? (string) $row->expected_close_date : null,
                    'entity_name' => $row->entity_name !== null ? (string) $row->entity_name : null,
                    'owner_name' => $row->owner_name !== null ? (string) $row->owner_name : null,
                    'total_quantity' => (float) $row->total_quantity,
                    'total_value' => (float) $row->total_value,
                ];
            })
        );

        return Inertia::render('deals/product-stats/Show', [
            'item' => [
                'id' => $item->id,
                'name' => $this->itemDisplayName($item),
                'reference' => $item->reference,
                'code' => $item->code,
            ],
            'totals' => [
                'total_quantity' => (float) ($totalsQuery?->total_quantity ?? 0),
                'total_value' => (float) ($totalsQuery?->total_value ?? 0),
                'deals_count' => (int) ($totalsQuery?->deals_count ?? 0),
            ],
            'deals' => $deals,
            'filters' => $filters,
            'stageOptions' => $stageOptions,
        ]);
    }

    /**
     * @param  array{owner_id: int|null, stage: string|null, expected_close_from: string|null, expected_close_to: string|null, value_min: float|null, value_max: float|null, sort_by: string, sort_direction: string}  $filters
     */
    private function aggregatedProductsQuery(array $filters, int $tenantId): QueryBuilder
    {
        return $this->baseQuery($filters, $tenantId)
            ->select([
                'items.id as item_id',
                'items.reference',
                'items.code',
                DB::raw($this->itemDisplayExpression()),
                DB::raw('SUM(deal_products.quantity) as total_quantity'),
                DB::raw('SUM(deal_products.total_value) as total_value'),
                DB::raw('COUNT(DISTINCT deals.id) as deals_count'),
            ])
            ->groupBy([
                'items.id',
                'items.name',
                'items.description',
                'items.reference',
                'items.code',
            ]);
    }

    /**
     * @param  array{owner_id: int|null, stage: string|null, expected_close_from: string|null, expected_close_to: string|null, value_min: float|null, value_max: float|null, sort_by: string, sort_direction: string}  $filters
     */
    private function baseQuery(array $filters, int $tenantId): QueryBuilder
    {
        $query = DB::table('deal_products')
            ->join('deals', 'deals.id', '=', 'deal_products.deal_id')
            ->join('items', 'items.id', '=', 'deal_products.item_id')
            ->where('deal_products.tenant_id', $tenantId)
            ->where('deals.tenant_id', $tenantId)
            ->where('items.tenant_id', $tenantId);

        if ($filters['owner_id'] !== null) {
            $query->where('deals.owner_id', $filters['owner_id']);
        }

        if ($filters['stage'] !== null) {
            $query->where('deals.stage', $filters['stage']);
        }

        if ($filters['expected_close_from'] !== null) {
            $query->whereDate('deals.expected_close_date', '>=', $filters['expected_close_from']);
        }

        if ($filters['expected_close_to'] !== null) {
            $query->whereDate('deals.expected_close_date', '<=', $filters['expected_close_to']);
        }

        if ($filters['value_min'] !== null) {
            $query->where('deals.value', '>=', $filters['value_min']);
        }

        if ($filters['value_max'] !== null) {
            $query->where('deals.value', '<=', $filters['value_max']);
        }

        return $query;
    }

    /**
     * @param  array<int, array{value: string, label: string}>  $stageOptions
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request, int $tenantId, array $stageOptions): array
    {
        $allowedStages = collect($stageOptions)
            ->map(fn (array $stage): string => (string) $stage['value'])
            ->all();

        return $request->validate([
            'owner_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($tenantId): void {
                    $query->whereExists(function ($subQuery) use ($tenantId): void {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from('tenant_user')
                            ->whereColumn('tenant_user.user_id', 'users.id')
                            ->where('tenant_user.tenant_id', $tenantId);
                    });
                }),
            ],
            'stage' => ['nullable', 'string', Rule::in($allowedStages)],
            'expected_close_from' => ['nullable', 'date'],
            'expected_close_to' => ['nullable', 'date', 'after_or_equal:expected_close_from'],
            'value_min' => ['nullable', 'numeric', 'min:0'],
            'value_max' => ['nullable', 'numeric', 'gte:value_min'],
            'sort_by' => ['nullable', 'string', Rule::in(['total_value', 'total_quantity'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{owner_id: int|null, stage: string|null, expected_close_from: string|null, expected_close_to: string|null, value_min: float|null, value_max: float|null, sort_by: string, sort_direction: string}
     */
    private function normalizedFilters(array $validated): array
    {
        return [
            'owner_id' => isset($validated['owner_id']) ? (int) $validated['owner_id'] : null,
            'stage' => isset($validated['stage']) ? (string) $validated['stage'] : null,
            'expected_close_from' => isset($validated['expected_close_from']) ? (string) $validated['expected_close_from'] : null,
            'expected_close_to' => isset($validated['expected_close_to']) ? (string) $validated['expected_close_to'] : null,
            'value_min' => isset($validated['value_min']) ? (float) $validated['value_min'] : null,
            'value_max' => isset($validated['value_max']) ? (float) $validated['value_max'] : null,
            'sort_by' => isset($validated['sort_by']) ? (string) $validated['sort_by'] : 'total_value',
            'sort_direction' => isset($validated['sort_direction']) ? (string) $validated['sort_direction'] : 'desc',
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function owners(int $tenantId): array
    {
        return User::query()
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function stageOptions(int $tenantId): array
    {
        return collect($this->dealStageService->forTenant($tenantId))
            ->map(fn (array $stage): array => [
                'value' => (string) $stage['value'],
                'label' => (string) $stage['label'],
            ])
            ->values()
            ->all();
    }

    private function itemDisplayExpression(): string
    {
        return "COALESCE(NULLIF(items.name, ''), NULLIF(items.description, ''), NULLIF(items.code, ''), CONCAT('Produto #', items.id)) as item_name";
    }

    private function itemDisplayName(Item $item): string
    {
        if (is_string($item->name) && trim($item->name) !== '') {
            return trim($item->name);
        }

        if (is_string($item->description) && trim($item->description) !== '') {
            return trim($item->description);
        }

        if (is_string($item->code) && trim($item->code) !== '') {
            return trim($item->code);
        }

        return sprintf('Produto #%d', $item->id);
    }

    /**
     * @param  array{sort_by: string, sort_direction: string}  $filters
     */
    private function applyProductOrdering(QueryBuilder $query, array $filters): QueryBuilder
    {
        $orderColumn = $filters['sort_by'] === 'total_quantity' ? 'total_quantity' : 'total_value';
        $direction = strtolower($filters['sort_direction']) === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($orderColumn, $direction)
            ->orderBy('total_value', 'desc')
            ->orderBy('item_name');
    }

    /**
     * @param  array{sort_by: string, sort_direction: string}  $filters
     */
    private function applyDealsOrdering(QueryBuilder $query, array $filters): void
    {
        $orderColumn = $filters['sort_by'] === 'total_quantity' ? 'total_quantity' : 'total_value';
        $direction = strtolower($filters['sort_direction']) === 'asc' ? 'asc' : 'desc';

        $query
            ->orderBy($orderColumn, $direction)
            ->orderBy('deals.title');
    }
}
