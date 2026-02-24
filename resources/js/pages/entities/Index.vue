<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type EntityRow = {
    id: number;
    tax_id: string;
    name: string;
    phone: string | null;
    mobile: string | null;
    website: string | null;
    email: string | null;
    status: string;
    type: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedEntities = {
    data: EntityRow[];
    current_page: number;
    last_page: number;
    links: PaginationLink[];
};

const props = defineProps<{
    entities: PaginatedEntities;
    filters: {
        type: 'customer' | 'supplier' | 'both';
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title:
            props.filters.type === 'supplier'
                ? 'Fornecedores'
                : props.filters.type === 'both'
                  ? 'Entidades'
                  : 'Clientes',
        href: `/entities?type=${props.filters.type}`,
    },
];

const columns: ColumnDef<EntityRow>[] = [
    {
        accessorKey: 'tax_id',
        header: 'NIF',
    },
    {
        accessorKey: 'name',
        header: 'Nome',
        cell: ({ row }: { row: { original: EntityRow } }) =>
            h(
                Link,
                {
                    href: `/entities/${row.original.id}`,
                    class: 'underline-offset-4 hover:underline',
                },
                () => row.original.name,
            ),
    },
    {
        accessorKey: 'phone',
        header: 'Telefone',
        cell: ({ row }: { row: { original: EntityRow } }) => row.original.phone || '-',
    },
    {
        accessorKey: 'mobile',
        header: 'Telemovel',
        cell: ({ row }: { row: { original: EntityRow } }) => row.original.mobile || '-',
    },
    {
        accessorKey: 'website',
        header: 'Website',
        cell: ({ row }: { row: { original: EntityRow } }) => row.original.website || '-',
    },
    {
        accessorKey: 'email',
        header: 'Email',
        cell: ({ row }: { row: { original: EntityRow } }) => row.original.email || '-',
    },
];

function applyFilter(type: 'customer' | 'supplier' | 'both'): void {
    router.get(
        '/entities',
        { type },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

</script>

<template>
    <Head title="Entidades" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>
                        {{
                            filters.type === 'supplier'
                                ? 'Fornecedores'
                                : filters.type === 'both'
                                  ? 'Entidades'
                                  : 'Clientes'
                        }}
                    </CardTitle>
                    <div class="flex gap-2">
                        <select
                            :value="filters.type"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            @change="
                                applyFilter(
                                    ($event.target as HTMLSelectElement)
                                        .value as
                                        | 'customer'
                                        | 'supplier'
                                        | 'both',
                                )
                            "
                        >
                            <option value="both">Ambos</option>
                            <option value="customer">Cliente</option>
                            <option value="supplier">Fornecedor</option>
                        </select>
                        <Button as-child>
                            <Link :href="`/entities/create?type=${filters.type}`">Criar</Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <DataTable
                        :columns="columns"
                        :data="entities.data"
                        empty-text="Sem entidades para mostrar."
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-for="link in entities.links"
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
