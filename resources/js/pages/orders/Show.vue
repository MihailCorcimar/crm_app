<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type AppPageProps, type BreadcrumbItem } from '@/types';

type OrderLine = {
    id: number;
    item: string;
    supplier: string | null;
    quantity: number;
    sale_price: number;
    cost_price: number;
    line_total: number;
};

type SupplierOrderItem = {
    id: number;
    number: number;
    supplier: string | null;
    order_date: string | null;
    total: number;
    status: string;
};

type OrderPayload = {
    id: number;
    number: number;
    order_date: string | null;
    valid_until: string | null;
    customer: string | null;
    status: string;
    total: number;
    lines: OrderLine[];
    supplier_orders: SupplierOrderItem[];
};

const props = defineProps<{ order: OrderPayload }>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Encomendas', href: '/orders' },
    { title: `Encomenda #${props.order.number}`, href: `/orders/${props.order.id}` },
];

const page = usePage<AppPageProps<{ errors: Record<string, string> }>>();
const orderError = computed(() => page.props.errors?.order ?? '');

function destroyOrder(): void {
    if (!window.confirm('Eliminar esta encomenda?')) {
        return;
    }
    router.delete(`/orders/${props.order.id}`);
}

function convertToSupplierOrders(): void {
    if (!window.confirm('Converter esta encomenda fechada em encomendas de fornecedor?')) {
        return;
    }

    router.post(`/orders/${props.order.id}/convert-to-supplier-orders`);
}
</script>

<template>
    <Head :title="`Encomenda #${order.number}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Encomenda #{{ order.number }}</CardTitle>
                    <div class="flex gap-2">
                        <Button variant="outline" as-child><Link href="/orders">Voltar</Link></Button>
                        <Button variant="outline" as-child><Link :href="`/orders/${order.id}/edit`">Editar</Link></Button>
                        <Button variant="outline" as-child><a :href="`/orders/${order.id}/pdf`">PDF</a></Button>
                        <Button
                            v-if="order.status === 'closed'"
                            variant="secondary"
                            @click="convertToSupplierOrders"
                        >
                            Converter para Encomenda - Fornecedor
                        </Button>
                        <Button variant="destructive" @click="destroyOrder">Eliminar</Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p v-if="orderError" class="text-sm font-medium text-destructive">{{ orderError }}</p>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div><dt class="text-sm text-muted-foreground">Data</dt><dd>{{ order.order_date || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Validade</dt><dd>{{ order.valid_until || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Cliente</dt><dd>{{ order.customer || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Estado</dt><dd>{{ order.status === 'closed' ? 'Fechado' : 'Rascunho' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Total</dt><dd>{{ order.total.toFixed(2) }} EUR</dd></div>
                    </div>

                    <div class="rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/30">
                                <tr>
                                    <th class="px-3 py-2 text-left">Artigo</th>
                                    <th class="px-3 py-2 text-left">Fornecedor</th>
                                    <th class="px-3 py-2 text-left">Qtd</th>
                                    <th class="px-3 py-2 text-left">Preco</th>
                                    <th class="px-3 py-2 text-left">Preco custo</th>
                                    <th class="px-3 py-2 text-left">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in order.lines" :key="line.id" class="border-t">
                                    <td class="px-3 py-2">{{ line.item }}</td>
                                    <td class="px-3 py-2">{{ line.supplier || '-' }}</td>
                                    <td class="px-3 py-2">{{ line.quantity }}</td>
                                    <td class="px-3 py-2">{{ line.sale_price.toFixed(2) }} EUR</td>
                                    <td class="px-3 py-2">{{ line.cost_price.toFixed(2) }} EUR</td>
                                    <td class="px-3 py-2">{{ line.line_total.toFixed(2) }} EUR</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="order.supplier_orders.length" class="space-y-2">
                        <h3 class="text-sm font-medium">Encomendas de Fornecedor geradas</h3>
                        <div class="rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/30">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Numero</th>
                                        <th class="px-3 py-2 text-left">Fornecedor</th>
                                        <th class="px-3 py-2 text-left">Data</th>
                                        <th class="px-3 py-2 text-left">Total</th>
                                        <th class="px-3 py-2 text-left">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="supplierOrder in order.supplier_orders" :key="supplierOrder.id" class="border-t">
                                        <td class="px-3 py-2">{{ supplierOrder.number }}</td>
                                        <td class="px-3 py-2">{{ supplierOrder.supplier || '-' }}</td>
                                        <td class="px-3 py-2">{{ supplierOrder.order_date || '-' }}</td>
                                        <td class="px-3 py-2">{{ supplierOrder.total.toFixed(2) }} EUR</td>
                                        <td class="px-3 py-2">{{ supplierOrder.status === 'closed' ? 'Fechado' : 'Rascunho' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
