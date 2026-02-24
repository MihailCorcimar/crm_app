<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type MonthlyRow = {
    key: string;
    label: string;
    proposals_total: number;
    orders_total: number;
    proposals_count: number;
    orders_count: number;
};

type TopCustomerRow = {
    customer_id: number | null;
    name: string;
    orders_count: number;
    total_revenue: number;
};

const props = defineProps<{
    tenantDetails: {
        id: number;
        name: string;
        slug: string;
    };
    window: {
        from: string;
        to: string;
    };
    kpis: {
        closed_order_revenue: number;
        closed_order_count: number;
        closed_proposal_count: number;
        converted_proposal_count: number;
        conversion_rate: number;
        average_order_value: number;
        paid_invoice_total: number;
        pending_invoice_count: number;
        overdue_invoice_count: number;
    };
    monthly: MonthlyRow[];
    topCustomers: TopCustomerRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tenants', href: '/tenants' },
    { title: 'Planos e faturacao', href: '/tenants/billing' },
    { title: 'Relatorios premium', href: '/tenants/billing/premium-reports' },
];

const maxMonthlyOrderTotal = computed(() =>
    props.monthly.reduce((max, row) => Math.max(max, row.orders_total), 0),
);

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function monthBarPercent(value: number): number {
    if (maxMonthlyOrderTotal.value <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, Math.round((value / maxMonthlyOrderTotal.value) * 100)));
}

function toPercent(value: number): string {
    return `${value.toFixed(1)}%`;
}
</script>

<template>
    <Head title="Relatorios premium" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-start justify-between gap-3">
                    <div>
                        <CardTitle>Relatorios premium</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Tenant ativo:
                            <span class="font-medium text-foreground">{{ tenantDetails.name }}</span>
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Janela de analise: {{ window.from }} ate {{ window.to }}
                        </p>
                    </div>
                    <Button variant="outline" as-child>
                        <Link href="/tenants/billing">Voltar</Link>
                    </Button>
                </CardHeader>
            </Card>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Receita (encomendas fechadas)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">{{ formatCurrency(kpis.closed_order_revenue) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ kpis.closed_order_count }} encomendas fechadas
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Taxa de conversao</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">{{ toPercent(kpis.conversion_rate) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ kpis.converted_proposal_count }} de {{ kpis.closed_proposal_count }} propostas fechadas
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Ticket medio</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">{{ formatCurrency(kpis.average_order_value) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Valor medio por encomenda fechada
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Faturas fornecedor</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p class="text-sm">
                            Total pago:
                            <span class="font-semibold">{{ formatCurrency(kpis.paid_invoice_total) }}</span>
                        </p>
                        <p class="text-sm">
                            Pendentes:
                            <span class="font-semibold">{{ kpis.pending_invoice_count }}</span>
                        </p>
                        <p class="text-sm">
                            Em atraso:
                            <span class="font-semibold text-red-600">{{ kpis.overdue_invoice_count }}</span>
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <Card class="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>Evolucao mensal</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="row in monthly"
                            :key="row.key"
                            class="rounded-md border p-3"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium">{{ row.label }}</p>
                                <Badge variant="secondary">
                                    {{ row.orders_count }} encomendas
                                </Badge>
                            </div>
                            <div class="mt-2 grid gap-1 text-sm text-muted-foreground sm:grid-cols-2">
                                <p>Propostas: {{ formatCurrency(row.proposals_total) }} ({{ row.proposals_count }})</p>
                                <p>Encomendas: {{ formatCurrency(row.orders_total) }} ({{ row.orders_count }})</p>
                            </div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded bg-slate-200">
                                <div
                                    class="h-2 rounded bg-emerald-500"
                                    :style="{ width: `${monthBarPercent(row.orders_total)}%` }"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Top clientes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table v-if="topCustomers.length > 0">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Cliente</TableHead>
                                    <TableHead class="text-right">Encomendas</TableHead>
                                    <TableHead class="text-right">Total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="customer in topCustomers"
                                    :key="customer.customer_id ?? customer.name"
                                >
                                    <TableCell class="font-medium">{{ customer.name }}</TableCell>
                                    <TableCell class="text-right">{{ customer.orders_count }}</TableCell>
                                    <TableCell class="text-right">{{ formatCurrency(customer.total_revenue) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <p v-else class="text-sm text-muted-foreground">
                            Sem dados suficientes para top clientes neste periodo.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

