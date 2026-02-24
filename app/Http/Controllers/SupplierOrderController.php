<?php

namespace App\Http\Controllers;

use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use Inertia\Inertia;
use Inertia\Response;

class SupplierOrderController extends Controller
{
    public function index(): Response
    {
        $supplierOrders = SupplierOrder::query()
            ->with(['supplier:id,name', 'order:id,number'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (SupplierOrder $supplierOrder): array => [
                'id' => $supplierOrder->id,
                'order_date' => $supplierOrder->order_date?->format('Y-m-d'),
                'number' => $supplierOrder->number,
                'supplier' => $supplierOrder->supplier?->name,
                'customer_order_number' => $supplierOrder->order?->number,
                'total' => (float) $supplierOrder->total,
                'status' => $supplierOrder->status,
            ]);

        return Inertia::render('supplier-orders/Index', [
            'supplierOrders' => $supplierOrders,
        ]);
    }

    public function show(SupplierOrder $supplierOrder): Response
    {
        $supplierOrder->load([
            'supplier:id,name,tax_id,address,postal_code,city,phone,mobile,email',
            'order:id,number,order_date,customer_id',
            'order.customer:id,name',
            'lines.item:id,reference,code,name,description',
            'invoices:id,number,supplier_order_id,status,total,invoice_date',
        ]);

        return Inertia::render('supplier-orders/Show', [
            'supplierOrder' => [
                'id' => $supplierOrder->id,
                'number' => $supplierOrder->number,
                'order_date' => $supplierOrder->order_date?->format('Y-m-d'),
                'status' => $supplierOrder->status,
                'total' => (float) $supplierOrder->total,
                'supplier' => $supplierOrder->supplier?->name,
                'supplier_tax_id' => $supplierOrder->supplier?->tax_id,
                'supplier_address' => $supplierOrder->supplier?->address,
                'supplier_postal_code' => $supplierOrder->supplier?->postal_code,
                'supplier_city' => $supplierOrder->supplier?->city,
                'supplier_phone' => $supplierOrder->supplier?->phone,
                'supplier_mobile' => $supplierOrder->supplier?->mobile,
                'supplier_email' => $supplierOrder->supplier?->email,
                'customer_order_id' => $supplierOrder->order?->id,
                'customer_order_number' => $supplierOrder->order?->number,
                'customer' => $supplierOrder->order?->customer?->name,
                'lines' => $supplierOrder->lines->map(fn (SupplierOrderLine $line): array => [
                    'id' => $line->id,
                    'item' => ($line->item?->reference ?: $line->item?->code ?: '-').' - '.($line->item?->name ?: $line->item?->description ?: '-'),
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'line_total' => (float) $line->line_total,
                ])->all(),
                'invoices' => $supplierOrder->invoices->map(fn ($invoice): array => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                    'total' => (float) $invoice->total,
                    'status' => $invoice->status,
                ])->all(),
            ],
        ]);
    }
}

