<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

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

const props = defineProps<{
    columns: KanbanColumn[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Negócios', href: '/deals' }];

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
