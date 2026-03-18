<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type RuleRow = {
    id: number;
    name: string;
    inactivity_days: number;
    activity_type: string;
    activity_due_in_days: number;
    activity_priority: string;
    notify_internal: boolean;
    status: 'active' | 'paused';
    updated_at: string | null;
};

type NotificationRow = {
    id: number;
    title: string;
    message: string | null;
    deal_id: number | null;
    calendar_event_id: number | null;
    read_at: string | null;
    created_at: string | null;
};

type RunRow = {
    id: number;
    rule_name: string;
    deal_id: number;
    deal_title: string;
    status: string;
    status_reason: string | null;
    triggered_at: string | null;
};

type PaginatedRules = {
    data: RuleRow[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

const props = defineProps<{
    rules: PaginatedRules;
    notifications: NotificationRow[];
    runs: RunRow[];
    unreadNotificationsCount: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Automações', href: '/automations/deal-rules' },
];

function runNow(): void {
    router.post('/automations/deal-rules/run-now', {}, { preserveScroll: true });
}

function toggleStatus(ruleId: number): void {
    router.patch(`/automations/deal-rules/${ruleId}/toggle-status`, {}, { preserveScroll: true });
}

function destroyRule(ruleId: number): void {
    if (!window.confirm('Remover esta regra de automacao?')) {
        return;
    }

    router.delete(`/automations/deal-rules/${ruleId}`);
}

function markNotificationRead(notificationId: number): void {
    router.patch(`/automations/notifications/${notificationId}/read`, {}, { preserveScroll: true });
}

function markAllNotificationsRead(): void {
    router.patch('/automations/notifications/read-all', {}, { preserveScroll: true });
}

function activityTypeLabel(value: string): string {
    return ({
        call: 'Chamada',
        task: 'Tarefa',
        meeting: 'Reuniao',
        note: 'Nota',
    }[value] ?? value);
}

function priorityLabel(value: string): string {
    return ({
        low: 'Baixa',
        medium: 'Media',
        high: 'Alta',
    }[value] ?? value);
}
</script>

<template>
    <Head title="Automações de negocios" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Gestao de automações</CardTitle>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="runNow">Executar agora</Button>
                        <Button as-child>
                            <Link href="/automations/deal-rules/create">Nova regra</Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="rules.data.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Sem regras de automacao.
                    </div>

                    <template v-else>
                        <div class="overflow-x-auto rounded-md border">
                            <table class="min-w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Regra</th>
                                        <th class="px-3 py-2 text-left font-medium">Condicao</th>
                                        <th class="px-3 py-2 text-left font-medium">Ação</th>
                                        <th class="px-3 py-2 text-left font-medium">Estado</th>
                                        <th class="px-3 py-2 text-left font-medium">Atualizada</th>
                                        <th class="px-3 py-2 text-right font-medium">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="rule in rules.data" :key="rule.id" class="border-t">
                                        <td class="px-3 py-2 font-medium">{{ rule.name }}</td>
                                        <td class="px-3 py-2">Sem atividade ha {{ rule.inactivity_days }} dia(s)</td>
                                        <td class="px-3 py-2">
                                            {{ activityTypeLabel(rule.activity_type) }} | Prioridade {{ priorityLabel(rule.activity_priority) }} | Prazo {{ rule.activity_due_in_days }} dia(s)
                                        </td>
                                        <td class="px-3 py-2">
                                            <span
                                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                                :class="rule.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-200 text-zinc-700'"
                                            >
                                                {{ rule.status === 'active' ? 'Ativa' : 'Pausada' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">{{ rule.updated_at || '-' }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <div class="flex justify-end gap-2">
                                                <Button as-child size="sm" variant="outline">
                                                    <Link :href="`/automations/deal-rules/${rule.id}/edit`">Editar</Link>
                                                </Button>
                                                <Button size="sm" variant="outline" @click="toggleStatus(rule.id)">
                                                    {{ rule.status === 'active' ? 'Pausar' : 'Ativar' }}
                                                </Button>
                                                <Button size="sm" variant="destructive" @click="destroyRule(rule.id)">
                                                    Remover
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                v-for="link in rules.links"
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

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Notificacoes internas ({{ unreadNotificationsCount }} por ler)</CardTitle>
                    <Button
                        v-if="unreadNotificationsCount > 0"
                        variant="outline"
                        size="sm"
                        @click="markAllNotificationsRead"
                    >
                        Marcar todas como lidas
                    </Button>
                </CardHeader>
                <CardContent>
                    <div v-if="notifications.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Sem notificacoes.
                    </div>

                    <div v-else class="space-y-2">
                        <div v-for="notification in notifications" :key="notification.id" class="rounded-md border p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium">{{ notification.title }}</p>
                                    <p class="text-xs text-muted-foreground">{{ notification.created_at || '-' }}</p>
                                </div>
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="notification.read_at ? 'bg-zinc-200 text-zinc-700' : 'bg-emerald-100 text-emerald-800'"
                                >
                                    {{ notification.read_at ? 'Lida' : 'Por ler' }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">{{ notification.message || '-' }}</p>
                            <div class="mt-3 flex gap-2">
                                <Button
                                    v-if="notification.deal_id !== null"
                                    as-child
                                    variant="outline"
                                    size="sm"
                                >
                                    <Link :href="`/deals/${notification.deal_id}`">Abrir negocio</Link>
                                </Button>
                                <Button
                                    v-if="!notification.read_at"
                                    variant="outline"
                                    size="sm"
                                    @click="markNotificationRead(notification.id)"
                                >
                                    Marcar como lida
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Ultimas execucoes</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="runs.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Sem execucoes registadas.
                    </div>
                    <div v-else class="overflow-x-auto rounded-md border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Data</th>
                                    <th class="px-3 py-2 text-left font-medium">Regra</th>
                                    <th class="px-3 py-2 text-left font-medium">Negocio</th>
                                    <th class="px-3 py-2 text-left font-medium">Estado</th>
                                    <th class="px-3 py-2 text-left font-medium">Detalhe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="run in runs" :key="run.id" class="border-t">
                                    <td class="px-3 py-2">{{ run.triggered_at || '-' }}</td>
                                    <td class="px-3 py-2">{{ run.rule_name }}</td>
                                    <td class="px-3 py-2">{{ run.deal_title }}</td>
                                    <td class="px-3 py-2">{{ run.status }}</td>
                                    <td class="px-3 py-2">{{ run.status_reason || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

