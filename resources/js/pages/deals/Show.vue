<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

const props = defineProps<{
    deal: DealShowPayload;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Negócios', href: '/deals' },
    { title: props.deal.title, href: `/deals/${props.deal.id}` },
];

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

function destroyDeal(): void {
    if (!window.confirm('Tens a certeza que queres eliminar este negócio?')) {
        return;
    }

    router.delete(`/deals/${props.deal.id}`);
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
        </div>
    </AppLayout>
</template>
