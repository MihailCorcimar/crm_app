<script setup lang="ts">
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

type CalendarTimeState = 'past' | 'today' | 'upcoming';

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
        entity_attendees: string[];
        person_attendees: string[];
        deal_attendees: string[];
        status: string;
        time_state: CalendarTimeState;
    };
};

const props = defineProps<{
    events: CalendarEventItem[];
    owners: SelectOption[];
    eventableTypes: EventableTypeOption[];
    filters: {
        owner_id: string | number;
        eventable_type: string;
        time_scope: 'all' | 'upcoming' | 'today' | 'past';
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CalendÃ¡rio', href: '/calendar' },
];

const selectedOwnerId = ref<string>(String(props.filters.owner_id ?? ''));
const selectedEventableType = ref<string>(String(props.filters.eventable_type ?? ''));
const selectedTimeScope = ref<string>(String(props.filters.time_scope ?? 'all'));
const calendarShellRef = ref<HTMLElement | null>(null);

const hoverTooltip = ref<{
    visible: boolean;
    x: number;
    y: number;
    title: string;
    period: string;
    type: string;
    action: string;
    eventable: string;
    entities: string[];
    people: string[];
    deals: string[];
    timeState: CalendarTimeState;
}>({
    visible: false,
    x: 0,
    y: 0,
    title: '',
    period: '',
    type: '-',
    action: '-',
    eventable: '-',
    entities: [],
    people: [],
    deals: [],
    timeState: 'upcoming',
});

type CalendarHoverInfo = {
    jsEvent: MouseEvent;
    el?: Element;
    event: {
        title: string;
        start: Date | null;
        end: Date | null;
        extendedProps: Record<string, unknown>;
    };
};

const dateTimeFormatter = new Intl.DateTimeFormat('pt-PT', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function toStringList(value: unknown): string[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((item): item is string => typeof item === 'string')
        .map((item) => item.trim())
        .filter((item) => item.length > 0);
}

function toTimeState(value: unknown): CalendarTimeState {
    if (value === 'past' || value === 'today' || value === 'upcoming') {
        return value;
    }

    return 'upcoming';
}

function timeStateLabel(value: CalendarTimeState): string {
    if (value === 'past') {
        return 'Passado';
    }

    if (value === 'today') {
        return 'Hoje';
    }

    return 'PrÃ³ximo';
}

function timeStateBadgeClass(value: CalendarTimeState): string {
    if (value === 'past') {
        return 'bg-zinc-100 text-zinc-700 border-zinc-200';
    }

    if (value === 'today') {
        return 'bg-amber-100 text-amber-800 border-amber-200';
    }

    return 'bg-emerald-100 text-emerald-800 border-emerald-200';
}


function formatPeriod(start: Date | null, end: Date | null): string {
    if (!start) {
        return '-';
    }

    const startLabel = dateTimeFormatter.format(start);
    const endLabel = end ? dateTimeFormatter.format(end) : '-';

    return `${startLabel} - ${endLabel}`;
}

function toTooltipText(value: unknown): string {
    if (typeof value !== 'string') {
        return '-';
    }

    const text = value.trim();

    return text.length > 0 ? text : '-';
}

function showEventTooltip(info: CalendarHoverInfo): void {
    const shellElement = calendarShellRef.value;
    if (!shellElement) {
        return;
    }

    const tooltipWidth = 420;
    const tooltipHeight = 340;
    const margin = 8;
    const sideOffset = 10;

    const shellRect = shellElement.getBoundingClientRect();

    let preferredX = (info.jsEvent.clientX - shellRect.left) + 16;
    let preferredY = (info.jsEvent.clientY - shellRect.top) + 12;

    if (info.el instanceof HTMLElement) {
        const rect = info.el.getBoundingClientRect();
        const rightSpace = shellRect.right - rect.right;
        const leftSpace = rect.left - shellRect.left;

        if (rightSpace >= tooltipWidth + sideOffset) {
            preferredX = (rect.right - shellRect.left) + sideOffset;
        } else if (leftSpace >= tooltipWidth + sideOffset) {
            preferredX = (rect.left - shellRect.left) - tooltipWidth - sideOffset;
        } else {
            preferredX = (rect.left - shellRect.left) + (rect.width / 2) - (tooltipWidth / 2);
        }

        preferredY = (rect.top - shellRect.top) + (rect.height / 2) - (tooltipHeight / 2);
    }

    const maxX = Math.max(margin, shellRect.width - tooltipWidth - margin);
    const maxY = Math.max(margin, shellRect.height - tooltipHeight - margin);

    const x = Math.max(margin, Math.min(preferredX, maxX));
    const y = Math.max(margin, Math.min(preferredY, maxY));

    hoverTooltip.value = {
        visible: true,
        x,
        y,
        title: info.event.title || 'Atividade',
        period: formatPeriod(info.event.start, info.event.end),
        type: toTooltipText(info.event.extendedProps.type),
        action: toTooltipText(info.event.extendedProps.action),
        eventable: toTooltipText(info.event.extendedProps.eventable),
        entities: toStringList(info.event.extendedProps.entity_attendees),
        people: toStringList(info.event.extendedProps.person_attendees),
        deals: toStringList(info.event.extendedProps.deal_attendees),
        timeState: toTimeState(info.event.extendedProps.time_state),
    };
}

function hideEventTooltip(): void {
    hoverTooltip.value.visible = false;
}

watch(
    () => props.filters,
    (value) => {
        selectedOwnerId.value = String(value.owner_id ?? '');
        selectedEventableType.value = String(value.eventable_type ?? '');
        selectedTimeScope.value = String(value.time_scope ?? 'all');
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
    eventClassNames: (arg: { event: { extendedProps: Record<string, unknown> } }) => {
        const state = toTimeState(arg.event.extendedProps.time_state);

        return {
            past: ['crm-fc-event', 'crm-fc-event-past'],
            today: ['crm-fc-event', 'crm-fc-event-today'],
            upcoming: ['crm-fc-event', 'crm-fc-event-upcoming'],
        }[state];
    },
    eventMouseEnter: (info: CalendarHoverInfo) => {
        showEventTooltip(info);
    },
    eventMouseLeave: () => {
        hideEventTooltip();
    },
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
            time_scope: selectedTimeScope.value || 'all',
        },
        {
            preserveState: true,
        },
    );
}

</script>

<template>
    <Head title="CalendÃ¡rio" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <CardTitle>CalendÃ¡rio</CardTitle>
                    <Button as-child>
                        <Link href="/calendar/create">Agendar atividade</Link>
                    </Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <form class="grid gap-4 md:grid-cols-4" @submit.prevent="applyFilters">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Filtrar por responsÃ¡vel</label>
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
                            <label class="text-sm font-medium">Filtrar por associaÃ§Ã£o</label>
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

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">PerÃ­odo</label>
                            <select
                                v-model="selectedTimeScope"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="all">Todos</option>
                                <option value="upcoming">Hoje e prÃ³ximos</option>
                                <option value="today">A decorrer hoje</option>
                                <option value="past">Passados</option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <Button type="submit">Filtrar</Button>
                            <Button type="button" variant="outline" as-child>
                                <Link href="/calendar">Limpar</Link>
                            </Button>
                        </div>
                    </form>

                    <div ref="calendarShellRef" class="crm-calendar-shell relative">
                        <div class="crm-calendar">
                            <FullCalendar :options="calendarOptions" />
                        </div>

                        <div
                        v-if="hoverTooltip.visible"
                        class="crm-event-tooltip pointer-events-none absolute z-50 w-[26rem] max-w-[calc(100%-1rem)] rounded-md border bg-background p-3 shadow-lg"
                        :style="{ left: `${hoverTooltip.x}px`, top: `${hoverTooltip.y}px` }"
                    >
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold">{{ hoverTooltip.title }}</p>
                            <Badge variant="outline" :class="timeStateBadgeClass(hoverTooltip.timeState)">
                                {{ timeStateLabel(hoverTooltip.timeState) }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">{{ hoverTooltip.period }}</p>

                        <dl class="mt-2 space-y-2 text-xs">
                            <div>
                                <dt class="font-medium text-muted-foreground">Tipo</dt>
                                <dd>{{ hoverTooltip.type }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-muted-foreground">AÃ§Ã£o</dt>
                                <dd>{{ hoverTooltip.action }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-muted-foreground">AssociaÃ§Ã£o principal</dt>
                                <dd>{{ hoverTooltip.eventable }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-muted-foreground">Entidades</dt>
                                <dd>{{ hoverTooltip.entities.length > 0 ? hoverTooltip.entities.join(', ') : '-' }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-muted-foreground">Pessoas</dt>
                                <dd>{{ hoverTooltip.people.length > 0 ? hoverTooltip.people.join(', ') : '-' }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-muted-foreground">NegÃ³cios</dt>
                                <dd>{{ hoverTooltip.deals.length > 0 ? hoverTooltip.deals.join(', ') : '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>


