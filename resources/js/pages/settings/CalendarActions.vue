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

type CalendarActionRow = {
    id: number;
    name: string;
    events_count: number;
    status: string;
};

const props = defineProps<{
    calendarActions: CalendarActionRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações - Calendário - Ações', href: '/settings/calendar/actions' },
];

const createForm = useForm({
    name: '',
    status: 'active',
});

const editForm = useForm({
    id: null as number | null,
    name: '',
    status: 'active',
});

const deletingId = ref<number | null>(null);

function createCalendarAction(): void {
    createForm.post('/settings/calendar/actions', {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function startEdit(calendarAction: CalendarActionRow): void {
    editForm.id = calendarAction.id;
    editForm.name = calendarAction.name;
    editForm.status = calendarAction.status;
    editForm.clearErrors();
}

function cancelEdit(): void {
    editForm.reset();
    editForm.id = null;
}

function updateCalendarAction(): void {
    if (!editForm.id) {
        return;
    }

    editForm.put(`/settings/calendar/actions/${editForm.id}`, {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
}

function deleteCalendarAction(calendarAction: CalendarActionRow): void {
    if (!window.confirm(`Eliminar acao ${calendarAction.name}?`)) {
        return;
    }

    deletingId.value = calendarAction.id;
    editForm.delete(`/settings/calendar/actions/${calendarAction.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

const columns: ColumnDef<CalendarActionRow>[] = [
    { accessorKey: 'name', header: 'Nome' },
    { accessorKey: 'events_count', header: 'Atividades' },
    { accessorKey: 'status', header: 'Estado', cell: ({ row }: { row: { original: CalendarActionRow } }) => (row.original.status === 'active' ? 'Ativo' : 'Inativo') },
    {
        id: 'actions',
        header: 'Ações',
        cell: ({ row }: { row: { original: CalendarActionRow } }) =>
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
                        onClick: () => deleteCalendarAction(row.original),
                    },
                    () => 'Eliminar',
                ),
            ]),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Configurações - Calendário - Ações" />

        <SettingsLayout :show-system-nav="false">
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Configurações - Calendário - Ações"
                    description="Gerir as acoes utilizadas no calendario."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Nova acao</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-3" @submit.prevent="createCalendarAction">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Nome</label>
                                <Input v-model="createForm.name" />
                                <InputError :message="createForm.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Estado</label>
                                <select
                                    v-model="createForm.status"
                                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                >
                                    <option value="active">Ativo</option>
                                    <option value="inactive">Inativo</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <Button type="submit" :disabled="createForm.processing">Adicionar</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card v-if="editForm.id">
                    <CardHeader>
                        <CardTitle>Editar acao</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-3" @submit.prevent="updateCalendarAction">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Nome</label>
                                <Input v-model="editForm.name" />
                                <InputError :message="editForm.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Estado</label>
                                <select
                                    v-model="editForm.status"
                                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                >
                                    <option value="active">Ativo</option>
                                    <option value="inactive">Inativo</option>
                                </select>
                            </div>
                            <div class="flex items-end gap-2">
                                <Button type="submit" :disabled="editForm.processing">Guardar</Button>
                                <Button type="button" variant="outline" @click="cancelEdit">Cancelar</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ações</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable :columns="columns" :data="calendarActions" empty-text="Sem acoes configuradas." />
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
