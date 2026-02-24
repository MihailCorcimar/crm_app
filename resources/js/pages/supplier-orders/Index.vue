<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type SupplierOrderRow = {
    id: number;
    order_date: string | null;
    number: number;
    supplier: string | null;
    customer_order_number: number | null;
    total: number;
    status: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };
type PaginatedSupplierOrders = { data: SupplierOrderRow[]; links: PaginationLink[] };

const props = defineProps<{ supplierOrders: PaginatedSupplierOrders }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Encomendas - Fornecedores', href: '/supplier-orders' }];

const columns: ColumnDef<SupplierOrderRow>[] = [
    {
        accessorKey: 'order_date',
        header: 'Data',
        cell: ({ row }: { row: { original: SupplierOrderRow } }) => row.original.order_date ?? '-',
    },
    {
        accessorKey: 'number',
        header: 'Numero',
        cell: ({ row }: { row: { original: SupplierOrderRow } }) =>
            h(Link, { href: `/supplier-orders/${row.original.id}`, class: 'underline hover:opacity-80' }, () => String(row.original.number)),
    },
    {
        accessorKey: 'supplier',
        header: 'Fornecedor',
        cell: ({ row }: { row: { original: SupplierOrderRow } }) => row.original.supplier ?? '-',
    },
    {
        accessorKey: 'customer_order_number',
        header: 'Encomenda Cliente',
        cell: ({ row }: { row: { original: SupplierOrderRow } }) =>
            row.original.customer_order_number ? `#${row.original.customer_order_number}` : '-',
    },
    {
        accessorKey: 'total',
        header: 'Valor Total',
        cell: ({ row }: { row: { original: SupplierOrderRow } }) => `${row.original.total.toFixed(2)} EUR`,
    },
    {
        accessorKey: 'status',
        header: 'Estado',
        cell: ({ row }: { row: { original: SupplierOrderRow } }) => (row.original.status === 'closed' ? 'Fechado' : 'Rascunho'),
    },
];
</script>

<template>
    <Head title="Encomendas - Fornecedores" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Encomendas - Fornecedores</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <DataTable :columns="columns" :data="supplierOrders.data" empty-text="Sem encomendas de fornecedor." />
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-for="(link, index) in supplierOrders.links"
                            :key="`${link.label}-${index}`"
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

