<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import SupplierInvoiceForm from '@/components/supplier-invoices/SupplierInvoiceForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type SupplierOption = { id: number; name: string };
type SupplierOrderOption = {
    id: number;
    number: number;
    supplier_id: number;
    supplier: string;
    total: number;
    order_date: string | null;
    status: string;
};

type InvoicePayload = {
    id: number;
    number: number;
    invoice_date: string;
    due_date: string;
    supplier_id: number;
    supplier_order_id: number | null;
    total: number;
    status: 'pending_payment' | 'paid';
    document_url: string | null;
    payment_proof_url: string | null;
};

const props = defineProps<{
    invoice: InvoicePayload;
    suppliers: SupplierOption[];
    supplierOrders: SupplierOrderOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Faturas Fornecedor', href: '/supplier-invoices' },
    { title: `Editar fatura #${props.invoice.number}`, href: `/supplier-invoices/${props.invoice.id}/edit` },
];

const form = useForm({
    number: props.invoice.number,
    invoice_date: props.invoice.invoice_date,
    due_date: props.invoice.due_date,
    supplier_id: props.invoice.supplier_id,
    supplier_order_id: props.invoice.supplier_order_id ?? '',
    total: props.invoice.total,
    document: null as File | null,
    payment_proof: null as File | null,
    status: props.invoice.status,
    send_payment_proof_email: false,
});

function submit(): void {
    form.put(`/supplier-invoices/${props.invoice.id}`, {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Editar fatura fornecedor" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader><CardTitle>Editar fatura fornecedor</CardTitle></CardHeader>
                <CardContent>
                    <SupplierInvoiceForm
                        :form="form"
                        :suppliers="suppliers"
                        :supplier-orders="supplierOrders"
                        submit-label="Guardar"
                        :existing-document-url="invoice.document_url"
                        :existing-payment-proof-url="invoice.payment_proof_url"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
