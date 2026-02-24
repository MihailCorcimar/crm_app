<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type OrderRow = {
    id: number;
    order_date: string | null;
    number: number;
    valid_until: string | null;
    customer: string | null;
    total: number;
    status: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };
type PaginatedOrders = { data: OrderRow[]; links: PaginationLink[] };

const props = defineProps<{ orders: PaginatedOrders }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Encomendas', href: '/orders' }];

const columns: ColumnDef<OrderRow>[] = [
    { accessorKey: 'order_date', header: 'Data', cell: ({ row }: { row: { original: OrderRow } }) => row.original.order_date ?? '-' },
    {
        accessorKey: 'number',
        header: 'Numero',
        cell: ({ row }: { row: { original: OrderRow } }) =>
            h(Link, { href: `/orders/${row.original.id}`, class: 'underline hover:opacity-80' }, () => String(row.original.number)),
    },
    { accessorKey: 'valid_until', header: 'Validade', cell: ({ row }: { row: { original: OrderRow } }) => row.original.valid_until ?? '-' },
    { accessorKey: 'customer', header: 'Cliente', cell: ({ row }: { row: { original: OrderRow } }) => row.original.customer ?? '-' },
    { accessorKey: 'total', header: 'Valor Total', cell: ({ row }: { row: { original: OrderRow } }) => `${row.original.total.toFixed(2)} EUR` },
    { accessorKey: 'status', header: 'Estado', cell: ({ row }: { row: { original: OrderRow } }) => (row.original.status === 'closed' ? 'Fechado' : 'Rascunho') },
];
</script>

<template>
    <Head title="Encomendas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Encomendas</CardTitle>
                    <Button as-child><Link href="/orders/create">Criar</Link></Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <DataTable :columns="columns" :data="orders.data" empty-text="Sem encomendas." />
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-for="link in orders.links"
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
