<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    mobile: string | null;
    permission_group: string | null;
    status: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedUsers = {
    data: UserRow[];
    current_page: number;
    last_page: number;
    links: PaginationLink[];
};

const props = defineProps<{
    users: PaginatedUsers;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestao de Acessos - Utilizadores', href: '/access/users' },
];

function deleteUser(user: UserRow): void {
    if (!window.confirm(`Eliminar utilizador ${user.name}?`)) {
        return;
    }

    router.delete(`/access/users/${user.id}`, {
        preserveScroll: true,
    });
}

const columns: ColumnDef<UserRow>[] = [
    {
        accessorKey: 'name',
        header: 'Nome',
        cell: ({ row }: { row: { original: UserRow } }) =>
            h(
                Link,
                {
                    href: `/access/users/${row.original.id}/edit`,
                    class: 'underline-offset-4 hover:underline',
                },
                () => row.original.name,
            ),
    },
    { accessorKey: 'email', header: 'Email' },
    { accessorKey: 'mobile', header: 'Telemovel', cell: ({ row }: { row: { original: UserRow } }) => row.original.mobile || '-' },
    { accessorKey: 'permission_group', header: 'Grupo de Permissoes', cell: ({ row }: { row: { original: UserRow } }) => row.original.permission_group || '-' },
    { accessorKey: 'status', header: 'Estado', cell: ({ row }: { row: { original: UserRow } }) => (row.original.status === 'active' ? 'Ativo' : 'Inativo') },
    {
        id: 'actions',
        header: 'Acoes',
        cell: ({ row }: { row: { original: UserRow } }) =>
            h('div', { class: 'flex gap-2' }, [
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        asChild: true,
                    },
                    {
                        default: () =>
                            h(
                                Link,
                                {
                                    href: `/access/users/${row.original.id}/edit`,
                                },
                                () => 'Editar',
                            ),
                    },
                ),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'destructive',
                        onClick: () => deleteUser(row.original),
                    },
                    () => 'Eliminar',
                ),
            ]),
    },
];
</script>

<template>
    <Head title="Gestao de Acessos - Utilizadores" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Utilizadores</CardTitle>
                    <Button as-child>
                        <Link href="/access/users/create">Criar</Link>
                    </Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <DataTable
                        :columns="columns"
                        :data="users.data"
                        empty-text="Sem utilizadores para mostrar."
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-for="link in users.links"
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
