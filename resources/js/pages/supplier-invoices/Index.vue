<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type SupplierInvoiceRow = {
    id: number;
    invoice_date: string | null;
    number: number;
    supplier: string | null;
    supplier_order: number | null;
    document_url: string | null;
    total: number;
    status: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };
type PaginatedInvoices = { data: SupplierInvoiceRow[]; links: PaginationLink[] };

const props = defineProps<{ invoices: PaginatedInvoices }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Faturas Fornecedor', href: '/supplier-invoices' }];

const columns: ColumnDef<SupplierInvoiceRow>[] = [
    { accessorKey: 'invoice_date', header: 'Data', cell: ({ row }: { row: { original: SupplierInvoiceRow } }) => row.original.invoice_date ?? '-' },
    {
        accessorKey: 'number',
        header: 'Numero',
        cell: ({ row }: { row: { original: SupplierInvoiceRow } }) =>
            h(Link, { href: `/supplier-invoices/${row.original.id}`, class: 'underline hover:opacity-80' }, () => String(row.original.number)),
    },
    { accessorKey: 'supplier', header: 'Fornecedor', cell: ({ row }: { row: { original: SupplierInvoiceRow } }) => row.original.supplier ?? '-' },
    { accessorKey: 'supplier_order', header: 'Encomenda', cell: ({ row }: { row: { original: SupplierInvoiceRow } }) => (row.original.supplier_order ? `#${row.original.supplier_order}` : '-') },
    {
        accessorKey: 'document_url',
        header: 'Documento',
        cell: ({ row }: { row: { original: SupplierInvoiceRow } }) =>
            row.original.document_url
                ? h(Link, { href: row.original.document_url, class: 'underline hover:opacity-80' }, () => 'Abrir')
                : '-',
    },
    { accessorKey: 'total', header: 'Valor Total', cell: ({ row }: { row: { original: SupplierInvoiceRow } }) => `${row.original.total.toFixed(2)} EUR` },
    { accessorKey: 'status', header: 'Estado', cell: ({ row }: { row: { original: SupplierInvoiceRow } }) => (row.original.status === 'paid' ? 'Paga' : 'Pendente de Pagamento') },
];
</script>

<template>
    <Head title="Faturas Fornecedor" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Faturas Fornecedor</CardTitle>
                    <Button as-child><Link href="/supplier-invoices/create">Criar</Link></Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <DataTable :columns="columns" :data="invoices.data" empty-text="Sem faturas fornecedor." />
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-for="link in invoices.links"
                            :key="link.label"
                            :variant="link.active ? 'default' : 'outline'"
                            size="sm"
                            :disabled="!link.url"
                            as-child
                        >
                            <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state><span v-html="link.label" /></Link>
                            <span v-else v-html="link.label" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
