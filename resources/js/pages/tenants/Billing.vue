<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, type CSSProperties } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type PlanItem = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    price_cents: number;
    billing_cycle_days: number;
    max_users: number | null;
    max_customers: number | null;
    storage_limit_gb: number | null;
    trial_days: number;
    features: string[];
};

type AuditLogItem = {
    id: number;
    change_type: string;
    effective_at: string | null;
    proration_amount_cents: number;
    actor: { id: number; name: string; email: string } | null;
    from_plan: { id: number; code: string; name: string } | null;
    to_plan: { id: number; code: string; name: string } | null;
    metadata: Record<string, unknown>;
};

type AuditSummaryItem = {
    key: string;
    change_type: string;
    occurrences: number;
    first_effective_at: string | null;
    last_effective_at: string | null;
    last_log: AuditLogItem;
};

const props = defineProps<{
    tenantDetails: {
        id: number;
        name: string;
        slug: string;
    };
    subscription: {
        status: string;
        trial_ends_at: string | null;
        current_period_start_at: string | null;
        current_period_end_at: string | null;
        cancel_at_period_end: boolean;
        pending_plan: { id: number; code: string; name: string } | null;
        pending_plan_effective_at: string | null;
        last_proration_amount_cents: number;
        plan: PlanItem | null;
    };
    plans: PlanItem[];
    usage: {
        current_users: number;
        max_users: number | null;
        remaining_slots: number | null;
        is_limit_reached: boolean;
        current_customers: number;
        max_customers: number | null;
        remaining_customer_slots: number | null;
        is_customer_limit_reached: boolean;
        storage_used_bytes: number;
        storage_used_gb: number;
        storage_limit_gb: number | null;
        storage_remaining_gb: number | null;
        is_storage_limit_reached: boolean;
    };
    notifications: Array<{
        code: string;
        severity: string;
        days_left: number;
    }>;
    feature_access: {
        premium_reports: boolean;
        priority_support: boolean;
    };
    audit: {
        view: 'summary' | 'raw';
        filters: {
            audit_type: string;
            audit_actor: string;
            audit_per_page: number;
        };
        filter_options: {
            types: string[];
            actors: Array<{
                id: number;
                name: string;
            }>;
        };
        summary_logs: AuditSummaryItem[];
        raw_logs: {
            data: AuditLogItem[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    canManageBilling: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tenants', href: '/tenants' },
    { title: 'Planos e faturação', href: '/tenants/billing' },
];

function formatCurrency(cents: number): string {
    return new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(cents / 100);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-PT', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatGb(value: number | null): string {
    if (value === null) {
        return 'Ilimitado';
    }

    return `${value.toFixed(2)} GB`;
}

function normalizeUsagePercent(current: number, max: number | null): number {
    if (max === null || max <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, Math.round((current / max) * 100)));
}

function usageFillColor(percent: number, isLimitReached: boolean): string {
    if (isLimitReached || percent >= 90) {
        return '#ef4444';
    }

    if (percent >= 70) {
        return '#f59e0b';
    }

    return '#22c55e';
}

function usageChipStyle(percent: number, isLimitReached: boolean, hasLimit: boolean): CSSProperties {
    if (!hasLimit) {
        return {
            backgroundColor: '#e5e7eb',
            color: '#111827',
        };
    }

    const fill = usageFillColor(percent, isLimitReached);

    return {
        backgroundImage: `linear-gradient(90deg, ${fill} ${percent}%, #e5e7eb ${percent}%)`,
        color: '#111827',
    };
}

const usersUsagePercent = computed(() => normalizeUsagePercent(props.usage.current_users, props.usage.max_users));
const customersUsagePercent = computed(() => normalizeUsagePercent(props.usage.current_customers, props.usage.max_customers));
const storageUsagePercent = computed(() =>
    props.usage.storage_limit_gb === null
        ? 0
        : normalizeUsagePercent(props.usage.storage_used_gb, props.usage.storage_limit_gb),
);
const isCancelling = ref(false);
const isResuming = ref(false);

function statusLabel(status: string): string {
    if (status === 'trialing') return 'Trial';
    if (status === 'active') return 'Ativo';
    if (status === 'canceled') return 'Cancelado';

    return status;
}

function notificationText(code: string, daysLeft: number): string {
    if (code === 'trial_ending_soon') {
        return `O trial termina em ${daysLeft} dias.`;
    }

    if (code === 'trial_ending_very_soon') {
        return `O trial termina em ${daysLeft} dias. Considera fazer upgrade.`;
    }

    if (code === 'trial_ending_today') {
        return 'O trial termina hoje.';
    }

    if (code === 'trial_ended') {
        return 'O trial terminou.';
    }

    return 'Notificacao de trial.';
}

function changeTypeLabel(changeType: string): string {
    if (changeType === 'trial_started') return 'Trial iniciado';
    if (changeType === 'trial_ended') return 'Trial terminado';
    if (changeType === 'upgrade') return 'Upgrade imediato';
    if (changeType === 'downgrade_scheduled') return 'Downgrade agendado';
    if (changeType === 'downgrade_applied') return 'Downgrade aplicado';
    if (changeType === 'cancel_scheduled') return 'Cancelamento agendado';
    if (changeType === 'canceled') return 'Cancelado';
    if (changeType === 'resume') return 'Reativado';

    return changeType;
}

function auditQueryPatch(patch: Partial<{
    audit_view: 'summary' | 'raw';
    audit_type: string;
    audit_actor: string;
    audit_per_page: number;
    audit_page: number;
}>): void {
    const next = {
        audit_view: props.audit.view,
        audit_type: props.audit.filters.audit_type,
        audit_actor: props.audit.filters.audit_actor,
        audit_per_page: props.audit.filters.audit_per_page,
        audit_page: props.audit.raw_logs.current_page,
        ...patch,
    };

    const query: Record<string, string | number> = {
        audit_view: next.audit_view,
        audit_per_page: next.audit_per_page,
        audit_page: next.audit_page,
    };

    if (next.audit_type !== 'all') {
        query.audit_type = next.audit_type;
    }

    if (next.audit_actor !== 'all') {
        query.audit_actor = next.audit_actor;
    }

    if (next.audit_page <= 1) {
        delete query.audit_page;
    }

    router.get('/tenants/billing', query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function setAuditView(view: 'summary' | 'raw'): void {
    auditQueryPatch({
        audit_view: view,
        audit_page: 1,
    });
}

function setAuditType(value: string): void {
    auditQueryPatch({
        audit_type: value,
        audit_page: 1,
    });
}

function setAuditActor(value: string): void {
    auditQueryPatch({
        audit_actor: value,
        audit_page: 1,
    });
}

function setAuditPerPage(value: number): void {
    auditQueryPatch({
        audit_per_page: value,
        audit_page: 1,
    });
}

function onAuditTypeChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    setAuditType(target?.value ?? 'all');
}

function onAuditActorChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    setAuditActor(target?.value ?? 'all');
}

function onAuditPerPageChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    setAuditPerPage(Number(target?.value ?? 10));
}

function goToAuditPage(page: number): void {
    if (page < 1 || page > props.audit.raw_logs.last_page || page === props.audit.raw_logs.current_page) {
        return;
    }

    auditQueryPatch({
        audit_page: page,
    });
}

function isCurrentPlan(planId: number): boolean {
    return props.subscription.plan?.id === planId;
}

function changePlan(plan: PlanItem): void {
    if (isCurrentPlan(plan.id) || !props.canManageBilling) {
        return;
    }

    router.post(`/tenants/billing/plans/${plan.id}/change`, {}, {
        preserveScroll: true,
    });
}

function cancelSubscription(): void {
    if (!props.canManageBilling || isCancelling.value) {
        return;
    }

    if (!window.confirm('Pretendes cancelar no final do ciclo atual?')) {
        return;
    }

    isCancelling.value = true;

    router.post('/tenants/billing/cancel', {}, {
        preserveScroll: true,
        onFinish: () => {
            isCancelling.value = false;
        },
    });
}

function resumeSubscription(): void {
    if (!props.canManageBilling || isResuming.value) {
        return;
    }

    isResuming.value = true;

    router.post('/tenants/billing/resume', {}, {
        preserveScroll: true,
        onFinish: () => {
            isResuming.value = false;
        },
    });
}
</script>

<template>
    <Head title="Planos e faturação" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between gap-3">
                    <div>
                        <CardTitle>Planos e faturação</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Tenant ativo:
                            <span class="font-medium text-foreground">{{ tenantDetails.name }}</span>
                        </p>
                    </div>
                    <Button variant="outline" as-child>
                        <Link href="/tenants">Voltar</Link>
                    </Button>
                </CardHeader>
            </Card>

            <div
                v-if="notifications.length > 0"
                class="space-y-2"
            >
                <div
                    v-for="notification in notifications"
                    :key="`${notification.code}-${notification.days_left}`"
                    class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"
                >
                    {{ notificationText(notification.code, notification.days_left) }}
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Plano atual</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div class="flex items-center gap-2">
                            <p class="font-medium">{{ subscription.plan?.name ?? '-' }}</p>
                            <Badge variant="secondary">{{ statusLabel(subscription.status) }}</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Preco: {{ formatCurrency(subscription.plan?.price_cents ?? 0) }} / {{ subscription.plan?.billing_cycle_days ?? 30 }} dias
                        </p>
                        <p class="text-sm text-muted-foreground">Inicio do ciclo: {{ formatDate(subscription.current_period_start_at) }}</p>
                        <p class="text-sm text-muted-foreground">Fim do ciclo: {{ formatDate(subscription.current_period_end_at) }}</p>
                        <p class="text-sm text-muted-foreground">Fim do trial: {{ formatDate(subscription.trial_ends_at) }}</p>
                        <p v-if="subscription.pending_plan" class="text-sm text-muted-foreground">
                            Downgrade agendado para {{ subscription.pending_plan.name }} em {{ formatDate(subscription.pending_plan_effective_at) }}
                        </p>
                        <p v-if="subscription.last_proration_amount_cents > 0" class="text-sm text-muted-foreground">
                            Custo pro-rata do ultimo upgrade: {{ formatCurrency(subscription.last_proration_amount_cents) }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Limites e utilização</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p class="text-sm text-muted-foreground">
                            Utilizadores ativos no tenant: {{ usage.current_users }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Limite do plano: {{ usage.max_users ?? 'Ilimitado' }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Vagas restantes: {{ usage.remaining_slots ?? 'Ilimitado' }}
                        </p>
                        <span
                            class="inline-flex w-fit items-center rounded-full border border-slate-300 px-2 py-0.5 text-xs font-medium"
                            :style="usageChipStyle(usersUsagePercent, usage.is_limit_reached, usage.max_users !== null)"
                            :title="`Utilização: ${usersUsagePercent}%`"
                        >
                            {{ usage.is_limit_reached ? 'Limite atingido' : 'Dentro do limite' }}
                        </span>
                        <p class="pt-2 text-sm text-muted-foreground">
                            Clientes ativos no tenant: {{ usage.current_customers }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Limite de clientes: {{ usage.max_customers ?? 'Ilimitado' }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Vagas de clientes restantes: {{ usage.remaining_customer_slots ?? 'Ilimitado' }}
                        </p>
                        <span
                            class="inline-flex w-fit items-center rounded-full border border-slate-300 px-2 py-0.5 text-xs font-medium"
                            :style="usageChipStyle(customersUsagePercent, usage.is_customer_limit_reached, usage.max_customers !== null)"
                            :title="`Utilização: ${customersUsagePercent}%`"
                        >
                            {{ usage.is_customer_limit_reached ? 'Limite de clientes atingido' : 'Clientes dentro do limite' }}
                        </span>
                        <p class="pt-2 text-sm text-muted-foreground">
                            Armazenamento usado: {{ formatGb(usage.storage_used_gb) }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Limite de armazenamento: {{ formatGb(usage.storage_limit_gb) }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Armazenamento restante: {{ formatGb(usage.storage_remaining_gb) }}
                        </p>
                        <span
                            class="inline-flex w-fit items-center rounded-full border border-slate-300 px-2 py-0.5 text-xs font-medium"
                            :style="usageChipStyle(storageUsagePercent, usage.is_storage_limit_reached, usage.storage_limit_gb !== null)"
                            :title="`Utilização: ${storageUsagePercent}%`"
                        >
                            {{ usage.is_storage_limit_reached ? 'Limite de armazenamento atingido' : 'Armazenamento dentro do limite' }}
                        </span>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Planos disponiveis</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 md:grid-cols-2">
                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        class="rounded-md border p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium">{{ plan.name }}</p>
                            <Badge v-if="isCurrentPlan(plan.id)" variant="secondary">Plano atual</Badge>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">{{ plan.description ?? '-' }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ formatCurrency(plan.price_cents) }} / {{ plan.billing_cycle_days }} dias
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Limite de utilizadores: {{ plan.max_users ?? 'Ilimitado' }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Limite de clientes: {{ plan.max_customers ?? 'Ilimitado' }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Limite de armazenamento: {{ formatGb(plan.storage_limit_gb) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Trial: {{ plan.trial_days }} dias
                        </p>

                        <div class="mt-3">
                            <Button
                                :disabled="isCurrentPlan(plan.id) || !canManageBilling"
                                @click="changePlan(plan)"
                            >
                                {{ (subscription.plan?.price_cents ?? 0) < plan.price_cents ? 'Fazer upgrade' : 'Agendar downgrade' }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Funcionalidades premium</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex items-center justify-between rounded-md border p-3">
                        <div>
                            <p class="font-medium">Relatorios premium</p>
                            <p class="text-sm text-muted-foreground">Acesso automatico conforme o plano ativo.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge
                                :variant="feature_access.premium_reports ? 'default' : 'secondary'"
                                :class="feature_access.premium_reports ? 'border-green-200 bg-green-100 text-green-800 hover:bg-green-100' : ''"
                            >
                                {{ feature_access.premium_reports ? 'Ativo' : 'Bloqueado' }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Cancelamento</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap items-center gap-2">
                    <Button
                        variant="destructive"
                        :disabled="subscription.cancel_at_period_end || !canManageBilling || isCancelling"
                        @click="cancelSubscription"
                    >
                        Cancelar no fim do ciclo
                    </Button>
                    <Button
                        variant="outline"
                        :disabled="(!subscription.cancel_at_period_end && subscription.status !== 'canceled') || !canManageBilling || isResuming"
                        @click="resumeSubscription"
                    >
                        Reativar subscricao
                    </Button>
                    <p class="text-sm text-muted-foreground">
                        Regra de billing: sem reembolso parcial, com acesso ate ao fim do ciclo atual.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Auditoria de alteracoes de plano</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            size="sm"
                            :variant="audit.view === 'summary' ? 'default' : 'outline'"
                            @click="setAuditView('summary')"
                        >
                            Resumo
                        </Button>
                        <Button
                            size="sm"
                            :variant="audit.view === 'raw' ? 'default' : 'outline'"
                            @click="setAuditView('raw')"
                        >
                            Historico bruto
                        </Button>
                    </div>

                    <div class="grid gap-2 md:grid-cols-3">
                        <label class="text-sm">
                            Tipo
                            <select
                                class="mt-1 w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                                :value="audit.filters.audit_type"
                                @change="onAuditTypeChange"
                            >
                                <option value="all">Todos</option>
                                <option
                                    v-for="type in audit.filter_options.types"
                                    :key="type"
                                    :value="type"
                                >
                                    {{ changeTypeLabel(type) }}
                                </option>
                            </select>
                        </label>

                        <label class="text-sm">
                            Ator
                            <select
                                class="mt-1 w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                                :value="audit.filters.audit_actor"
                                @change="onAuditActorChange"
                            >
                                <option value="all">Todos</option>
                                <option
                                    v-for="actor in audit.filter_options.actors"
                                    :key="actor.id"
                                    :value="String(actor.id)"
                                >
                                    {{ actor.name }}
                                </option>
                            </select>
                        </label>

                        <label class="text-sm">
                            Itens por pagina
                            <select
                                class="mt-1 w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                                :value="audit.filters.audit_per_page"
                                @change="onAuditPerPageChange"
                            >
                                <option :value="5">5</option>
                                <option :value="10">10</option>
                                <option :value="20">20</option>
                                <option :value="50">50</option>
                            </select>
                        </label>
                    </div>

                    <div
                        v-if="audit.view === 'summary' && audit.summary_logs.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Sem registos de alteracao para os filtros aplicados.
                    </div>

                    <div
                        v-if="audit.view === 'summary'"
                        class="space-y-2"
                    >
                        <div
                            v-for="item in audit.summary_logs"
                            :key="item.key"
                            class="rounded-md border p-3"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium">{{ changeTypeLabel(item.change_type) }}</p>
                                <Badge variant="secondary">Ocorrencias: {{ item.occurrences }}</Badge>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Ultima vez: {{ formatDate(item.last_effective_at) }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Primeira vez: {{ formatDate(item.first_effective_at) }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.last_log.from_plan?.name ?? '-' }} -> {{ item.last_log.to_plan?.name ?? '-' }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Ator: {{ item.last_log.actor?.name ?? 'Sistema' }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="audit.view === 'raw' && audit.raw_logs.data.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Sem registos de alteracao para os filtros aplicados.
                    </div>

                    <div
                        v-if="audit.view === 'raw'"
                        class="space-y-2"
                    >
                        <div
                            v-for="log in audit.raw_logs.data"
                            :key="log.id"
                            class="rounded-md border p-3"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium">{{ changeTypeLabel(log.change_type) }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatDate(log.effective_at) }}</p>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ log.from_plan?.name ?? '-' }} -> {{ log.to_plan?.name ?? '-' }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Ator: {{ log.actor?.name ?? 'Sistema' }}
                            </p>
                            <p v-if="log.proration_amount_cents > 0" class="text-sm text-muted-foreground">
                                Pro-rata: {{ formatCurrency(log.proration_amount_cents) }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="audit.view === 'raw' && audit.raw_logs.last_page > 1"
                        class="flex items-center justify-between gap-2 rounded-md border p-2"
                    >
                        <p class="text-xs text-muted-foreground">
                            A mostrar {{ audit.raw_logs.from ?? 0 }}-{{ audit.raw_logs.to ?? 0 }} de {{ audit.raw_logs.total }}
                        </p>
                        <div class="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="audit.raw_logs.current_page <= 1"
                                @click="goToAuditPage(audit.raw_logs.current_page - 1)"
                            >
                                Anterior
                            </Button>
                            <p class="text-xs text-muted-foreground">
                                Pagina {{ audit.raw_logs.current_page }} / {{ audit.raw_logs.last_page }}
                            </p>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="audit.raw_logs.current_page >= audit.raw_logs.last_page"
                                @click="goToAuditPage(audit.raw_logs.current_page + 1)"
                            >
                                Seguinte
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
