<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type StageOption = {
    value: string;
    label: string;
};

type ProductFilters = {
    owner_id: number | null;
    stage: string | null;
    expected_close_from: string | null;
    expected_close_to: string | null;
    value_min: number | null;
    value_max: number | null;
};

type DealRow = {
    deal_id: number;
    deal_title: string;
    deal_stage: string;
    deal_value: number;
    expected_close_date: string | null;
    entity_name: string | null;
    owner_name: string | null;
    total_quantity: number;
    total_value: number;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    item: {
        id: number;
        name: string;
        reference: string | null;
        code: string | null;
    };
    totals: {
        total_quantity: number;
        total_value: number;
        deals_count: number;
    };
    deals: {
        data: DealRow[];
        links: PaginationLink[];
    };
    filters: ProductFilters;
    stageOptions: StageOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Negócios', href: '/deals' },
    { title: 'Estatísticas de produtos', href: '/deals/product-stats' },
    { title: props.item.name, href: `/deals/product-stats/${props.item.id}` },
];

const backUrl = computed(() => {
    const params = new URLSearchParams();

    if (props.filters.owner_id !== null) {
        params.set('owner_id', String(props.filters.owner_id));
    }

    if (props.filters.stage !== null) {
        params.set('stage', props.filters.stage);
    }

    if (props.filters.expected_close_from !== null) {
        params.set('expected_close_from', props.filters.expected_close_from);
    }

    if (props.filters.expected_close_to !== null) {
        params.set('expected_close_to', props.filters.expected_close_to);
    }

    if (props.filters.value_min !== null) {
        params.set('value_min', String(props.filters.value_min));
    }

    if (props.filters.value_max !== null) {
        params.set('value_max', String(props.filters.value_max));
    }

    const query = params.toString();

    return query === '' ? '/deals/product-stats' : `/deals/product-stats?${query}`;
});

const stageLabelMap = computed(() => {
    const map = new Map<string, string>();

    for (const stage of props.stageOptions) {
        map.set(stage.value, stage.label);
    }

    return map;
});

function stageLabel(value: string): string {
    return stageLabelMap.value.get(value) ?? value;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function formatQuantity(value: number): string {
    return new Intl.NumberFormat('pt-PT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
}
</script>

<template>
    <Head :title="`Produto - ${item.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div class="space-y-1">
                        <CardTitle>{{ item.name }}</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Ref.: {{ item.reference || '-' }} | Código: {{ item.code || '-' }}
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="backUrl">Voltar às estatísticas</Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        Quantidade total: {{ formatQuantity(totals.total_quantity) }} | Valor total: {{ formatCurrency(totals.total_value) }} | Negócios: {{ totals.deals_count }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Negócios onde este produto aparece</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="deals.data.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Não existem negócios para este produto com os filtros atuais.
                    </div>

                    <template v-else>
                        <div class="overflow-x-auto rounded-md border">
                            <table class="min-w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Negócio</th>
                                        <th class="px-3 py-2 text-left font-medium">Entidade</th>
                                        <th class="px-3 py-2 text-left font-medium">Responsável</th>
                                        <th class="px-3 py-2 text-left font-medium">Etapa</th>
                                        <th class="px-3 py-2 text-left font-medium">Data prevista</th>
                                        <th class="px-3 py-2 text-right font-medium">Quantidade</th>
                                        <th class="px-3 py-2 text-right font-medium">Valor do produto</th>
                                        <th class="px-3 py-2 text-right font-medium">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="deal in deals.data" :key="deal.deal_id" class="border-t">
                                        <td class="px-3 py-2">{{ deal.deal_title }}</td>
                                        <td class="px-3 py-2">{{ deal.entity_name || '-' }}</td>
                                        <td class="px-3 py-2">{{ deal.owner_name || '-' }}</td>
                                        <td class="px-3 py-2">{{ stageLabel(deal.deal_stage) }}</td>
                                        <td class="px-3 py-2">{{ deal.expected_close_date || '-' }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatQuantity(deal.total_quantity) }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatCurrency(deal.total_value) }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <Button as-child size="sm" variant="outline">
                                                <Link :href="`/deals/${deal.deal_id}`">Abrir</Link>
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                v-for="link in deals.links"
                                :key="`${link.label}-${link.url}`"
                                as-child
                                size="sm"
                                :variant="link.active ? 'default' : 'outline'"
                                :disabled="!link.url"
                            >
                                <Link v-if="link.url" :href="link.url" v-html="link.label" />
                                <span v-else v-html="link.label" />
                            </Button>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

