<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type Summary = {
    deals_active_count: number;
    pipeline_total: number;
    follow_ups_due_today: number;
    proposals_sent_week: number;
};

type PipelineRow = {
    stage: string;
    label: string;
    count: number;
    value_total: number;
};

type AgendaRow = {
    id: number;
    title: string;
    start_at: string;
    end_at: string;
    location: string | null;
    link: string;
};

type Automations = {
    stalled_deals_count: number;
    recent_executions_count: number;
    unread_notifications_count: number;
    recent_executions: Array<{
        id: number;
        rule_name: string;
        deal_title: string;
        status: string;
        triggered_at: string | null;
    }>;
};

type Leads = {
    total_7d: number;
    converted_7d: number;
    ignored_7d: number;
    new_7d: number;
    total_30d: number;
    conversion_rate_7d: number;
};

type TopProduct = {
    item_id: number;
    name: string;
    quantity_total: number;
    value_total: number;
    deals_count: number;
    link: string;
};

const props = defineProps<{
    summary: Summary;
    pipeline: PipelineRow[];
    agenda: {
        upcoming: AgendaRow[];
        overdue: AgendaRow[];
    };
    automations: Automations;
    leads: Leads;
    top_products: TopProduct[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const currencyFormatter = new Intl.NumberFormat('pt-PT', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 2,
});

const decimalFormatter = new Intl.NumberFormat('pt-PT', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

function formatCurrency(value: number): string {
    return currencyFormatter.format(value ?? 0);
}

function formatQuantity(value: number): string {
    return decimalFormatter.format(value ?? 0);
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Negocios ativos</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.deals_active_count }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Valor total do pipeline</CardDescription>
                        <CardTitle class="text-2xl">{{ formatCurrency(summary.pipeline_total) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Follow-ups por enviar hoje</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.follow_ups_due_today }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Propostas enviadas esta semana</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.proposals_sent_week }}</CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <Card class="xl:col-span-2">
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Pipeline por etapa</CardTitle>
                            <CardDescription>Contagem e valor por etapa do board</CardDescription>
                        </div>
                        <Button as-child size="sm" variant="outline">
                            <Link href="/deals">Abrir negocios</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div v-if="pipeline.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            Sem dados de pipeline.
                        </div>
                        <div v-else class="overflow-x-auto rounded-md border">
                            <table class="min-w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Etapa</th>
                                        <th class="px-3 py-2 text-right font-medium">Negocios</th>
                                        <th class="px-3 py-2 text-right font-medium">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in pipeline" :key="row.stage" class="border-t">
                                        <td class="px-3 py-2">{{ row.label }}</td>
                                        <td class="px-3 py-2 text-right">{{ row.count }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatCurrency(row.value_total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Acoes rapidas</CardTitle>
                            <CardDescription>Acesso direto ao fluxo diario</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-2">
                        <Button as-child variant="outline"><Link href="/deals/create">Criar negocio</Link></Button>
                        <Button as-child variant="outline"><Link href="/people/create">Criar pessoa</Link></Button>
                        <Button as-child variant="outline"><Link href="/calendar/create">Agendar atividade</Link></Button>
                        <Button as-child variant="outline"><Link href="/lead-forms">Formularios publicos</Link></Button>
                        <Button as-child variant="outline"><Link href="/ai/chat">Abrir Chat IA</Link></Button>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Agenda do dia</CardTitle>
                            <CardDescription>Proximas atividades do responsavel atual</CardDescription>
                        </div>
                        <Button as-child size="sm" variant="outline">
                            <Link href="/calendar">Calendario</Link>
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <p class="mb-2 text-sm font-medium">Proximas</p>
                            <div v-if="agenda.upcoming.length === 0" class="text-sm text-muted-foreground">Sem atividades para hoje.</div>
                            <div v-else class="space-y-2">
                                <div v-for="event in agenda.upcoming" :key="`up-${event.id}`" class="rounded-md border p-2">
                                    <div class="font-medium">{{ event.title }}</div>
                                    <div class="text-xs text-muted-foreground">{{ event.start_at }} - {{ event.end_at }}</div>
                                    <div v-if="event.location" class="text-xs text-muted-foreground">Local: {{ event.location }}</div>
                                    <Link :href="event.link" class="text-xs text-blue-700 underline">Abrir</Link>
                                </div>
                            </div>
                        </div>
                        <div>
                            <p class="mb-2 text-sm font-medium">Atrasadas</p>
                            <div v-if="agenda.overdue.length === 0" class="text-sm text-muted-foreground">Sem atividades atrasadas.</div>
                            <div v-else class="space-y-2">
                                <div v-for="event in agenda.overdue" :key="`ov-${event.id}`" class="rounded-md border border-amber-200 bg-amber-50 p-2">
                                    <div class="font-medium">{{ event.title }}</div>
                                    <div class="text-xs text-muted-foreground">{{ event.start_at }} - {{ event.end_at }}</div>
                                    <Link :href="event.link" class="text-xs text-blue-700 underline">Abrir</Link>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Automacoes</CardTitle>
                            <CardDescription>Estado rapido das regras e execucoes</CardDescription>
                        </div>
                        <Button as-child size="sm" variant="outline">
                            <Link href="/automations/deal-rules">Abrir automacoes</Link>
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="rounded-md border p-2 text-center">
                                <div class="text-xs text-muted-foreground">Sem atividade</div>
                                <div class="text-lg font-semibold">{{ automations.stalled_deals_count }}</div>
                            </div>
                            <div class="rounded-md border p-2 text-center">
                                <div class="text-xs text-muted-foreground">Execucoes 7d</div>
                                <div class="text-lg font-semibold">{{ automations.recent_executions_count }}</div>
                            </div>
                            <div class="rounded-md border p-2 text-center">
                                <div class="text-xs text-muted-foreground">Notificacoes por ler</div>
                                <div class="text-lg font-semibold">{{ automations.unread_notifications_count }}</div>
                            </div>
                        </div>
                        <div>
                            <p class="mb-2 text-sm font-medium">Ultimas execucoes</p>
                            <div v-if="automations.recent_executions.length === 0" class="text-sm text-muted-foreground">
                                Sem execucoes recentes.
                            </div>
                            <div v-else class="space-y-2">
                                <div v-for="run in automations.recent_executions" :key="run.id" class="rounded-md border p-2">
                                    <div class="font-medium">{{ run.rule_name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ run.deal_title }}</div>
                                    <div class="text-xs text-muted-foreground">{{ run.triggered_at || '-' }} | {{ run.status }}</div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Leads (formularios)</CardTitle>
                            <CardDescription>Entrada e conversao recente</CardDescription>
                        </div>
                        <Button as-child size="sm" variant="outline">
                            <Link href="/lead-forms">Abrir formularios</Link>
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-md border p-2 text-center">
                                <div class="text-xs text-muted-foreground">Total 7d</div>
                                <div class="text-lg font-semibold">{{ leads.total_7d }}</div>
                            </div>
                            <div class="rounded-md border p-2 text-center">
                                <div class="text-xs text-muted-foreground">Total 30d</div>
                                <div class="text-lg font-semibold">{{ leads.total_30d }}</div>
                            </div>
                            <div class="rounded-md border p-2 text-center">
                                <div class="text-xs text-muted-foreground">Convertidas 7d</div>
                                <div class="text-lg font-semibold">{{ leads.converted_7d }}</div>
                            </div>
                            <div class="rounded-md border p-2 text-center">
                                <div class="text-xs text-muted-foreground">Taxa conversao</div>
                                <div class="text-lg font-semibold">{{ leads.conversion_rate_7d }}%</div>
                            </div>
                            <div class="rounded-md border p-2 text-center">
                                <div class="text-xs text-muted-foreground">Novas 7d</div>
                                <div class="text-lg font-semibold">{{ leads.new_7d }}</div>
                            </div>
                            <div class="rounded-md border p-2 text-center">
                                <div class="text-xs text-muted-foreground">Ignoradas 7d</div>
                                <div class="text-lg font-semibold">{{ leads.ignored_7d }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Top produtos no pipeline</CardTitle>
                        <CardDescription>Produtos com maior impacto atual nos negocios abertos</CardDescription>
                    </div>
                    <Button as-child size="sm" variant="outline">
                        <Link href="/deals/product-stats">Estatisticas de produtos</Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <div v-if="top_products.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Sem produtos associados a negocios em aberto.
                    </div>
                    <div v-else class="overflow-x-auto rounded-md border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Produto</th>
                                    <th class="px-3 py-2 text-right font-medium">Quantidade</th>
                                    <th class="px-3 py-2 text-right font-medium">Valor</th>
                                    <th class="px-3 py-2 text-right font-medium">Negocios</th>
                                    <th class="px-3 py-2 text-right font-medium">Detalhe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in top_products" :key="item.item_id" class="border-t">
                                    <td class="px-3 py-2">{{ item.name }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatQuantity(item.quantity_total) }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(item.value_total) }}</td>
                                    <td class="px-3 py-2 text-right">{{ item.deals_count }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <Link :href="item.link" class="text-blue-700 underline">Abrir</Link>
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

