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

type EventableTypeOption = {
    value: 'entity' | 'person' | 'deal';
    label: string;
};

type CalendarEventItem = {
    id: number;
    title: string;
    start: string;
    end: string;
    extendedProps: {
        owner: string | null;
        type: string | null;
        action: string | null;
        eventable: string;
        attendees_count: number;
        status: string;
    };
};

type CalendarRow = {
    id: number;
    title: string | null;
    start_at: string;
    end_at: string;
    eventable: string;
    owner: string | null;
    type: string | null;
    action: string | null;
    attendees_count: number;
    status: string;
};

const props = defineProps<{
    events: CalendarEventItem[];
    rows: CalendarRow[];
    owners: SelectOption[];
    eventableTypes: EventableTypeOption[];
    filters: {
        owner_id: string | number;
        eventable_type: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Calendario', href: '/calendar' },
];

const selectedOwnerId = ref<string>(String(props.filters.owner_id ?? ''));
const selectedEventableType = ref<string>(String(props.filters.eventable_type ?? ''));

watch(
    () => props.filters,
    (value) => {
        selectedOwnerId.value = String(value.owner_id ?? '');
        selectedEventableType.value = String(value.eventable_type ?? '');
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
    router.get(
        '/calendar',
        {
            owner_id: selectedOwnerId.value || '',
            eventable_type: selectedEventableType.value || '',
        },
        {
            preserveState: true,
        },
    );
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
    { accessorKey: 'title', header: 'Titulo', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.title ?? '-' },
    { accessorKey: 'start_at', header: 'Inicio', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.start_at },
    { accessorKey: 'end_at', header: 'Fim', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.end_at },
    { accessorKey: 'eventable', header: 'Associacao', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.eventable },
    { accessorKey: 'owner', header: 'Responsavel', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.owner ?? '-' },
    { accessorKey: 'type', header: 'Tipo', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.type ?? '-' },
    { accessorKey: 'action', header: 'Acao', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.action ?? '-' },
    { accessorKey: 'attendees_count', header: 'Attendees', cell: ({ row }: { row: { original: CalendarRow } }) => row.original.attendees_count },
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
                            <label class="text-sm font-medium">Filtrar por Responsavel</label>
                            <select
                                v-model="selectedOwnerId"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="">Todos</option>
                                <option v-for="owner in owners" :key="owner.id" :value="String(owner.id)">
                                    {{ owner.name }}
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Filtrar por Associacao</label>
                            <select
                                v-model="selectedEventableType"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="">Todas</option>
                                <option v-for="eventableType in eventableTypes" :key="eventableType.value" :value="eventableType.value">
                                    {{ eventableType.label }}
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
