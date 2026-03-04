<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { ref } from 'vue';

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
    proposal: {
        has_file: boolean;
        file_name: string | null;
        mime_type: string | null;
        size_label: string | null;
        uploaded_at: string | null;
        uploaded_by: string | null;
        download_url: string | null;
    };
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
    proposalEmailDefaults: {
        to_email: string;
        subject: string;
        body: string;
    };
    followUp: {
        active: boolean;
        next_send_at: string | null;
        last_sent_at: string | null;
        started_at: string | null;
        stop_reason: string | null;
        stop_reason_label: string | null;
        customer_replied_at: string | null;
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

const proposalForm = useForm<{
    proposal_file: File | null;
}>({
    proposal_file: null,
});

const emailProposalForm = useForm({
    to_email: props.proposalEmailDefaults.to_email,
    subject: props.proposalEmailDefaults.subject,
    body: props.proposalEmailDefaults.body,
});

const proposalInput = ref<HTMLInputElement | null>(null);
const emailConfirmation = ref<string>('');
const followUpFeedback = ref<string>('');
const followUpError = ref<string>('');

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
        proposal: 'Proposta',
        follow_up: 'Follow Up',
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

function onProposalSelected(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    proposalForm.proposal_file = input?.files?.[0] ?? null;
}

function openProposalPicker(): void {
    proposalInput.value?.click();
}

function proposalFileLabel(): string {
    return proposalForm.proposal_file?.name ?? 'Nenhum ficheiro selecionado';
}

function submitProposal(): void {
    proposalForm.post(`/deals/${props.deal.id}/proposal`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            proposalForm.reset('proposal_file');
            if (proposalInput.value !== null) {
                proposalInput.value.value = '';
            }
        },
    });
}

function sendProposalByEmail(): void {
    emailConfirmation.value = '';

    emailProposalForm.post(`/deals/${props.deal.id}/proposal/email`, {
        preserveScroll: true,
        onSuccess: () => {
            emailConfirmation.value = 'Proposta enviada por email com sucesso.';
        },
    });
}

function cancelFollowUp(): void {
    followUpFeedback.value = '';
    followUpError.value = '';

    router.post(`/deals/${props.deal.id}/follow-up/cancel`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            followUpFeedback.value = 'Follow up cancelado.';
        },
        onError: (errors) => {
            followUpError.value = (errors.follow_up as string | undefined) ?? 'Não foi possível cancelar o follow up.';
        },
    });
}

function resumeFollowUp(): void {
    followUpFeedback.value = '';
    followUpError.value = '';

    router.post(`/deals/${props.deal.id}/follow-up/resume`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            followUpFeedback.value = 'Follow up retomado.';
        },
        onError: (errors) => {
            followUpError.value = (errors.follow_up as string | undefined) ?? 'Não foi possível retomar o follow up.';
        },
    });
}

