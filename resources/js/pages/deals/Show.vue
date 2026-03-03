<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type DealShowPayload = {
    id: number;
    title: string;
    entity: string | null;
    stage: string;
    value: number;
    probability: number;
    expected_close_date: string | null;
    owner: string | null;
    created_at: string | null;
    updated_at: string | null;
};

type QuickActivityType = {
    value: 'call' | 'task' | 'meeting' | 'note';
    label: string;
};

type OwnerOption = {
    id: number;
    name: string;
};

type TimelineItem = {
    key: string;
    entry_type: string;
    activity_type: string | null;
    title: string;
    details: string;
    owner: string | null;
    occurred_at: string;
};

const props = defineProps<{
    deal: DealShowPayload;
    timeline: TimelineItem[];
    quickActivityTypes: QuickActivityType[];
    quickActivityDefaults: {
        activity_type: 'call' | 'task' | 'meeting' | 'note';
        activity_at: string;
        owner_id: number | null;
    };
    owners: OwnerOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Negócios', href: '/deals' },
    { title: props.deal.title, href: `/deals/${props.deal.id}` },
];

const quickForm = useForm({
    activity_type: props.quickActivityDefaults.activity_type,
    activity_at: props.quickActivityDefaults.activity_at,
    owner_id: props.quickActivityDefaults.owner_id ?? '',
    title: '',
    description: '',
});

function stageLabel(stage: string): string {
    const map: Record<string, string> = {
        lead: 'Lead',
        proposal: 'Proposta',
        negotiation: 'Negociação',
        follow_up: 'Follow Up',
        won: 'Ganho',
        lost: 'Perdido',
    };

    return map[stage] ?? stage;
}

function activityLabel(type: string | null): string {
    const map: Record<string, string> = {
        call: 'Chamada',
        task: 'Tarefa',
        meeting: 'Reunião',
        note: 'Nota',
    };

    if (type === null) {
        return '-';
    }

    return map[type] ?? type;
}

function destroyDeal(): void {
    if (!window.confirm('Tens a certeza que queres eliminar este negócio?')) {
        return;
    }

    router.delete(`/deals/${props.deal.id}`);
}

function submitQuickActivity(): void {
    quickForm.post(`/deals/${props.deal.id}/quick-activity`, {
        preserveScroll: true,
        onSuccess: () => {
            quickForm.reset('title', 'description');
        },
    });
}
</script>

<template>
    <Head :title="deal.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ deal.title }}</CardTitle>
                    <div class="flex gap-2">
                        <Button variant="outline" as-child>
                            <Link href="/deals">Voltar</Link>
                        </Button>
                        <Button variant="outline" as-child>
                            <Link :href="`/deals/${deal.id}/edit`">Editar</Link>
                        </Button>
                        <Button variant="destructive" @click="destroyDeal">
                            Eliminar
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-4 md:grid-cols-2">
                        <div><dt class="text-sm text-muted-foreground">Título</dt><dd>{{ deal.title }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Entidade</dt><dd>{{ deal.entity || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Etapa</dt><dd>{{ stageLabel(deal.stage) }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Valor</dt><dd>{{ deal.value.toFixed(2) }} EUR</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Probabilidade</dt><dd>{{ deal.probability }}%</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Fecho previsto</dt><dd>{{ deal.expected_close_date || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Responsável</dt><dd>{{ deal.owner || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Criado em</dt><dd>{{ deal.created_at || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Atualizado em</dt><dd>{{ deal.updated_at || '-' }}</dd></div>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Criação rápida de atividade</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitQuickActivity">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Tipo</label>
                            <select
                                v-model="quickForm.activity_type"
                                class="border-input bg-background ring-offset-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option v-for="type in quickActivityTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                            <p v-if="quickForm.errors.activity_type" class="text-destructive text-sm">{{ quickForm.errors.activity_type }}</p>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Data e hora</label>
                            <Input v-model="quickForm.activity_at" type="datetime-local" />
                            <p v-if="quickForm.errors.activity_at" class="text-destructive text-sm">{{ quickForm.errors.activity_at }}</p>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Responsável</label>
                            <select
                                v-model="quickForm.owner_id"
                                class="border-input bg-background ring-offset-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option :value="''">Selecionar responsável</option>
                                <option v-for="owner in owners" :key="owner.id" :value="owner.id">
                                    {{ owner.name }}
                                </option>
                            </select>
                            <p v-if="quickForm.errors.owner_id" class="text-destructive text-sm">{{ quickForm.errors.owner_id }}</p>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Título (opcional)</label>
                            <Input v-model="quickForm.title" />
                            <p v-if="quickForm.errors.title" class="text-destructive text-sm">{{ quickForm.errors.title }}</p>
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <label class="text-sm font-medium">Descrição (opcional)</label>
                            <textarea
                                v-model="quickForm.description"
                                class="border-input bg-background ring-offset-background min-h-24 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            />
                            <p v-if="quickForm.errors.description" class="text-destructive text-sm">{{ quickForm.errors.description }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <Button type="submit" :disabled="quickForm.processing">Registar atividade</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Cronologia</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul v-if="timeline.length > 0" class="space-y-3">
                        <li
                            v-for="item in timeline"
                            :key="item.key"
                            class="rounded-md border p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium">{{ item.title }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        Tipo: {{ item.entry_type }}<span v-if="item.activity_type"> | {{ activityLabel(item.activity_type) }}</span>
                                    </p>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ item.occurred_at }}</p>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">{{ item.details }}</p>
                            <p v-if="item.owner" class="mt-1 text-xs text-muted-foreground">Responsável: {{ item.owner }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">Sem registos na cronologia.</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
