<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type ProposalLine = {
    id: number;
    item: string;
    supplier: string | null;
    quantity: number;
    sale_price: number;
    cost_price: number;
    line_total: number;
};

type ProposalPayload = {
    id: number;
    number: number;
    proposal_date: string | null;
    valid_until: string;
    customer: string | null;
    status: string;
    total: number;
    lines: ProposalLine[];
};

const props = defineProps<{ proposal: ProposalPayload }>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Propostas', href: '/proposals' },
    { title: `Proposta #${props.proposal.number}`, href: `/proposals/${props.proposal.id}` },
];

function destroyProposal(): void {
    if (!window.confirm('Eliminar esta proposta?')) {
        return;
    }
    router.delete(`/proposals/${props.proposal.id}`);
}

function convertToOrder(): void {
    router.post(`/proposals/${props.proposal.id}/convert-to-order`);
}
</script>

<template>
    <Head :title="`Proposta #${proposal.number}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Proposta #{{ proposal.number }}</CardTitle>
                    <div class="flex gap-2">
                        <Button variant="outline" as-child><Link href="/proposals">Voltar</Link></Button>
                        <Button variant="outline" as-child><Link :href="`/proposals/${proposal.id}/edit`">Editar</Link></Button>
                        <Button variant="outline" as-child><a :href="`/proposals/${proposal.id}/pdf`">PDF</a></Button>
                        <Button variant="secondary" @click="convertToOrder">Converter em Encomenda</Button>
                        <Button variant="destructive" @click="destroyProposal">Eliminar</Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div><dt class="text-sm text-muted-foreground">Data</dt><dd>{{ proposal.proposal_date || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Validade</dt><dd>{{ proposal.valid_until }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Cliente</dt><dd>{{ proposal.customer || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Estado</dt><dd>{{ proposal.status === 'closed' ? 'Fechado' : 'Rascunho' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Total</dt><dd>{{ proposal.total.toFixed(2) }} €</dd></div>
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
                                <tr v-for="line in proposal.lines" :key="line.id" class="border-t">
                                    <td class="px-3 py-2">{{ line.item }}</td>
                                    <td class="px-3 py-2">{{ line.supplier || '-' }}</td>
                                    <td class="px-3 py-2">{{ line.quantity }}</td>
                                    <td class="px-3 py-2">{{ line.sale_price.toFixed(2) }} €</td>
                                    <td class="px-3 py-2">{{ line.cost_price.toFixed(2) }} €</td>
                                    <td class="px-3 py-2">{{ line.line_total.toFixed(2) }} €</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
