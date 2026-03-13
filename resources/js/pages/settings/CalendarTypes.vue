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

type CalendarTypeRow = {
    id: number;
    name: string;
    events_count: number;
    status: string;
};

const props = defineProps<{
    calendarTypes: CalendarTypeRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações - Calendário - Tipos', href: '/settings/calendar/types' },
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

function createCalendarType(): void {
    createForm.post('/settings/calendar/types', {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function startEdit(calendarType: CalendarTypeRow): void {
    editForm.id = calendarType.id;
    editForm.name = calendarType.name;
    editForm.status = calendarType.status;
    editForm.clearErrors();
}

function cancelEdit(): void {
    editForm.reset();
    editForm.id = null;
}

function updateCalendarType(): void {
    if (!editForm.id) {
        return;
    }

    editForm.put(`/settings/calendar/types/${editForm.id}`, {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
}

function deleteCalendarType(calendarType: CalendarTypeRow): void {
    if (!window.confirm(`Eliminar tipo ${calendarType.name}?`)) {
        return;
    }

    deletingId.value = calendarType.id;
    editForm.delete(`/settings/calendar/types/${calendarType.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

const columns: ColumnDef<CalendarTypeRow>[] = [
    { accessorKey: 'name', header: 'Nome' },
    { accessorKey: 'events_count', header: 'Atividades' },
    { accessorKey: 'status', header: 'Estado', cell: ({ row }: { row: { original: CalendarTypeRow } }) => (row.original.status === 'active' ? 'Ativo' : 'Inativo') },
    {
        id: 'actions',
        header: 'Ações',
        cell: ({ row }: { row: { original: CalendarTypeRow } }) =>
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
                        onClick: () => deleteCalendarType(row.original),
                    },
                    () => 'Eliminar',
                ),
            ]),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Configurações - Calendário - Tipos" />

        <SettingsLayout :show-system-nav="false">
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Configurações - Calendário - Tipos"
                    description="Gerir os tipos utilizados no calendario."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Novo tipo</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-3" @submit.prevent="createCalendarType">
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
                        <CardTitle>Editar tipo</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-3" @submit.prevent="updateCalendarType">
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
                        <CardTitle>Tipos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable :columns="columns" :data="calendarTypes" empty-text="Sem tipos configurados." />
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
