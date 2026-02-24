<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

type RoleRow = {
    id: number;
    name: string;
    contacts_count: number;
};

const props = defineProps<{
    roles: RoleRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracoes - Funcoes', href: '/settings/contacts/roles' },
];

const createForm = useForm({
    name: '',
});

const editForm = useForm({
    id: null as number | null,
    name: '',
});

const deletingId = ref<number | null>(null);

function createRole(): void {
    createForm.post('/settings/contacts/roles', {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function startEdit(role: RoleRow): void {
    editForm.id = role.id;
    editForm.name = role.name;
    editForm.clearErrors();
}

function cancelEdit(): void {
    editForm.reset();
    editForm.id = null;
}

function updateRole(): void {
    if (!editForm.id) {
        return;
    }

    editForm.put(`/settings/contacts/roles/${editForm.id}`, {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
}

function deleteRole(role: RoleRow): void {
    if (!window.confirm(`Eliminar funcao ${role.name}?`)) {
        return;
    }

    deletingId.value = role.id;
    editForm.delete(`/settings/contacts/roles/${role.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

const columns: ColumnDef<RoleRow>[] = [
    {
        accessorKey: 'name',
        header: 'Nome',
    },
    {
        accessorKey: 'contacts_count',
        header: 'Contactos',
    },
    {
        id: 'actions',
        header: 'Acoes',
        cell: ({ row }: { row: { original: RoleRow } }) =>
            h('div', { class: 'flex gap-2' }, [
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        onClick: () => startEdit(row.original),
                    },
                    () => 'Editar',
                ),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'destructive',
                        disabled: deletingId.value === row.original.id,
                        onClick: () => deleteRole(row.original),
                    },
                    () => 'Eliminar',
                ),
            ]),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Configuracoes - Funcoes" />

        <SettingsLayout :show-system-nav="false">
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Configuracoes - Contactos - Funcoes"
                    description="Gerir a lista de funcoes usada nos Contactos."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Nova funcao</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="createRole">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Nome</label>
                                <Input v-model="createForm.name" placeholder="Finance" />
                                <InputError :message="createForm.errors.name" />
                            </div>
                            <div>
                                <Button type="submit" :disabled="createForm.processing">Adicionar</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card v-if="editForm.id">
                    <CardHeader>
                        <CardTitle>Editar funcao</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="updateRole">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Nome</label>
                                <Input v-model="editForm.name" />
                                <InputError :message="editForm.errors.name" />
                            </div>
                            <div class="flex gap-2">
                                <Button type="submit" :disabled="editForm.processing">Guardar</Button>
                                <Button type="button" variant="outline" @click="cancelEdit">Cancelar</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Funcoes</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <DataTable :columns="columns" :data="roles" empty-text="Sem funcoes configuradas." />
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
