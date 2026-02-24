<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierInvoiceRequest;
use App\Mail\SupplierInvoicePaymentProofMail;
use App\Models\Entity;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SupplierInvoiceController extends Controller
{
    public function index(): Response
    {
        $invoices = SupplierInvoice::query()
            ->with(['supplier:id,name', 'supplierOrder:id,number'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (SupplierInvoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'number' => $invoice->number,
                'supplier' => $invoice->supplier?->name,
                'supplier_order' => $invoice->supplierOrder?->number,
                'document_url' => $invoice->document_path ? route('supplier-invoices.document', $invoice) : null,
                'total' => (float) $invoice->total,
                'status' => $invoice->status,
            ]);

        return Inertia::render('supplier-invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('supplier-invoices/Create', [
            'suppliers' => $this->suppliers(),
            'supplierOrders' => $this->supplierOrders(),
            'defaults' => [
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
            ],
        ]);
    }

    public function store(SupplierInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (
            ($validated['status'] ?? 'pending_payment') === 'paid'
            && ! $request->hasFile('payment_proof')
        ) {
            return back()->withErrors([
                'payment_proof' => 'Comprovativo de pagamento obrigatorio quando a fatura esta paga.',
            ]);
        }

        $invoice = SupplierInvoice::query()->create([
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'supplier_id' => $validated['supplier_id'],
            'supplier_order_id' => $validated['supplier_order_id'] ?? null,
            'total' => $validated['total'],
            'status' => $validated['status'],
        ]);

        $this->storeUploadedFile($request, $invoice, 'document', 'document_path', 'supplier-invoices/documents');
        $this->storeUploadedFile($request, $invoice, 'payment_proof', 'payment_proof_path', 'supplier-invoices/payment-proofs');

        $this->sendPaymentProofEmailIfRequested($invoice->fresh(['supplier']), $request->boolean('send_payment_proof_email'));

        return to_route('supplier-invoices.index');
    }

    public function show(SupplierInvoice $supplierInvoice): Response
    {
        $supplierInvoice->load(['supplier:id,name,email', 'supplierOrder:id,number']);

        return Inertia::render('supplier-invoices/Show', [
            'invoice' => [
                'id' => $supplierInvoice->id,
                'number' => $supplierInvoice->number,
                'invoice_date' => $supplierInvoice->invoice_date?->format('Y-m-d'),
                'due_date' => $supplierInvoice->due_date?->format('Y-m-d'),
                'supplier_id' => $supplierInvoice->supplier_id,
                'supplier' => $supplierInvoice->supplier?->name,
                'supplier_email' => $supplierInvoice->supplier?->email,
                'supplier_order_id' => $supplierInvoice->supplier_order_id,
                'supplier_order' => $supplierInvoice->supplierOrder?->number,
                'total' => (float) $supplierInvoice->total,
                'status' => $supplierInvoice->status,
                'document_url' => $supplierInvoice->document_path ? route('supplier-invoices.document', $supplierInvoice) : null,
                'payment_proof_url' => $supplierInvoice->payment_proof_path ? route('supplier-invoices.payment-proof', $supplierInvoice) : null,
            ],
        ]);
    }

    public function edit(SupplierInvoice $supplierInvoice): Response
    {
        return Inertia::render('supplier-invoices/Edit', [
            'invoice' => [
                'id' => $supplierInvoice->id,
                'number' => $supplierInvoice->number,
                'invoice_date' => $supplierInvoice->invoice_date?->format('Y-m-d'),
                'due_date' => $supplierInvoice->due_date?->format('Y-m-d'),
                'supplier_id' => $supplierInvoice->supplier_id,
                'supplier_order_id' => $supplierInvoice->supplier_order_id,
                'total' => (float) $supplierInvoice->total,
                'status' => $supplierInvoice->status,
                'document_url' => $supplierInvoice->document_path ? route('supplier-invoices.document', $supplierInvoice) : null,
                'payment_proof_url' => $supplierInvoice->payment_proof_path ? route('supplier-invoices.payment-proof', $supplierInvoice) : null,
            ],
            'suppliers' => $this->suppliers(),
            'supplierOrders' => $this->supplierOrders(),
        ]);
    }

    public function update(SupplierInvoiceRequest $request, SupplierInvoice $supplierInvoice): RedirectResponse
    {
        $validated = $request->validated();

        $willBePaid = ($validated['status'] ?? 'pending_payment') === 'paid';
        $hasStoredPaymentProof = ! empty($supplierInvoice->payment_proof_path);
        $hasNewPaymentProof = $request->hasFile('payment_proof');

        if ($willBePaid && ! $hasStoredPaymentProof && ! $hasNewPaymentProof) {
            return back()->withErrors([
                'payment_proof' => 'Comprovativo de pagamento obrigatorio quando a fatura esta paga.',
            ]);
        }

        $supplierInvoice->update([
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'supplier_id' => $validated['supplier_id'],
            'supplier_order_id' => $validated['supplier_order_id'] ?? null,
            'total' => $validated['total'],
            'status' => $validated['status'],
        ]);

        $this->storeUploadedFile($request, $supplierInvoice, 'document', 'document_path', 'supplier-invoices/documents');
        $this->storeUploadedFile($request, $supplierInvoice, 'payment_proof', 'payment_proof_path', 'supplier-invoices/payment-proofs');

        $this->sendPaymentProofEmailIfRequested($supplierInvoice->fresh(['supplier']), $request->boolean('send_payment_proof_email'));

        return to_route('supplier-invoices.index');
    }

    public function destroy(SupplierInvoice $supplierInvoice): RedirectResponse
    {
        if ($supplierInvoice->document_path) {
            Storage::disk('local')->delete($supplierInvoice->document_path);
        }

        if ($supplierInvoice->payment_proof_path) {
            Storage::disk('local')->delete($supplierInvoice->payment_proof_path);
        }

        $supplierInvoice->delete();

        return to_route('supplier-invoices.index');
    }

    public function document(SupplierInvoice $supplierInvoice)
    {
        abort_unless($supplierInvoice->document_path, 404);
        abort_unless(Storage::disk('local')->exists($supplierInvoice->document_path), 404);

        return Storage::disk('local')->response($supplierInvoice->document_path);
    }

    public function paymentProof(SupplierInvoice $supplierInvoice)
    {
        abort_unless($supplierInvoice->payment_proof_path, 404);
        abort_unless(Storage::disk('local')->exists($supplierInvoice->payment_proof_path), 404);

        return Storage::disk('local')->response($supplierInvoice->payment_proof_path);
    }

    private function storeUploadedFile(
        Request $request,
        SupplierInvoice $supplierInvoice,
        string $field,
        string $pathColumn,
        string $directory
    ): void {
        if (! $request->hasFile($field)) {
            return;
        }

        $currentPath = (string) $supplierInvoice->getAttribute($pathColumn);
        if ($currentPath !== '') {
            Storage::disk('local')->delete($currentPath);
        }

        $path = $request->file($field)->store($directory, 'local');
        $supplierInvoice->update([$pathColumn => $path]);
    }

    private function sendPaymentProofEmailIfRequested(SupplierInvoice $supplierInvoice, bool $shouldSend): void
    {
        if (! $shouldSend || $supplierInvoice->status !== 'paid') {
            return;
        }

        if (! $supplierInvoice->payment_proof_path || ! $supplierInvoice->supplier?->email) {
            return;
        }

        Mail::to($supplierInvoice->supplier->email)->send(
            new SupplierInvoicePaymentProofMail($supplierInvoice)
        );

        $supplierInvoice->update([
            'proof_emailed_at' => now(),
        ]);
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
     * @return array<int, array{id: int, number: int, supplier_id: int, supplier: string, total: float, order_date: string|null, status: string}>
     */
    private function supplierOrders(): array
    {
        return SupplierOrder::query()
            ->with('supplier:id,name')
            ->orderByDesc('id')
            ->get(['id', 'number', 'supplier_id', 'order_date', 'total', 'status'])
            ->map(fn (SupplierOrder $supplierOrder): array => [
                'id' => $supplierOrder->id,
                'number' => $supplierOrder->number,
                'supplier_id' => $supplierOrder->supplier_id,
                'supplier' => $supplierOrder->supplier?->name ?? '-',
                'total' => (float) $supplierOrder->total,
                'order_date' => $supplierOrder->order_date?->format('Y-m-d'),
                'status' => $supplierOrder->status,
            ])
            ->all();
    }
}

