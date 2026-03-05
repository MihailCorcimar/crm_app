<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type ItemRow = {
    id: number;
    reference: string;
    code: string;
    name: string;
    price: number;
    vat: number;
    status: 'active' | 'inactive';
    vat_rate_name: string | null;
};

type ItemPaginator = {
    data: ItemRow[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

const props = defineProps<{
    items: ItemPaginator;
    filters: {
        q: string;
        status: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Produtos', href: '/items' }];

const filterForm = useForm({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

function applyFilters(): void {
    filterForm.get('/items', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters(): void {
    filterForm.q = '';
    filterForm.status = '';
    applyFilters();
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function deactivate(itemId: number): void {
    router.patch(`/items/${itemId}/deactivate`, {}, { preserveScroll: true });
}

function activate(itemId: number): void {
    router.patch(`/items/${itemId}/activate`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Produtos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Filtros</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-3 md:grid-cols-3" @submit.prevent="applyFilters">
                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Pesquisar</label>
                            <Input v-model="filterForm.q" placeholder="Nome, SKU ou codigo" />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Estado</label>
                            <select
                                v-model="filterForm.status"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option :value="''">Todos</option>
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <Button type="submit" :disabled="filterForm.processing">Filtrar</Button>
                            <Button type="button" variant="outline" :disabled="filterForm.processing" @click="clearFilters">Limpar</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Catalogo de produtos</CardTitle>
                    <Button as-child>
                        <Link href="/items/create">Criar produto</Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <div v-if="items.data.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Sem produtos para os filtros selecionados.
                    </div>

                    <template v-else>
                        <div class="overflow-x-auto rounded-md border">
                            <table class="min-w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">SKU</th>
                                        <th class="px-3 py-2 text-left font-medium">Código</th>
                                        <th class="px-3 py-2 text-left font-medium">Nome</th>
                                        <th class="px-3 py-2 text-right font-medium">Preço</th>
                                        <th class="px-3 py-2 text-right font-medium">IVA</th>
                                        <th class="px-3 py-2 text-left font-medium">Estado</th>
                                        <th class="px-3 py-2 text-right font-medium">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in items.data" :key="item.id" class="border-t">
                                        <td class="px-3 py-2">{{ item.reference }}</td>
                                        <td class="px-3 py-2">{{ item.code }}</td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium">{{ item.name }}</div>
                                            <div v-if="item.vat_rate_name" class="text-xs text-muted-foreground">Taxa: {{ item.vat_rate_name }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ formatCurrency(item.price) }}</td>
                                        <td class="px-3 py-2 text-right">{{ item.vat }}%</td>
                                        <td class="px-3 py-2">
                                            <span
                                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                                :class="item.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-200 text-zinc-700'"
                                            >
                                                {{ item.status === 'active' ? 'Ativo' : 'Inativo' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <div class="flex justify-end gap-2">
                                                <Button as-child size="sm" variant="outline">
                                                    <Link :href="`/items/${item.id}/edit`">Editar</Link>
                                                </Button>
                                                <Button
                                                    v-if="item.status === 'active'"
                                                    size="sm"
                                                    variant="outline"
                                                    @click="deactivate(item.id)"
                                                >
                                                    Desativar
                                                </Button>
                                                <Button
                                                    v-else
                                                    size="sm"
                                                    variant="outline"
                                                    @click="activate(item.id)"
                                                >
                                                    Ativar
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                v-for="link in items.links"
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

