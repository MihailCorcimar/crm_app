<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type ProposalRow = {
    id: number;
    proposal_date: string | null;
    number: number;
    valid_until: string;
    customer: string | null;
    total: number;
    status: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };
type PaginatedProposals = { data: ProposalRow[]; links: PaginationLink[] };

const props = defineProps<{ proposals: PaginatedProposals }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Propostas', href: '/proposals' }];

const columns: ColumnDef<ProposalRow>[] = [
    { accessorKey: 'proposal_date', header: 'Data', cell: ({ row }: { row: { original: ProposalRow } }) => row.original.proposal_date ?? '-' },
    {
        accessorKey: 'number',
        header: 'Numero',
        cell: ({ row }: { row: { original: ProposalRow } }) =>
            h(Link, { href: `/proposals/${row.original.id}`, class: 'underline hover:opacity-80' }, () => String(row.original.number)),
    },
    { accessorKey: 'valid_until', header: 'Validade' },
    { accessorKey: 'customer', header: 'Cliente', cell: ({ row }: { row: { original: ProposalRow } }) => row.original.customer ?? '-' },
    { accessorKey: 'total', header: 'Valor Total', cell: ({ row }: { row: { original: ProposalRow } }) => `${row.original.total.toFixed(2)} €` },
    { accessorKey: 'status', header: 'Estado', cell: ({ row }: { row: { original: ProposalRow } }) => (row.original.status === 'closed' ? 'Fechado' : 'Rascunho') },
];
</script>

<template>
    <Head title="Propostas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Propostas</CardTitle>
                    <Button as-child><Link href="/proposals/create">Criar</Link></Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <DataTable :columns="columns" :data="proposals.data" empty-text="Sem propostas." />
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-for="link in proposals.links"
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
