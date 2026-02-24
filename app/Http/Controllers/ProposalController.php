<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProposalRequest;
use App\Models\CompanySetting;
use App\Models\Entity;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Proposal;
use App\Models\ProposalLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function index(): Response
    {
        $proposals = Proposal::query()
            ->with('customer:id,name')
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Proposal $proposal): array => [
                'id' => $proposal->id,
                'proposal_date' => $proposal->proposal_date?->format('Y-m-d'),
                'number' => $proposal->number,
                'valid_until' => $proposal->valid_until->format('Y-m-d'),
                'customer' => $proposal->customer?->name,
                'total' => (float) $proposal->total,
                'status' => $proposal->status,
            ]);

        return Inertia::render('proposals/Index', [
            'proposals' => $proposals,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('proposals/Create', [
            'customers' => $this->customers(),
            'items' => $this->items(),
            'suppliers' => $this->suppliers(),
            'defaults' => [
                'proposal_date' => now()->format('Y-m-d'),
                'valid_until' => now()->addDays(30)->format('Y-m-d'),
            ],
        ]);
    }

    public function store(ProposalRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $validated = $request->validated();
            $proposalDate = $validated['proposal_date'] ?? null;

            if (($validated['status'] ?? 'draft') === 'closed' && empty($proposalDate)) {
                $proposalDate = now()->toDateString();
            }

            $proposal = Proposal::query()->create([
                'proposal_date' => $proposalDate,
                'valid_until' => $validated['valid_until'],
                'customer_id' => $validated['customer_id'],
                'status' => $validated['status'],
            ]);

            $total = $this->syncLines($proposal, $validated['lines']);
            $proposal->update(['total' => $total]);
        });

        return to_route('proposals.index');
    }

    public function show(Proposal $proposal): Response
    {
        $proposal->load(['customer:id,name', 'lines.item:id,reference,name,description', 'lines.supplier:id,name']);

        return Inertia::render('proposals/Show', [
            'proposal' => [
                'id' => $proposal->id,
                'number' => $proposal->number,
                'proposal_date' => $proposal->proposal_date?->format('Y-m-d'),
                'valid_until' => $proposal->valid_until->format('Y-m-d'),
                'customer' => $proposal->customer?->name,
                'status' => $proposal->status,
                'total' => (float) $proposal->total,
                'lines' => $proposal->lines->map(fn (ProposalLine $line): array => [
                    'id' => $line->id,
                    'item' => $line->item?->reference.' - '.$line->item?->name,
                    'supplier' => $line->supplier?->name,
                    'quantity' => (float) $line->quantity,
                    'sale_price' => (float) $line->sale_price,
                    'cost_price' => (float) $line->cost_price,
                    'line_total' => (float) $line->line_total,
                ])->all(),
            ],
        ]);
    }

    public function edit(Proposal $proposal): Response
    {
        $proposal->load('lines');

        return Inertia::render('proposals/Edit', [
            'proposal' => [
                'id' => $proposal->id,
                'number' => $proposal->number,
                'proposal_date' => $proposal->proposal_date?->format('Y-m-d'),
                'valid_until' => $proposal->valid_until->format('Y-m-d'),
                'customer_id' => $proposal->customer_id,
                'status' => $proposal->status,
                'lines' => $proposal->lines->map(fn (ProposalLine $line): array => [
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

    public function update(ProposalRequest $request, Proposal $proposal): RedirectResponse
    {
        DB::transaction(function () use ($request, $proposal): void {
            $validated = $request->validated();
            $proposalDate = $validated['proposal_date'] ?? null;

            if (($validated['status'] ?? 'draft') === 'closed' && empty($proposalDate)) {
                $proposalDate = now()->toDateString();
            }

            $proposal->update([
                'proposal_date' => $proposalDate,
                'valid_until' => $validated['valid_until'],
                'customer_id' => $validated['customer_id'],
                'status' => $validated['status'],
            ]);

            $total = $this->syncLines($proposal, $validated['lines']);
            $proposal->update(['total' => $total]);
        });

        return to_route('proposals.index');
    }

    public function destroy(Proposal $proposal): RedirectResponse
    {
        $proposal->delete();

        return to_route('proposals.index');
    }

    public function downloadPdf(Proposal $proposal)
    {
        $proposal->load(['customer:id,name,address,postal_code,city,tax_id', 'lines.item:id,reference,name', 'lines.supplier:id,name']);

        $pdf = Pdf::loadView('pdf.proposal', [
            'proposal' => $proposal,
            'company' => $this->companyPdfData(),
        ])->setPaper('a4');

        return $pdf->download('proposal-'.$proposal->number.'.pdf');
    }

    public function convertToOrder(Proposal $proposal): RedirectResponse
    {
        $proposal->load('lines');

        if ($proposal->lines->isEmpty()) {
            return to_route('proposals.show', $proposal)->withErrors([
                'proposal' => 'Cannot convert a proposal without lines.',
            ]);
        }

        DB::transaction(function () use ($proposal): void {
            $order = Order::query()->create([
                'proposal_id' => $proposal->id,
                'order_date' => $proposal->proposal_date,
                'valid_until' => $proposal->valid_until,
                'customer_id' => $proposal->customer_id,
                'total' => $proposal->total,
                'status' => 'draft',
            ]);

            foreach ($proposal->lines as $line) {
                OrderLine::query()->create([
                    'order_id' => $order->id,
                    'item_id' => $line->item_id,
                    'supplier_id' => $line->supplier_id,
                    'quantity' => $line->quantity,
                    'sale_price' => $line->sale_price,
                    'cost_price' => $line->cost_price,
                    'line_total' => $line->line_total,
                ]);
            }
        });

        return to_route('proposals.show', $proposal);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(Proposal $proposal, array $lines): float
    {
        $proposal->lines()->delete();

        $total = 0.0;

        foreach ($lines as $line) {
            $quantity = (int) $line['quantity'];
            $salePrice = (float) $line['sale_price'];
            $costPrice = (float) ($line['cost_price'] ?? 0);
            $lineTotal = round($quantity * $salePrice, 2);
            $total += $lineTotal;

            ProposalLine::query()->create([
                'proposal_id' => $proposal->id,
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
