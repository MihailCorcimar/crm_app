<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Proposal;
use App\Models\SupplierInvoice;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantPremiumReportController extends Controller
{
    public function show(Request $request): Response
    {
        $tenant = $this->activeTenant($request);
        $this->authorize('view', $tenant);

        $now = CarbonImmutable::now();
        $windowStart = $now->startOfMonth()->subMonths(5);

        $monthlySeed = collect(range(0, 5))
            ->map(fn (int $offset): CarbonImmutable => $windowStart->addMonths($offset))
            ->mapWithKeys(fn (CarbonImmutable $month): array => [
                $month->format('Y-m') => [
                    'key' => $month->format('Y-m'),
                    'label' => $month->format('m/Y'),
                    'proposals_total' => 0.0,
                    'orders_total' => 0.0,
                    'proposals_count' => 0,
                    'orders_count' => 0,
                ],
            ]);

        $closedProposals = Proposal::query()
            ->where('status', 'closed')
            ->whereDate('proposal_date', '>=', $windowStart->toDateString())
            ->get(['id', 'proposal_date', 'total']);

        $closedOrders = Order::query()
            ->where('status', 'closed')
            ->whereDate('order_date', '>=', $windowStart->toDateString())
            ->with('customer:id,name')
            ->get(['id', 'proposal_id', 'customer_id', 'order_date', 'total']);

        $monthly = $monthlySeed->all();

        foreach ($closedProposals as $proposal) {
            if ($proposal->proposal_date === null) {
                continue;
            }

            $monthKey = $proposal->proposal_date->format('Y-m');
            if (! array_key_exists($monthKey, $monthly)) {
                continue;
            }

            $monthly[$monthKey]['proposals_total'] += (float) $proposal->total;
            $monthly[$monthKey]['proposals_count']++;
        }

        foreach ($closedOrders as $order) {
            if ($order->order_date === null) {
                continue;
            }

            $monthKey = $order->order_date->format('Y-m');
            if (! array_key_exists($monthKey, $monthly)) {
                continue;
            }

            $monthly[$monthKey]['orders_total'] += (float) $order->total;
            $monthly[$monthKey]['orders_count']++;
        }

        $closedProposalIds = $closedProposals->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $convertedProposalCount = $closedOrders
            ->pluck('proposal_id')
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->intersect($closedProposalIds)
            ->unique()
            ->count();

        $closedProposalCount = $closedProposals->count();
        $closedOrderCount = $closedOrders->count();
        $closedOrderRevenue = (float) $closedOrders->sum(fn (Order $order): float => (float) $order->total);
        $averageOrderValue = $closedOrderCount > 0
            ? round($closedOrderRevenue / $closedOrderCount, 2)
            : 0.0;
        $conversionRate = $closedProposalCount > 0
            ? round(($convertedProposalCount / $closedProposalCount) * 100, 1)
            : 0.0;

        $topCustomers = $closedOrders
            ->reduce(function (array $carry, Order $order): array {
                $customerId = $order->customer_id !== null ? (string) $order->customer_id : 'no-customer';
                $customerName = $order->customer?->name ?? 'Sem cliente';

                if (! isset($carry[$customerId])) {
                    $carry[$customerId] = [
                        'customer_id' => $order->customer_id === null ? null : (int) $order->customer_id,
                        'name' => $customerName,
                        'orders_count' => 0,
                        'total_revenue' => 0.0,
                    ];
                }

                $carry[$customerId]['orders_count']++;
                $carry[$customerId]['total_revenue'] += (float) $order->total;

                return $carry;
            }, []);

        $paidInvoices = SupplierInvoice::query()
            ->where('status', 'paid')
            ->whereDate('invoice_date', '>=', $windowStart->toDateString())
            ->get(['total']);

        $pendingInvoicesCount = SupplierInvoice::query()
            ->where('status', 'pending_payment')
            ->count();

        $overdueInvoicesCount = SupplierInvoice::query()
            ->where('status', 'pending_payment')
            ->whereDate('due_date', '<', $now->toDateString())
            ->count();

        return Inertia::render('tenants/PremiumReports', [
            'tenantDetails' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'window' => [
                'from' => $windowStart->toDateString(),
                'to' => $now->toDateString(),
            ],
            'kpis' => [
                'closed_order_revenue' => round($closedOrderRevenue, 2),
                'closed_order_count' => $closedOrderCount,
                'closed_proposal_count' => $closedProposalCount,
                'converted_proposal_count' => $convertedProposalCount,
                'conversion_rate' => min(100, max(0, $conversionRate)),
                'average_order_value' => $averageOrderValue,
                'paid_invoice_total' => round((float) $paidInvoices->sum(fn ($invoice): float => (float) $invoice->total), 2),
                'pending_invoice_count' => $pendingInvoicesCount,
                'overdue_invoice_count' => $overdueInvoicesCount,
            ],
            'monthly' => collect($monthly)
                ->values()
                ->map(function (array $item): array {
                    $item['proposals_total'] = round((float) $item['proposals_total'], 2);
                    $item['orders_total'] = round((float) $item['orders_total'], 2);

                    return $item;
                })
                ->all(),
            'topCustomers' => collect($topCustomers)
                ->sortByDesc('total_revenue')
                ->take(5)
                ->values()
                ->map(fn (array $item): array => [
                    'customer_id' => $item['customer_id'],
                    'name' => $item['name'],
                    'orders_count' => (int) $item['orders_count'],
                    'total_revenue' => round((float) $item['total_revenue'], 2),
                ])
                ->all(),
        ]);
    }

    private function activeTenant(Request $request): Tenant
    {
        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'An active tenant is required.');

        $tenant = $request->user()
            ?->tenants()
            ->where('tenants.id', $tenantId)
            ->first();

        abort_if($tenant === null, 403, 'You are not authorized for the active tenant.');

        return $tenant;
    }
}

