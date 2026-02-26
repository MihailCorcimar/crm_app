<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h, reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type EntityRow = {
    id: number;
    vat: string;
    name: string;
    phone: string | null;
    email: string | null;
    status: 'active' | 'inactive';
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
        name: string;
        vat: string;
        status: '' | 'active' | 'inactive';
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Entidades', href: '/entities' }];

const filterForm = reactive({
    name: props.filters.name ?? '',
    vat: props.filters.vat ?? '',
    status: props.filters.status ?? '',
});

const columns: ColumnDef<EntityRow>[] = [
    {
        accessorKey: 'vat',
        header: 'VAT',
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
        accessorKey: 'email',
        header: 'Email',
        cell: ({ row }: { row: { original: EntityRow } }) => row.original.email || '-',
    },
    {
        accessorKey: 'status',
        header: 'Estado',
        cell: ({ row }: { row: { original: EntityRow } }) =>
            row.original.status === 'active' ? 'Ativo' : 'Inativo',
    },
];

function applyFilters(): void {
    router.get(
        '/entities',
        {
            name: filterForm.name || undefined,
            vat: filterForm.vat || undefined,
            status: filterForm.status || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function clearFilters(): void {
    filterForm.name = '';
    filterForm.vat = '';
    filterForm.status = '';
    applyFilters();
}
</script>

<template>
    <Head title="Entidades" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Entidades</CardTitle>
                    <Button as-child>
                        <Link href="/entities/create">Criar</Link>
                    </Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-4">
                        <input
                            v-model="filterForm.name"
                            type="text"
                            placeholder="Filtrar por nome"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <input
                            v-model="filterForm.vat"
                            type="text"
                            placeholder="Filtrar por VAT"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <select
                            v-model="filterForm.status"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        >
                            <option value="">Todos os estados</option>
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                        <div class="flex gap-2">
                            <Button type="button" @click="applyFilters">Filtrar</Button>
                            <Button type="button" variant="outline" @click="clearFilters">Limpar</Button>
                        </div>
                    </div>

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
