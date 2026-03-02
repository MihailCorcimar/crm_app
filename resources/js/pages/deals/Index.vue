<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type SelectOption = {
    id: number;
    name: string;
};

type DealCard = {
    id: number;
    title: string;
    entity: string | null;
    stage: string;
    value: number;
    probability: number;
    expected_close_date: string | null;
    owner: string | null;
};

type KanbanColumn = {
    stage: string;
    label: string;
    count: number;
    total_value: number;
    deals: DealCard[];
};

type KanbanFilters = {
    owner_id: number | null;
    expected_close_from: string | null;
    expected_close_to: string | null;
    value_min: number | null;
    value_max: number | null;
};

const props = defineProps<{
    columns: KanbanColumn[];
    owners: SelectOption[];
    filters: KanbanFilters;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Negócios', href: '/deals' }];

const filterForm = useForm({
    owner_id: props.filters.owner_id ?? '',
    expected_close_from: props.filters.expected_close_from ?? '',
    expected_close_to: props.filters.expected_close_to ?? '',
    value_min: props.filters.value_min ?? '',
    value_max: props.filters.value_max ?? '',
});

const localColumns = ref<KanbanColumn[]>(cloneColumns(props.columns));
const draggingDealId = ref<number | null>(null);
const dragSourceStage = ref<string | null>(null);
const dropTargetStage = ref<string | null>(null);
const isSaving = ref(false);

watch(
    () => props.columns,
    (value) => {
        localColumns.value = cloneColumns(value);
    },
    { deep: true },
);

const totalPipelineValue = computed(() =>
    localColumns.value.reduce((sum, column) => sum + column.total_value, 0),
);

function cloneColumns(columns: KanbanColumn[]): KanbanColumn[] {
    return columns.map((column) => ({
        ...column,
        deals: column.deals.map((deal) => ({ ...deal })),
    }));
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function applyFilters(): void {
    filterForm.get('/deals', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters(): void {
    filterForm.owner_id = '';
    filterForm.expected_close_from = '';
    filterForm.expected_close_to = '';
    filterForm.value_min = '';
    filterForm.value_max = '';

    applyFilters();
}

function updateColumnStats(stage: string): void {
    const column = localColumns.value.find((item) => item.stage === stage);

    if (!column) {
        return;
    }

    column.count = column.deals.length;
    column.total_value = column.deals.reduce((sum, deal) => sum + deal.value, 0);
}

function moveDealLocally(dealId: number, toStage: string): boolean {
    let sourceColumn: KanbanColumn | undefined;
    let targetColumn: KanbanColumn | undefined;
    let dealIndex = -1;

    for (const column of localColumns.value) {
        const index = column.deals.findIndex((deal) => deal.id === dealId);

        if (index !== -1) {
            sourceColumn = column;
            dealIndex = index;
            break;
        }
    }

    targetColumn = localColumns.value.find((column) => column.stage === toStage);

    if (!sourceColumn || !targetColumn || dealIndex === -1) {
        return false;
    }

    const [deal] = sourceColumn.deals.splice(dealIndex, 1);

    if (!deal) {
        return false;
    }

    deal.stage = toStage;
    targetColumn.deals.unshift(deal);

    updateColumnStats(sourceColumn.stage);
    updateColumnStats(targetColumn.stage);

    return true;
}

function clearDragState(): void {
    draggingDealId.value = null;
    dragSourceStage.value = null;
    dropTargetStage.value = null;
}

function onDragStart(event: DragEvent, dealId: number, stage: string): void {
    draggingDealId.value = dealId;
    dragSourceStage.value = stage;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(dealId));
    }
}

function onDragOver(event: DragEvent, stage: string): void {
    event.preventDefault();
    dropTargetStage.value = stage;

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onDragLeave(stage: string): void {
    if (dropTargetStage.value === stage) {
        dropTargetStage.value = null;
    }
}

function onDrop(event: DragEvent, stage: string): void {
    event.preventDefault();

    if (isSaving.value) {
        return;
    }

    const dealId = draggingDealId.value;
    const sourceStage = dragSourceStage.value;

    if (!dealId || !sourceStage || sourceStage === stage) {
        clearDragState();
        return;
    }

    const snapshot = cloneColumns(localColumns.value);
    const moved = moveDealLocally(dealId, stage);

    if (!moved) {
        clearDragState();
        return;
    }

    isSaving.value = true;

    router.patch(
        `/deals/${dealId}/stage`,
        { stage },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                localColumns.value = snapshot;
            },
            onFinish: () => {
                isSaving.value = false;
                clearDragState();
            },
        },
    );
}
</script>

<template>
    <Head title="Negócios - Kanban" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Filtros do Kanban</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-3 md:grid-cols-5" @submit.prevent="applyFilters">
                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Responsável</label>
                            <select
                                v-model="filterForm.owner_id"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option :value="''">Todos</option>
                                <option v-for="owner in owners" :key="owner.id" :value="owner.id">
                                    {{ owner.name }}
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Data prevista (de)</label>
                            <Input v-model="filterForm.expected_close_from" type="date" />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Data prevista (até)</label>
                            <Input v-model="filterForm.expected_close_to" type="date" />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Valor mínimo</label>
                            <Input v-model="filterForm.value_min" type="number" min="0" step="0.01" placeholder="0.00" />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Valor máximo</label>
                            <Input v-model="filterForm.value_max" type="number" min="0" step="0.01" placeholder="10000.00" />
                        </div>

                        <div class="flex gap-2 md:col-span-5">
                            <Button type="submit" :disabled="filterForm.processing">Aplicar filtros</Button>
                            <Button type="button" variant="outline" :disabled="filterForm.processing" @click="clearFilters">Limpar</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div class="space-y-1">
                        <CardTitle>Pipeline de Negócios</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Total do pipeline: {{ formatCurrency(totalPipelineValue) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span v-if="isSaving" class="text-xs text-muted-foreground">A atualizar etapa...</span>
                        <Button as-child>
                            <Link href="/deals/create">Criar negócio</Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex gap-4 overflow-x-auto pb-2">
                        <section
                            v-for="column in localColumns"
                            :key="column.stage"
                            class="bg-muted/30 flex min-h-[28rem] w-80 min-w-80 flex-col rounded-lg border p-3"
                            :class="dropTargetStage === column.stage ? 'border-primary ring-primary/30 ring-2' : ''"
                            @dragover="onDragOver($event, column.stage)"
                            @dragleave="onDragLeave(column.stage)"
                            @drop="onDrop($event, column.stage)"
                        >
                            <header class="mb-3 border-b pb-3">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold">{{ column.label }}</h3>
                                    <span class="text-xs text-muted-foreground">{{ column.count }} negócio(s)</span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Total: {{ formatCurrency(column.total_value) }}
                                </p>
                            </header>

                            <div class="flex flex-1 flex-col gap-3">
                                <article
                                    v-for="deal in column.deals"
                                    :key="deal.id"
                                    class="bg-background cursor-grab rounded-md border p-3 shadow-xs"
                                    draggable="true"
                                    @dragstart="onDragStart($event, deal.id, column.stage)"
                                >
                                    <div class="mb-1 flex items-start justify-between gap-2">
                                        <Link
                                            :href="`/deals/${deal.id}`"
                                            class="text-sm font-medium leading-snug underline-offset-4 hover:underline"
                                        >
                                            {{ deal.title }}
                                        </Link>
                                        <span class="text-xs font-semibold text-muted-foreground">
                                            {{ deal.probability }}%
                                        </span>
                                    </div>

                                    <p class="text-xs text-muted-foreground">{{ deal.entity || 'Sem entidade' }}</p>
                                    <p class="text-xs text-muted-foreground">Responsável: {{ deal.owner || '-' }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        Fecho previsto: {{ deal.expected_close_date || '-' }}
                                    </p>
                                    <p class="mt-2 text-sm font-semibold">{{ formatCurrency(deal.value) }}</p>
                                </article>

                                <div
                                    v-if="column.deals.length === 0"
                                    class="text-muted-foreground grid flex-1 place-items-center rounded-md border border-dashed p-4 text-xs"
                                >
                                    Arrasta um negócio para esta etapa.
                                </div>
                            </div>
                        </section>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>