function markCustomerReplied(): void {
    followUpFeedback.value = '';
    followUpError.value = '';

    router.post(`/deals/${props.deal.id}/follow-up/customer-replied`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            followUpFeedback.value = 'Follow up parado: cliente respondeu.';
        },
        onError: () => {
            followUpError.value = 'Não foi possível registar a resposta do cliente.';
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
                    <CardTitle>Proposta</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="deal.proposal.has_file" class="rounded-md border p-3 text-sm">
                        <p><span class="font-medium">Ficheiro:</span> {{ deal.proposal.file_name || '-' }}</p>
                        <p><span class="font-medium">Tamanho:</span> {{ deal.proposal.size_label || '-' }}</p>
                        <p><span class="font-medium">Enviado em:</span> {{ deal.proposal.uploaded_at || '-' }}</p>
                        <p><span class="font-medium">Responsável:</span> {{ deal.proposal.uploaded_by || '-' }}</p>

                        <div class="mt-3">
                            <Button v-if="deal.proposal.download_url" variant="outline" as-child>
                                <a :href="deal.proposal.download_url">Descarregar proposta</a>
                            </Button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        Ainda não existe proposta carregada para este negócio.
                    </p>

                    <form class="grid gap-3 md:grid-cols-[1fr_auto]" @submit.prevent="submitProposal">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Carregar proposta</label>
                            <input
                                ref="proposalInput"
                                class="hidden"
                                type="file"
                                accept=".pdf,.doc,.docx,.odt"
                                @change="onProposalSelected"
                            />
                            <div class="flex h-10 items-center gap-2 rounded-md border px-2">
                                <Button type="button" variant="outline" @click="openProposalPicker">
                                    Escolher ficheiro
                                </Button>
                                <span class="truncate text-sm text-muted-foreground">{{ proposalFileLabel() }}</span>
                            </div>
                            <p v-if="proposalForm.errors.proposal_file" class="text-destructive text-sm">
                                {{ proposalForm.errors.proposal_file }}
                            </p>
                        </div>
                        <div class="flex items-end">
                            <Button type="submit" :disabled="proposalForm.processing || !proposalForm.proposal_file">
                                Guardar proposta
                            </Button>
                        </div>
                    </form>

                    <div class="border-t pt-4">
                        <h3 class="text-base font-semibold">Enviar proposta ao cliente</h3>
                        <p class="mt-1 text-sm text-muted-foreground">O texto de email é editável antes do envio.</p>

                        <form class="mt-3 grid gap-3" @submit.prevent="sendProposalByEmail">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Email do cliente</label>
                                <Input v-model="emailProposalForm.to_email" type="email" placeholder="cliente@empresa.pt" />
                                <p v-if="emailProposalForm.errors.to_email" class="text-destructive text-sm">{{ emailProposalForm.errors.to_email }}</p>
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Assunto</label>
                                <Input v-model="emailProposalForm.subject" type="text" />
                                <p v-if="emailProposalForm.errors.subject" class="text-destructive text-sm">{{ emailProposalForm.errors.subject }}</p>
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Mensagem</label>
                                <textarea
                                    v-model="emailProposalForm.body"
                                    class="border-input bg-background ring-offset-background min-h-28 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                />
                                <p v-if="emailProposalForm.errors.body" class="text-destructive text-sm">{{ emailProposalForm.errors.body }}</p>
                            </div>

                            <p v-if="emailProposalForm.errors.proposal_file" class="text-destructive text-sm">
                                {{ emailProposalForm.errors.proposal_file }}
                            </p>
                            <p v-if="emailProposalForm.errors.proposal_email" class="text-destructive text-sm">
                                {{ emailProposalForm.errors.proposal_email }}
                            </p>

                            <p v-if="emailConfirmation" class="text-sm text-emerald-600">
                                {{ emailConfirmation }}
                            </p>

                            <div>
                                <Button type="submit" :disabled="emailProposalForm.processing || !deal.proposal.has_file">
                                    Enviar proposta ao cliente
                                </Button>
                            </div>
                        </form>
                    </div>
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
                    <CardTitle>Follow Up automático</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p class="text-sm">
                        <span class="font-medium">Estado:</span>
                        <span v-if="followUp.active" class="text-emerald-600"> Ativo </span>
                        <span v-else class="text-muted-foreground"> Inativo </span>
                    </p>
                    <p class="text-sm">
                        <span class="font-medium">Próximo envio:</span> {{ followUp.next_send_at || '-' }}
                    </p>
                    <p class="text-sm">
                        <span class="font-medium">Último envio:</span> {{ followUp.last_sent_at || '-' }}
                    </p>
                    <p v-if="followUp.stop_reason_label" class="text-sm text-muted-foreground">
                        <span class="font-medium">Paragem:</span> {{ followUp.stop_reason_label }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        O envio automático ocorre de 2 em 2 dias com rotação de templates, apenas em horário de trabalho.
                    </p>

                    <p v-if="followUpFeedback" class="text-sm text-emerald-600">{{ followUpFeedback }}</p>
                    <p v-if="followUpError" class="text-destructive text-sm">{{ followUpError }}</p>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="followUp.active"
                            variant="outline"
                            type="button"
                            @click="cancelFollowUp"
                        >
                            Cancelar follow up
                        </Button>
                        <Button
                            v-if="followUp.active"
                            type="button"
                            @click="markCustomerReplied"
                        >
                            Cliente respondeu
                        </Button>
                        <Button
                            v-if="!followUp.active && deal.stage === 'follow_up'"
                            type="button"
                            @click="resumeFollowUp"
                        >
                            Retomar follow up
                        </Button>
                    </div>
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
