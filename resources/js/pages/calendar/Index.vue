<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h, ref, watch } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type SelectOption = {
    id: number;
    name: string;
};

type CalendarEventItem = {
    id: number;
    title: string;
    start: string;
    end: string;
    extendedProps: {
        entity: string | null;
        user: string | null;
        type: string | null;
        action: string | null;
        status: string;
    };
};

type CalendarRow = {
    id: number;
    event_date: string | null;
    event_time: string | null;
    duration_minutes: number;
    entity: string | null;
    user: string | null;
    type: string | null;
    action: string | null;
    status: string;
};

const props = defineProps<{
    events: CalendarEventItem[];
    rows: CalendarRow[];
    users: SelectOption[];
    entities: SelectOption[];
    filters: {
        user_id: string | number;
        entity_id: string | number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Calendario', href: '/calendar' },
];

const selectedUserId = ref<string>(String(props.filters.user_id ?? ''));
const selectedEntityId = ref<string>(String(props.filters.entity_id ?? ''));

watch(
    () => props.filters,
    (value) => {
        selectedUserId.value = String(value.user_id ?? '');
        selectedEntityId.value = String(value.entity_id ?? '');
    },
);

const calendarOptions = ref({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    events: props.events,
    locale: 'pt',
    eventClick: (info: { event: { id: string } }) => {
        router.get(`/calendar/${info.event.id}/edit`);
    },
});

function applyFilters(): void {
    router.get('/calendar', {
        user_id: selectedUserId.value || '',
        entity_id: selectedEntityId.value || '',
    }, {
        preserveState: true,
    });
}

function deleteEvent(event: CalendarRow): void {
    if (!window.confirm('Eliminar atividade?')) {
        return;
    }

    router.delete(`/calendar/${event.id}`, {
        preserveScroll: true,
    });
}

const columns: ColumnDef<CalendarRow>[] = [
    { accessorKey: 'event_date', header: 'Data', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.event_date ?? '-' },
    { accessorKey: 'event_time', header: 'Hora', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.event_time ?? '-' },
    { accessorKey: 'entity', header: 'Entidade', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.entity ?? '-' },
    { accessorKey: 'user', header: 'Utilizador', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.user ?? '-' },
    { accessorKey: 'type', header: 'Tipo', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.type ?? '-' },
    { accessorKey: 'action', header: 'Acao', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.action ?? '-' },
    { accessorKey: 'status', header: 'Estado', cell: ({ row }: { row: { original: CalendarRow } }) => (row.original.status === 'active' ? 'Ativo' : 'Inativo') },
    {
        id: 'actions',
        header: 'Acoes',
        cell: ({ row }: { row: { original: CalendarRow } }) =>
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
                                { href: `/calendar/${row.original.id}/edit` },
                                () => 'Editar',
                            ),
                    },
                ),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'destructive',
                        onClick: () => deleteEvent(row.original),
                    },
                    () => 'Eliminar',
                ),
            ]),
    },
];
</script>

<template>
    <Head title="Calendario" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Calendario</CardTitle>
                    <Button as-child>
                        <Link href="/calendar/create">Agendar atividade</Link>
                    </Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <form class="grid gap-4 md:grid-cols-3" @submit.prevent="applyFilters">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Filtrar por Utilizador</label>
                            <select
                                v-model="selectedUserId"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="">Todos</option>
                                <option v-for="user in users" :key="user.id" :value="String(user.id)">
                                    {{ user.name }}
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Filtrar por Entidade</label>
                            <select
                                v-model="selectedEntityId"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="">Todas</option>
                                <option v-for="entity in entities" :key="entity.id" :value="String(entity.id)">
                                    {{ entity.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <Button type="submit">Filtrar</Button>
                            <Button type="button" variant="outline" as-child>
                                <Link href="/calendar">Limpar</Link>
                            </Button>
                        </div>
                    </form>

                    <div class="overflow-x-auto rounded-md border bg-background p-2">
                        <FullCalendar :options="calendarOptions" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Atividades</CardTitle>
                </CardHeader>
                <CardContent>
                    <DataTable :columns="columns" :data="rows" empty-text="Sem atividades." />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
