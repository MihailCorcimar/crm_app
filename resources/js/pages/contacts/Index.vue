<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h, reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type ContactRow = {
    id: number;
    first_name: string;
    last_name: string | null;
    role: string | null;
    entity: string | null;
    phone: string | null;
    mobile: string | null;
    email: string | null;
    created_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedContacts = {
    data: ContactRow[];
    current_page: number;
    last_page: number;
    links: PaginationLink[];
};

const props = defineProps<{
    contacts: PaginatedContacts;
    filters: {
        name: string;
        email: string;
        created_at_order: 'asc' | 'desc';
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pessoas', href: '/people' }];

const filterForm = reactive({
    name: props.filters?.name ?? '',
    email: props.filters?.email ?? '',
    created_at_order: props.filters?.created_at_order ?? 'desc',
});

const columns: ColumnDef<ContactRow>[] = [
    {
        accessorKey: 'first_name',
        header: 'Nome',
        cell: ({ row }: { row: { original: ContactRow } }) =>
            h(
                Link,
                {
                    href: `/people/${row.original.id}`,
                    class: 'underline-offset-4 hover:underline',
                },
                () => row.original.first_name,
            ),
    },
    {
        accessorKey: 'last_name',
        header: 'Apelido',
        cell: ({ row }: { row: { original: ContactRow } }) => row.original.last_name || '-',
    },
    {
        accessorKey: 'role',
        header: 'Função',
        cell: ({ row }: { row: { original: ContactRow } }) => row.original.role || '-',
    },
    {
        accessorKey: 'entity',
        header: 'Entidade',
        cell: ({ row }: { row: { original: ContactRow } }) => row.original.entity || '-',
    },
    {
        accessorKey: 'phone',
        header: 'Telefone',
        cell: ({ row }: { row: { original: ContactRow } }) => row.original.phone || '-',
    },
    {
        accessorKey: 'mobile',
        header: 'Telemóvel',
        cell: ({ row }: { row: { original: ContactRow } }) => row.original.mobile || '-',
    },
    {
        accessorKey: 'email',
        header: 'Email',
        cell: ({ row }: { row: { original: ContactRow } }) => row.original.email || '-',
    },
    {
        accessorKey: 'created_at',
        header: 'Criado em',
        cell: ({ row }: { row: { original: ContactRow } }) => row.original.created_at || '-',
    },
];

function applyFilters(): void {
    router.get(
        '/people',
        {
            name: filterForm.name || undefined,
            email: filterForm.email || undefined,
            created_at_order: filterForm.created_at_order || undefined,
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
    filterForm.email = '';
    filterForm.created_at_order = 'desc';
    applyFilters();
}
</script>

<template>
    <Head title="Pessoas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Pessoas</CardTitle>
                    <Button as-child>
                        <Link href="/people/create">Criar</Link>
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
                            v-model="filterForm.email"
                            type="text"
                            placeholder="Filtrar por email"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <select
                            v-model="filterForm.created_at_order"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        >
                            <option value="desc">Data criação: mais recentes</option>
                            <option value="asc">Data criação: mais antigas</option>
                        </select>
                        <div class="flex gap-2">
                            <Button type="button" @click="applyFilters">Filtrar</Button>
                            <Button type="button" variant="outline" @click="clearFilters">Limpar</Button>
                        </div>
                    </div>

                    <DataTable
                        :columns="columns"
                        :data="contacts.data"
                        empty-text="Sem pessoas para mostrar."
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-for="link in contacts.links"
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
