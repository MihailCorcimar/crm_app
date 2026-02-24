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

const props = defineProps<{
    suppliers: SupplierOption[];
    supplierOrders: SupplierOrderOption[];
    defaults: { invoice_date: string; due_date: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Faturas Fornecedor', href: '/supplier-invoices' },
    { title: 'Criar fatura fornecedor', href: '/supplier-invoices/create' },
];

const form = useForm({
    invoice_date: props.defaults.invoice_date,
    due_date: props.defaults.due_date,
    supplier_id: props.suppliers[0]?.id ?? '',
    supplier_order_id: '',
    total: 0,
    document: null as File | null,
    payment_proof: null as File | null,
    status: 'pending_payment' as 'pending_payment' | 'paid',
    send_payment_proof_email: false,
});

function submit(): void {
    form.post('/supplier-invoices', {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Criar fatura fornecedor" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader><CardTitle>Nova fatura fornecedor</CardTitle></CardHeader>
                <CardContent>
                    <SupplierInvoiceForm
                        :form="form"
                        :suppliers="suppliers"
                        :supplier-orders="supplierOrders"
                        submit-label="Criar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
