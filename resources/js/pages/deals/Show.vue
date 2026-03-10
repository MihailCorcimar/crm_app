<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { CalendarDays, History, Mail, NotebookTabs } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, type Component, watch } from 'vue';

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

type ProductOption = {
    id: number;
    name: string;
    reference: string | null;
    code: string | null;
    default_price: number;
};

type DealProductLine = {
    id: number;
    item_id: number;
    item_name: string;
    item_reference: string | null;
    quantity: number;
    unit_price: number;
    total_value: number;
};

type TimelineItem = {
    key: string;
    entry_type: string;
    activity_type: string | null;
    title: string;
    details: string;
    owner: string | null;
    occurred_at: string;
    email: {
        to_email: string | null;
        from_email: string | null;
        subject: string | null;
        body: string | null;
        attachment_name: string | null;
        email_type: string | null;
    } | null;
    metadata: Array<{
        label: string;
        value: string;
    }>;
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
    productOptions: ProductOption[];
    dealProducts: DealProductLine[];
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

const productForm = useForm({
    item_id: '' as number | '',
    quantity: '1',
    unit_price: '',
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
const quickActivityFeedback = ref<string>('');
const quickActivityError = ref<string>('');
const timelineItems = ref<TimelineItem[]>([...props.timeline]);
const timelineFilter = ref<'all' | 'email' | 'activity' | 'note' | 'change'>('all');
const selectedTimelineItem = ref<TimelineItem | null>(null);
const timelineDialogOpen = ref<boolean>(false);
const timelineRefreshing = ref<boolean>(false);
const timelineRefreshError = ref<string>('');
const timelineLastSyncAt = ref<string | null>(null);
let timelinePolling: ReturnType<typeof setInterval> | null = null;

const totalProductsValue = computed(() =>
    props.dealProducts.reduce((sum, line) => sum + line.total_value, 0),
);

watch(
    () => productForm.item_id,
    (value) => {
        if (value === '') {
            productForm.unit_price = '';

            return;
        }

        const selected = props.productOptions.find((item) => item.id === Number(value));
        if (selected !== undefined) {
            productForm.unit_price = selected.default_price.toFixed(2);
        }
    },
);

watch(
    () => props.timeline,
    (incoming) => {
        timelineItems.value = [...incoming];
    },
);

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

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);
}

function formatQuantity(value: number): string {
    return new Intl.NumberFormat('pt-PT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
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

function entryTypeLabel(entryType: string): string {
    const map: Record<string, string> = {
        negocio: 'Negócio',
        atividade: 'Atividade',
        email: 'Email',
        alteracao: 'Alteracao',
    };

    return map[entryType] ?? entryType;
}

function entryTypeClass(entryType: string): string {
    const map: Record<string, string> = {
        negocio: 'bg-zinc-100 text-zinc-700',
        atividade: 'bg-blue-100 text-blue-700',
        email: 'bg-emerald-100 text-emerald-700',
        alteracao: 'bg-amber-100 text-amber-700',
    };

    return map[entryType] ?? 'bg-zinc-100 text-zinc-700';
}

function timelineItemIcon(item: TimelineItem): Component {
    if (item.entry_type === 'email') {
        return Mail;
    }

    if (item.entry_type === 'alteracao') {
        return History;
    }

    if (item.entry_type === 'atividade' && item.activity_type === 'note') {
        return NotebookTabs;
    }

    return CalendarDays;
}

function timelineMatchesFilter(item: TimelineItem): boolean {
    if (timelineFilter.value === 'all') {
        return true;
    }

    if (timelineFilter.value === 'email') {
        return item.entry_type === 'email';
    }

    if (timelineFilter.value === 'activity') {
        return item.entry_type === 'atividade';
    }

    if (timelineFilter.value === 'note') {
        return item.entry_type === 'atividade' && item.activity_type === 'note';
    }

    if (timelineFilter.value === 'change') {
        return item.entry_type === 'alteracao' || item.entry_type === 'negocio';
    }

    return true;
}

const filteredTimeline = computed(() =>
    timelineItems.value.filter((item) => timelineMatchesFilter(item)),
);

function openTimelineDetails(item: TimelineItem): void {
    selectedTimelineItem.value = item;
    timelineDialogOpen.value = true;
}

async function refreshTimeline(): Promise<void> {
    if (timelineRefreshing.value) {
        return;
    }

    timelineRefreshing.value = true;
    timelineRefreshError.value = '';

    try {
        const response = await fetch(`/deals/${props.deal.id}/timeline`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json() as { timeline?: TimelineItem[] };
        timelineItems.value = Array.isArray(payload.timeline) ? payload.timeline : [];
        timelineLastSyncAt.value = new Date().toLocaleTimeString('pt-PT');
    } catch (error) {
        console.error(error);
        timelineRefreshError.value = 'Não foi possível atualizar a cronologia em tempo real.';
    } finally {
        timelineRefreshing.value = false;
    }
}

function destroyDeal(): void {
    if (!window.confirm('Tens a certeza que queres eliminar este negócio?')) {
        return;
    }

    router.delete(`/deals/${props.deal.id}`);
}

function submitQuickActivity(): void {
    quickActivityFeedback.value = '';
    quickActivityError.value = '';

    quickForm.post(`/deals/${props.deal.id}/quick-activity`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            quickForm.reset('title', 'description');
            quickActivityFeedback.value = 'Atividade registada com sucesso.';
            void refreshTimeline();
        },
        onError: () => {
            quickActivityError.value = 'Não foi possível registar a atividade.';
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
            void refreshTimeline();
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

function addProductToDeal(): void {
    productForm.post(`/deals/${props.deal.id}/products`, {
        preserveScroll: true,
        onSuccess: () => {
            productForm.reset();
            productForm.quantity = '1';
            productForm.item_id = '';
            void refreshTimeline();
        },
    });
}

function removeProductFromDeal(lineId: number): void {
    if (!window.confirm('Remover este produto do negócio?')) {
        return;
    }

    router.delete(`/deals/${props.deal.id}/products/${lineId}`, {
        preserveScroll: true,
    });
}

onMounted(() => {
    timelinePolling = setInterval(() => {
        void refreshTimeline();
    }, 15000);
});

onBeforeUnmount(() => {
    if (timelinePolling !== null) {
        clearInterval(timelinePolling);
    }
});
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
                    <CardTitle>Produtos do negócio</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <form class="grid gap-3 md:grid-cols-[1fr_140px_140px_auto]" @submit.prevent="addProductToDeal">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Produto</label>
                            <select
                                v-model="productForm.item_id"
                                class="border-input bg-background ring-offset-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option :value="''">Selecionar produto</option>
                                <option v-for="item in productOptions" :key="item.id" :value="item.id">
                                    {{ item.name }}<span v-if="item.reference"> ({{ item.reference }})</span>
                                </option>
                            </select>
                            <p v-if="productForm.errors.item_id" class="text-destructive text-sm">{{ productForm.errors.item_id }}</p>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Quantidade</label>
                            <Input v-model="productForm.quantity" type="number" min="0.01" step="0.01" />
                            <p v-if="productForm.errors.quantity" class="text-destructive text-sm">{{ productForm.errors.quantity }}</p>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Preço unitário (EUR)</label>
                            <Input v-model="productForm.unit_price" type="number" min="0" step="0.01" />
                            <p v-if="productForm.errors.unit_price" class="text-destructive text-sm">{{ productForm.errors.unit_price }}</p>
                        </div>

                        <div class="flex items-end">
                            <Button
                                type="submit"
                                :disabled="productForm.processing || productForm.item_id === '' || productForm.unit_price === ''"
                            >
                                Adicionar
                            </Button>
                        </div>
                    </form>

                    <div v-if="dealProducts.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Ainda não existem produtos associados a este negócio.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="overflow-x-auto rounded-md border">
                            <table class="min-w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Produto</th>
                                        <th class="px-3 py-2 text-right font-medium">Quantidade</th>
                                        <th class="px-3 py-2 text-right font-medium">Preço unitário</th>
                                        <th class="px-3 py-2 text-right font-medium">Total</th>
                                        <th class="px-3 py-2 text-right font-medium">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in dealProducts" :key="line.id" class="border-t">
                                        <td class="px-3 py-2">
                                            <div class="font-medium">{{ line.item_name }}</div>
                                            <div v-if="line.item_reference" class="text-xs text-muted-foreground">{{ line.item_reference }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ formatQuantity(line.quantity) }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatCurrency(line.unit_price) }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatCurrency(line.total_value) }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <Button type="button" variant="outline" size="sm" @click="removeProductFromDeal(line.id)">
                                                Remover
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            Total de produtos no negócio: {{ formatCurrency(totalProductsValue) }}
                        </p>
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

                        <p v-if="quickActivityFeedback" class="md:col-span-2 text-sm text-emerald-600">
                            {{ quickActivityFeedback }}
                        </p>
                        <p v-if="quickActivityError" class="md:col-span-2 text-destructive text-sm">
                            {{ quickActivityError }}
                        </p>
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
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <Button size="sm" :variant="timelineFilter === 'all' ? 'default' : 'outline'" @click="timelineFilter = 'all'">
                            Todos
                        </Button>
                        <Button size="sm" :variant="timelineFilter === 'email' ? 'default' : 'outline'" @click="timelineFilter = 'email'">
                            Emails
                        </Button>
                        <Button size="sm" :variant="timelineFilter === 'activity' ? 'default' : 'outline'" @click="timelineFilter = 'activity'">
                            Atividades
                        </Button>
                        <Button size="sm" :variant="timelineFilter === 'note' ? 'default' : 'outline'" @click="timelineFilter = 'note'">
                            Notas
                        </Button>
                        <Button size="sm" :variant="timelineFilter === 'change' ? 'default' : 'outline'" @click="timelineFilter = 'change'">
                            Alterações
                        </Button>

                        <div class="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                            <span v-if="timelineLastSyncAt">Última atualização: {{ timelineLastSyncAt }}</span>
                            <Button size="sm" variant="outline" :disabled="timelineRefreshing" @click="refreshTimeline">
                                Atualizar
                            </Button>
                        </div>
                    </div>

                    <p v-if="timelineRefreshError" class="text-destructive text-sm">
                        {{ timelineRefreshError }}
                    </p>

                    <ul v-if="filteredTimeline.length > 0" class="space-y-3">
                        <li
                            v-for="item in filteredTimeline"
                            :key="item.key"
                            class="rounded-md border p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-2">
                                    <component :is="timelineItemIcon(item)" class="mt-0.5 h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p class="text-sm font-medium">{{ item.title }}</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-1">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="entryTypeClass(item.entry_type)">
                                                {{ entryTypeLabel(item.entry_type) }}
                                            </span>
                                            <span
                                                v-if="item.activity_type"
                                                class="inline-flex rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700"
                                            >
                                                {{ activityLabel(item.activity_type) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ item.occurred_at }}</p>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">{{ item.details }}</p>
                            <p v-if="item.owner" class="mt-1 text-xs text-muted-foreground">Responsável: {{ item.owner }}</p>
                            <div class="mt-2">
                                <Button size="sm" variant="outline" @click="openTimelineDetails(item)">
                                    Ver detalhe
                                </Button>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">Sem registos na cronologia para este filtro.</p>
                </CardContent>
            </Card>

            <Dialog v-model:open="timelineDialogOpen">
                <DialogContent class="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{ selectedTimelineItem?.title || 'Detalhe da cronologia' }}</DialogTitle>
                        <DialogDescription>{{ selectedTimelineItem?.occurred_at || '-' }}</DialogDescription>
                    </DialogHeader>

                    <div v-if="selectedTimelineItem" class="space-y-3 text-sm">
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="entryTypeClass(selectedTimelineItem.entry_type)">
                                {{ entryTypeLabel(selectedTimelineItem.entry_type) }}
                            </span>
                            <span
                                v-if="selectedTimelineItem.activity_type"
                                class="inline-flex rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700"
                            >
                                {{ activityLabel(selectedTimelineItem.activity_type) }}
                            </span>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-muted-foreground">Resumo</p>
                            <p>{{ selectedTimelineItem.details }}</p>
                        </div>

                        <div v-if="selectedTimelineItem.email" class="space-y-2 rounded-md border p-3">
                            <p class="text-xs font-medium text-muted-foreground">Email</p>
                            <p><span class="font-medium">De:</span> {{ selectedTimelineItem.email.from_email || '-' }}</p>
                            <p><span class="font-medium">Para:</span> {{ selectedTimelineItem.email.to_email || '-' }}</p>
                            <p><span class="font-medium">Assunto:</span> {{ selectedTimelineItem.email.subject || '-' }}</p>
                            <p><span class="font-medium">Anexo:</span> {{ selectedTimelineItem.email.attachment_name || '-' }}</p>
                            <div>
                                <p class="font-medium">Corpo</p>
                                <p class="whitespace-pre-wrap rounded-md bg-zinc-50 p-2">{{ selectedTimelineItem.email.body || '-' }}</p>
                            </div>
                        </div>

                        <div v-if="selectedTimelineItem.metadata.length > 0" class="space-y-2 rounded-md border p-3">
                            <p class="text-xs font-medium text-muted-foreground">Metadados</p>
                            <ul class="space-y-1">
                                <li v-for="meta in selectedTimelineItem.metadata" :key="`${meta.label}-${meta.value}`">
                                    <span class="font-medium">{{ meta.label }}:</span> {{ meta.value }}
                                </li>
                            </ul>
                        </div>

                        <p v-if="selectedTimelineItem.owner" class="text-xs text-muted-foreground">Responsável: {{ selectedTimelineItem.owner }}</p>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>





