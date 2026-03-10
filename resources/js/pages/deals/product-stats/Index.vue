<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type SelectOption = {
    id: number;
    name: string;
};

type StageOption = {
    value: string;
    label: string;
};

type ProductStat = {
    item_id: number;
    name: string;
    reference: string | null;
    code: string | null;
    total_quantity: number;
    total_value: number;
    deals_count: number;
};

type ProductFilters = {
    owner_id: number | null;
    stage: string | null;
    expected_close_from: string | null;
    expected_close_to: string | null;
    value_min: number | null;
    value_max: number | null;
    sort_by: 'total_value' | 'total_quantity';
    sort_direction: 'asc' | 'desc';
};

const props = defineProps<{
    products: ProductStat[];
    summary: {
        total_products: number;
        total_quantity: number;
        total_value: number;
    };
    filters: ProductFilters;
    owners: SelectOption[];
    stageOptions: StageOption[];
    moduleReady: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Negócios', href: '/deals' },
    { title: 'Estatísticas de produtos', href: '/deals/product-stats' },
];

const filterForm = useForm({
    owner_id: props.filters.owner_id ?? '',
    stage: props.filters.stage ?? '',
    expected_close_from: props.filters.expected_close_from ?? '',
    expected_close_to: props.filters.expected_close_to ?? '',
    value_min: props.filters.value_min ?? '',
    value_max: props.filters.value_max ?? '',
    sort_by: props.filters.sort_by ?? 'total_value',
    sort_direction: props.filters.sort_direction ?? 'desc',
});

const exportUrl = computed(() => {
    const params = buildParams();
    const query = params.toString();

    return query === '' ? '/deals/product-stats/export' : `/deals/product-stats/export?${query}`;
});

function applyFilters(): void {
    filterForm.get('/deals/product-stats', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters(): void {
    filterForm.owner_id = '';
    filterForm.stage = '';
    filterForm.expected_close_from = '';
    filterForm.expected_close_to = '';
    filterForm.value_min = '';
    filterForm.value_max = '';
    filterForm.sort_by = 'total_value';
    filterForm.sort_direction = 'desc';

    applyFilters();
}

function buildParams(): URLSearchParams {
    const params = new URLSearchParams();

    if (filterForm.owner_id !== '') {
        params.set('owner_id', String(filterForm.owner_id));
    }

    if (filterForm.stage !== '') {
        params.set('stage', String(filterForm.stage));
    }

    if (filterForm.expected_close_from !== '') {
        params.set('expected_close_from', String(filterForm.expected_close_from));
    }

    if (filterForm.expected_close_to !== '') {
        params.set('expected_close_to', String(filterForm.expected_close_to));
    }

    if (filterForm.value_min !== '') {
        params.set('value_min', String(filterForm.value_min));
    }

    if (filterForm.value_max !== '') {
        params.set('value_max', String(filterForm.value_max));
    }

    if (filterForm.sort_by !== 'total_value') {
        params.set('sort_by', String(filterForm.sort_by));
    }

    if (filterForm.sort_direction !== 'desc') {
        params.set('sort_direction', String(filterForm.sort_direction));
    }

    return params;
}

function detailUrl(itemId: number): string {
    const query = buildParams().toString();

    return query === '' ? `/deals/product-stats/${itemId}` : `/deals/product-stats/${itemId}?${query}`;
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
    <Head title="Estatísticas de produtos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Filtros</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-3 md:grid-cols-6" @submit.prevent="applyFilters">
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
                            <label class="text-sm font-medium">Etapa</label>
                            <select
                                v-model="filterForm.stage"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option :value="''">Todas</option>
                                <option v-for="stage in stageOptions" :key="stage.value" :value="stage.value">
                                    {{ stage.label }}
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
                            <label class="text-sm font-medium">Valor do negócio (mín.)</label>
                            <Input v-model="filterForm.value_min" type="number" min="0" step="0.01" placeholder="0.00" />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Valor do negócio (máx.)</label>
                            <Input v-model="filterForm.value_max" type="number" min="0" step="0.01" placeholder="10000.00" />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Ordenar por</label>
                            <select
                                v-model="filterForm.sort_by"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="total_value">Valor total</option>
                                <option value="total_quantity">Quantidade total</option>
                            </select>
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Ordem</label>
                            <select
                                v-model="filterForm.sort_direction"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="desc">Maior para menor</option>
                                <option value="asc">Menor para maior</option>
                            </select>
                        </div>

                        <div class="flex gap-2 md:col-span-6">
                            <Button type="submit" :disabled="filterForm.processing">Aplicar filtros</Button>
                            <Button type="button" variant="outline" :disabled="filterForm.processing" @click="clearFilters">Limpar</Button>
                            <Button v-if="moduleReady" as-child variant="secondary">
                                <a :href="exportUrl">Exportar CSV</a>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div class="space-y-1">
                        <CardTitle>Produtos mais presentes nos negócios</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{ summary.total_products }} produto(s) | Quantidade total: {{ formatQuantity(summary.total_quantity) }} | Valor total: {{ formatCurrency(summary.total_value) }}
                        </p>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="!moduleReady" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        O módulo de estatísticas de produtos ainda não está disponível nesta base de dados. Executa as migrations pendentes.
                    </div>

                    <div v-else-if="products.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Sem dados para os filtros selecionados.
                    </div>

                    <div v-else class="overflow-x-auto rounded-md border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Produto</th>
                                    <th class="px-3 py-2 text-left font-medium">Ref.</th>
                                    <th class="px-3 py-2 text-left font-medium">Código</th>
                                    <th class="px-3 py-2 text-right font-medium">Quantidade</th>
                                    <th class="px-3 py-2 text-right font-medium">Valor</th>
                                    <th class="px-3 py-2 text-right font-medium">Negócios</th>
                                    <th class="px-3 py-2 text-right font-medium">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in products" :key="product.item_id" class="border-t">
                                    <td class="px-3 py-2">{{ product.name }}</td>
                                    <td class="px-3 py-2">{{ product.reference || '-' }}</td>
                                    <td class="px-3 py-2">{{ product.code || '-' }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatQuantity(product.total_quantity) }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(product.total_value) }}</td>
                                    <td class="px-3 py-2 text-right">{{ product.deals_count }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <Button as-child size="sm" variant="outline">
                                            <Link :href="detailUrl(product.item_id)">Ver detalhe</Link>
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
