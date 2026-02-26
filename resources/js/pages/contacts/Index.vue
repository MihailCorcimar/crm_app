<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
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
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pessoas', href: '/people' }];

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
        header: 'Funcao',
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
        header: 'Telemovel',
        cell: ({ row }: { row: { original: ContactRow } }) => row.original.mobile || '-',
    },
    {
        accessorKey: 'email',
        header: 'Email',
        cell: ({ row }: { row: { original: ContactRow } }) => row.original.email || '-',
    },
];
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
