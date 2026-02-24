<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Models\CompanySetting;
use App\Models\Entity;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(): Response
    {
        $orders = Order::query()
            ->with('customer:id,name')
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Order $order): array => [
                'id' => $order->id,
                'order_date' => $order->order_date?->format('Y-m-d'),
                'number' => $order->number,
                'valid_until' => $order->valid_until?->format('Y-m-d'),
                'customer' => $order->customer?->name,
                'total' => (float) $order->total,
                'status' => $order->status,
            ]);

        return Inertia::render('orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('orders/Create', [
            'customers' => $this->customers(),
            'items' => $this->items(),
            'suppliers' => $this->suppliers(),
            'defaults' => [
                'order_date' => now()->format('Y-m-d'),
                'valid_until' => now()->addDays(30)->format('Y-m-d'),
            ],
        ]);
    }

    public function store(OrderRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $validated = $request->validated();
            $orderDate = $validated['order_date'] ?? null;

            if (($validated['status'] ?? 'draft') === 'closed' && empty($orderDate)) {
                $orderDate = now()->toDateString();
            }

            $order = Order::query()->create([
                'order_date' => $orderDate,
                'valid_until' => $validated['valid_until'],
                'customer_id' => $validated['customer_id'],
                'status' => $validated['status'],
            ]);

            $total = $this->syncLines($order, $validated['lines']);
            $order->update(['total' => $total]);
        });

        return to_route('orders.index');
    }

    public function show(Order $order): Response
    {
        $order->load([
            'customer:id,name',
            'lines.item:id,reference,name,description',
            'lines.supplier:id,name',
            'supplierOrders.supplier:id,name',
        ]);

        return Inertia::render('orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'order_date' => $order->order_date?->format('Y-m-d'),
                'valid_until' => $order->valid_until?->format('Y-m-d'),
                'customer' => $order->customer?->name,
                'status' => $order->status,
                'total' => (float) $order->total,
                'lines' => $order->lines->map(fn (OrderLine $line): array => [
                    'id' => $line->id,
                    'item' => $line->item?->reference.' - '.$line->item?->name,
                    'supplier' => $line->supplier?->name,
                    'quantity' => (float) $line->quantity,
                    'sale_price' => (float) $line->sale_price,
                    'cost_price' => (float) $line->cost_price,
                    'line_total' => (float) $line->line_total,
                ])->all(),
                'supplier_orders' => $order->supplierOrders->map(fn (SupplierOrder $supplierOrder): array => [
                    'id' => $supplierOrder->id,
                    'number' => $supplierOrder->number,
                    'supplier' => $supplierOrder->supplier?->name,
                    'order_date' => $supplierOrder->order_date?->format('Y-m-d'),
                    'total' => (float) $supplierOrder->total,
                    'status' => $supplierOrder->status,
                ])->all(),
            ],
        ]);
    }

    public function edit(Order $order): Response
    {
        $order->load('lines');

        return Inertia::render('orders/Edit', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'order_date' => $order->order_date?->format('Y-m-d'),
                'valid_until' => $order->valid_until?->format('Y-m-d'),
                'customer_id' => $order->customer_id,
                'status' => $order->status,
                'lines' => $order->lines->map(fn (OrderLine $line): array => [
                    'item_id' => $line->item_id,
                    'supplier_id' => $line->supplier_id,
                    'quantity' => (float) $line->quantity,
                    'sale_price' => (float) $line->sale_price,
                    'cost_price' => (float) $line->cost_price,
                ])->all(),
            ],
            'customers' => $this->customers(),
            'items' => $this->items(),
            'suppliers' => $this->suppliers(),
        ]);
    }

    public function update(OrderRequest $request, Order $order): RedirectResponse
    {
        DB::transaction(function () use ($request, $order): void {
            $validated = $request->validated();
            $orderDate = $validated['order_date'] ?? null;

            if (($validated['status'] ?? 'draft') === 'closed' && empty($orderDate)) {
                $orderDate = now()->toDateString();
            }

            $order->update([
                'order_date' => $orderDate,
                'valid_until' => $validated['valid_until'],
                'customer_id' => $validated['customer_id'],
                'status' => $validated['status'],
            ]);

            $total = $this->syncLines($order, $validated['lines']);
            $order->update(['total' => $total]);
        });

        return to_route('orders.index');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return to_route('orders.index');
    }

    public function downloadPdf(Order $order)
    {
        $order->load([
            'customer:id,name,address,postal_code,city,tax_id',
            'lines.item:id,reference,name',
            'lines.supplier:id,name',
        ]);

        $pdf = Pdf::loadView('pdf.order', [
            'order' => $order,
            'company' => $this->companyPdfData(),
        ])->setPaper('a4');

        return $pdf->download('order-'.$order->number.'.pdf');
    }

    public function convertToSupplierOrders(Order $order): RedirectResponse
    {
        $order->load(['lines', 'supplierOrders']);

        if ($order->status !== 'closed') {
            return to_route('orders.show', $order)->withErrors([
                'order' => 'Only closed orders can be converted to supplier orders.',
            ]);
        }

        if ($order->lines->isEmpty()) {
            return to_route('orders.show', $order)->withErrors([
                'order' => 'Cannot convert an order without lines.',
            ]);
        }

        if ($order->lines->contains(fn (OrderLine $line): bool => empty($line->supplier_id))) {
            return to_route('orders.show', $order)->withErrors([
                'order' => 'All order lines must have a supplier before conversion.',
            ]);
        }

        if ($order->supplierOrders->isNotEmpty()) {
            return to_route('orders.show', $order)->withErrors([
                'order' => 'This order has already been converted to supplier orders.',
            ]);
        }

        DB::transaction(function () use ($order): void {
            $groupedLines = $order->lines->groupBy('supplier_id');

            foreach ($groupedLines as $supplierId => $lines) {
                $supplierOrder = SupplierOrder::query()->create([
                    'order_id' => $order->id,
                    'supplier_id' => (int) $supplierId,
                    'order_date' => $order->order_date,
                    'status' => 'draft',
                    'total' => 0,
                ]);

                $total = 0.0;

                foreach ($lines as $line) {
                    $quantity = (float) $line->quantity;
                    $unitPrice = (float) ($line->cost_price > 0 ? $line->cost_price : $line->sale_price);
                    $lineTotal = round($quantity * $unitPrice, 2);
                    $total += $lineTotal;

                    SupplierOrderLine::query()->create([
                        'supplier_order_id' => $supplierOrder->id,
                        'order_line_id' => $line->id,
                        'item_id' => $line->item_id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ]);
                }

                $supplierOrder->update([
                    'total' => round($total, 2),
                ]);
            }
        });

        return to_route('orders.show', $order);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(Order $order, array $lines): float
    {
        $order->lines()->delete();

        $total = 0.0;

        foreach ($lines as $line) {
            $quantity = (int) $line['quantity'];
            $salePrice = (float) $line['sale_price'];
            $costPrice = (float) ($line['cost_price'] ?? 0);
            $lineTotal = round($quantity * $salePrice, 2);
            $total += $lineTotal;

            OrderLine::query()->create([
                'order_id' => $order->id,
                'item_id' => (int) $line['item_id'],
                'supplier_id' => ! empty($line['supplier_id']) ? (int) $line['supplier_id'] : null,
                'quantity' => $quantity,
                'sale_price' => $salePrice,
                'cost_price' => $costPrice,
                'line_total' => $lineTotal,
            ]);
        }

        return round($total, 2);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function customers(): array
    {
        return Entity::query()
            ->whereIn('type', ['customer', 'both'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Entity $entity): array => [
                'id' => $entity->id,
                'name' => $entity->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function suppliers(): array
    {
        return Entity::query()
            ->whereIn('type', ['supplier', 'both'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Entity $entity): array => [
                'id' => $entity->id,
                'name' => $entity->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, reference: string, name: string, description: string|null, price: float}>
     */
    private function items(): array
    {
        return Item::query()
            ->where('status', 'active')
            ->orderBy('reference')
            ->get(['id', 'reference', 'code', 'name', 'description', 'price'])
            ->map(fn (Item $item): array => [
                'id' => $item->id,
                'reference' => $item->reference ?: $item->code,
                'name' => $item->name ?: $item->description,
                'description' => $item->description,
                'price' => (float) $item->price,
            ])
            ->all();
    }

    /**
     * @return array{name: string, address: string|null, postal_code: string|null, city: string|null, tax_number: string|null, logo_data_uri: string|null}
     */
    private function companyPdfData(): array
    {
        $company = CompanySetting::query()->firstOrCreate(
            ['id' => 1],
            ['name' => 'App de Gestao']
        );

        $logoDataUri = null;
        if ($company->logo_path && Storage::disk('local')->exists($company->logo_path)) {
            $logoPath = Storage::disk('local')->path($company->logo_path);
            $logoContents = @file_get_contents($logoPath);

            if ($logoContents !== false) {
                $mimeType = @mime_content_type($logoPath) ?: 'image/png';
                $logoDataUri = 'data:'.$mimeType.';base64,'.base64_encode($logoContents);
            }
        }

        return [
            'name' => $company->name ?: 'App de Gestao',
            'address' => $company->address,
            'postal_code' => $company->postal_code,
            'city' => $company->city,
            'tax_number' => $company->tax_number,
            'logo_data_uri' => $logoDataUri,
        ];
    }
}
