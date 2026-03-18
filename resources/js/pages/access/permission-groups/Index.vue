<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type PermissionGroupRow = {
    id: number;
    name: string;
    users_count: number;
    status: string;
};

const props = defineProps<{
    permissionGroups: PermissionGroupRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestão de Acessos - Permissões', href: '/access/permission-groups' },
];

function deleteGroup(group: PermissionGroupRow): void {
    if (!window.confirm(`Eliminar grupo ${group.name}?`)) {
        return;
    }

    router.delete(`/access/permission-groups/${group.id}`, {
        preserveScroll: true,
    });
}

const columns: ColumnDef<PermissionGroupRow>[] = [
    {
        accessorKey: 'name',
        header: 'Nome do Grupo',
        cell: ({ row }: { row: { original: PermissionGroupRow } }) =>
            h(
                Link,
                {
                    href: `/access/permission-groups/${row.original.id}/edit`,
                    class: 'underline-offset-4 hover:underline',
                },
                () => row.original.name,
            ),
    },
    { accessorKey: 'users_count', header: 'Utilizadores Relacionados' },
    { accessorKey: 'status', header: 'Estado', cell: ({ row }: { row: { original: PermissionGroupRow } }) => (row.original.status === 'active' ? 'Ativo' : 'Inativo') },
    {
        id: 'actions',
        header: 'Ações',
        cell: ({ row }: { row: { original: PermissionGroupRow } }) =>
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
                                    href: `/access/permission-groups/${row.original.id}/edit`,
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
                        onClick: () => deleteGroup(row.original),
                    },
                    () => 'Eliminar',
                ),
            ]),
    },
];
</script>

<template>
    <Head title="Gestão de Acessos - Permissões" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Permissões</CardTitle>
                    <Button as-child>
                        <Link href="/access/permission-groups/create">Criar</Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :columns="columns"
                        :data="permissionGroups"
                        empty-text="Sem grupos de permissão."
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>


