<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type DealRow = {
    id: number;
    title: string;
    entity: string | null;
    stage: string;
    value: number;
    probability: number;
    expected_close_date: string | null;
    owner: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedDeals = {
    data: DealRow[];
    current_page: number;
    last_page: number;
    links: PaginationLink[];
};

const props = defineProps<{
    deals: PaginatedDeals;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Negócios', href: '/deals' }];

function stageLabel(stage: string): string {
    const map: Record<string, string> = {
        lead: 'Lead',
        proposal: 'Proposta',
        negotiation: 'Negociação',
        follow_up: 'Follow Up',
        won: 'Ganho',
        lost: 'Perdido',
    };

    return map[stage] ?? stage;
}

const columns: ColumnDef<DealRow>[] = [
    {
        accessorKey: 'title',
        header: 'Título',
        cell: ({ row }: { row: { original: DealRow } }) =>
            h(
                Link,
                {
                    href: `/deals/${row.original.id}`,
                    class: 'underline-offset-4 hover:underline',
                },
                () => row.original.title,
            ),
    },
    {
        accessorKey: 'entity',
        header: 'Entidade',
        cell: ({ row }: { row: { original: DealRow } }) => row.original.entity || '-',
    },
    {
        accessorKey: 'stage',
        header: 'Etapa',
        cell: ({ row }: { row: { original: DealRow } }) => stageLabel(row.original.stage),
    },
    {
        accessorKey: 'value',
        header: 'Valor',
        cell: ({ row }: { row: { original: DealRow } }) => `${row.original.value.toFixed(2)} EUR`,
    },
    {
        accessorKey: 'probability',
        header: 'Probabilidade',
        cell: ({ row }: { row: { original: DealRow } }) => `${row.original.probability}%`,
    },
    {
        accessorKey: 'expected_close_date',
        header: 'Fecho previsto',
        cell: ({ row }: { row: { original: DealRow } }) => row.original.expected_close_date || '-',
    },
    {
        accessorKey: 'owner',
        header: 'Responsável',
        cell: ({ row }: { row: { original: DealRow } }) => row.original.owner || '-',
    },
];
</script>

<template>
    <Head title="Negócios" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Negócios</CardTitle>
                    <Button as-child>
                        <Link href="/deals/create">Criar</Link>
                    </Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <DataTable
                        :columns="columns"
                        :data="deals.data"
                        empty-text="Sem negócios para mostrar."
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-for="link in deals.links"
                            :key="link.label"
                            :variant="link.active ? 'default' : 'outline'"
                            size="sm"
                            :disabled="!link.url"
                            as-child
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                            >
                                <span v-html="link.label" />
                            </Link>
                            <span v-else v-html="link.label" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
