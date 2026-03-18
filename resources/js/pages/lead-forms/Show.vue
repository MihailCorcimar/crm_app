<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type EnabledField = {
    key: string;
    label: string;
    type: string;
    required: boolean;
};

type ConversionSettings = {
    create_deal: boolean;
    entity_name_field_key: string | null;
    deal_title_field_key: string | null;
    deal_title_template: string;
    deal_value_field_key: string | null;
    deal_stage: string;
    deal_owner_id: number | null;
    deal_probability: number;
};

type SubmissionRow = {
    id: number;
    status: 'new' | 'converted' | 'ignored';
    contact: {
        id: number;
        name: string;
        email: string | null;
    } | null;
    entity: {
        id: number;
        name: string;
    } | null;
    deal: {
        id: number;
        title: string;
        stage: string;
    } | null;
    source_type: string;
    source_url: string | null;
    source_origin: string | null;
    ip_address: string | null;
    submitted_at: string | null;
    converted_at: string | null;
    converted_by: string | null;
    ignored_at: string | null;
    ignored_by: string | null;
    payload: Record<string, unknown>;
};

type SubmissionPaginator = {
    data: SubmissionRow[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

const props = defineProps<{
    leadForm: {
        id: number;
        name: string;
        slug: string;
        status: 'active' | 'inactive';
        requires_captcha: boolean;
        confirmation_message: string;
        enabled_fields: EnabledField[];
        conversion_settings: ConversionSettings;
        public_url: string;
        embed_iframe_code: string;
        embed_script_code: string;
    };
    submissions: SubmissionPaginator;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formularios publicos', href: '/lead-forms' },
    { title: props.leadForm.name, href: `/lead-forms/${props.leadForm.id}` },
];

type CopyKind = 'iframe' | 'script';
type CopyState = 'idle' | 'copied' | 'error';

const copyStatus = ref<Record<CopyKind, CopyState>>({
    iframe: 'idle',
    script: 'idle',
});

const runningSubmissionId = ref<number | null>(null);

const hasSubmissions = computed(() => props.submissions.data.length > 0);

function fallbackCopy(text: string): boolean {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();

    const copied = document.execCommand('copy');
    document.body.removeChild(textarea);

    return copied;
}

function setCopyStatus(kind: CopyKind, state: CopyState): void {
    copyStatus.value[kind] = state;
    window.setTimeout(() => {
        copyStatus.value[kind] = 'idle';
    }, 1600);
}

async function copySnippet(kind: CopyKind): Promise<void> {
    const text = kind === 'iframe' ? props.leadForm.embed_iframe_code : props.leadForm.embed_script_code;

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            setCopyStatus(kind, 'copied');

            return;
        }
    } catch {
        // Fallback para contexto HTTP.
    }

    const copied = fallbackCopy(text);
    setCopyStatus(kind, copied ? 'copied' : 'error');
}

function statusLabel(status: SubmissionRow['status']): string {
    if (status === 'converted') return 'Convertida';
    if (status === 'ignored') return 'Ignorada';

    return 'Nova';
}

function statusClass(status: SubmissionRow['status']): string {
    if (status === 'converted') return 'bg-emerald-100 text-emerald-800';
    if (status === 'ignored') return 'bg-zinc-200 text-zinc-700';

    return 'bg-amber-100 text-amber-800';
}

function toDisplayValue(value: unknown): string {
    if (value === null || value === undefined) return '';
    if (typeof value === 'boolean') return value ? 'Sim' : 'Nao';
    if (Array.isArray(value)) return value.map((item) => String(item)).join(', ');

    return String(value);
}

function prettyPayload(payload: Record<string, unknown>): string {
    return Object.entries(payload)
        .map(([key, value]) => `${key}: ${toDisplayValue(value)}`)
        .filter((row) => row.trim() !== '' && !row.endsWith(': '))
        .join(' | ');
}

function convertSubmission(submission: SubmissionRow): void {
    if (submission.status === 'converted') {
        return;
    }

    if (!window.confirm('Converter esta submissao agora?')) {
        return;
    }

    runningSubmissionId.value = submission.id;
    router.post(
        `/lead-forms/${props.leadForm.id}/submissions/${submission.id}/convert`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                runningSubmissionId.value = null;
            },
        },
    );
}

function ignoreSubmission(submission: SubmissionRow): void {
    if (submission.status === 'ignored') {
        return;
    }

    if (!window.confirm('Marcar esta submissao como ignorada?')) {
        return;
    }

    runningSubmissionId.value = submission.id;
    router.patch(
        `/lead-forms/${props.leadForm.id}/submissions/${submission.id}/ignore`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                runningSubmissionId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="`Formulario - ${leadForm.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ leadForm.name }}</CardTitle>
                    <div class="flex gap-2">
                        <Button as-child variant="outline">
                            <Link :href="`/lead-forms/${leadForm.id}/edit`">Editar</Link>
                        </Button>
                        <Button as-child variant="outline">
                            <a :href="leadForm.public_url" target="_blank" rel="noopener noreferrer">Abrir publico</a>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-md border p-3">
                            <div class="text-sm text-muted-foreground">Estado</div>
                            <div class="font-medium">{{ leadForm.status === 'active' ? 'Ativo' : 'Inativo' }}</div>
                        </div>
                        <div class="rounded-md border p-3">
                            <div class="text-sm text-muted-foreground">Captcha obrigatorio</div>
                            <div class="font-medium">{{ leadForm.requires_captcha ? 'Sim' : 'Nao' }}</div>
                        </div>
                    </div>

                    <div class="rounded-md border p-3">
                        <p class="text-sm font-medium">Campos ativos</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="field in leadForm.enabled_fields"
                                :key="field.key"
                                class="inline-flex rounded-full bg-zinc-200 px-2 py-1 text-xs"
                            >
                                {{ field.label }}{{ field.required ? ' *' : '' }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-md border p-3">
                        <p class="text-sm font-medium">Mensagem de confirmacao</p>
                        <p class="mt-1 text-sm text-muted-foreground">{{ leadForm.confirmation_message }}</p>
                    </div>

                    <div class="rounded-md border p-3">
                        <p class="text-sm font-medium">Conversao configurada</p>
                        <div class="mt-2 grid gap-2 text-sm text-muted-foreground md:grid-cols-2">
                            <div>Criar negocio: {{ leadForm.conversion_settings.create_deal ? 'Sim' : 'Nao' }}</div>
                            <div>Etapa inicial: {{ leadForm.conversion_settings.deal_stage }}</div>
                            <div>Probabilidade inicial: {{ leadForm.conversion_settings.deal_probability }}%</div>
                            <div>Template titulo: {{ leadForm.conversion_settings.deal_title_template }}</div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Codigo de incorporacao</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium">iframe</p>
                            <Button
                                size="sm"
                                :variant="copyStatus.iframe === 'copied' ? 'default' : 'outline'"
                                :class="copyStatus.iframe === 'error' ? 'border-red-500 text-red-600' : ''"
                                @click="copySnippet('iframe')"
                            >
                                {{
                                    copyStatus.iframe === 'copied'
                                        ? 'Copiado'
                                        : copyStatus.iframe === 'error'
                                            ? 'Falhou'
                                            : 'Copiar'
                                }}
                            </Button>
                        </div>
                        <p
                            v-if="copyStatus.iframe !== 'idle'"
                            class="text-xs"
                            :class="copyStatus.iframe === 'copied' ? 'text-emerald-600' : 'text-red-600'"
                        >
                            {{ copyStatus.iframe === 'copied' ? 'Codigo copiado para a area de transferencia.' : 'Nao foi possivel copiar automaticamente.' }}
                        </p>
                        <textarea
                            readonly
                            rows="3"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 font-mono text-xs"
                            :value="leadForm.embed_iframe_code"
                        />
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium">script</p>
                            <Button
                                size="sm"
                                :variant="copyStatus.script === 'copied' ? 'default' : 'outline'"
                                :class="copyStatus.script === 'error' ? 'border-red-500 text-red-600' : ''"
                                @click="copySnippet('script')"
                            >
                                {{
                                    copyStatus.script === 'copied'
                                        ? 'Copiado'
                                        : copyStatus.script === 'error'
                                            ? 'Falhou'
                                            : 'Copiar'
                                }}
                            </Button>
                        </div>
                        <p
                            v-if="copyStatus.script !== 'idle'"
                            class="text-xs"
                            :class="copyStatus.script === 'copied' ? 'text-emerald-600' : 'text-red-600'"
                        >
                            {{ copyStatus.script === 'copied' ? 'Codigo copiado para a area de transferencia.' : 'Nao foi possivel copiar automaticamente.' }}
                        </p>
                        <textarea
                            readonly
                            rows="4"
                            class="border-input bg-background w-full rounded-md border px-3 py-2 font-mono text-xs"
                            :value="leadForm.embed_script_code"
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Submissoes</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="!hasSubmissions" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Sem submissoes ainda.
                    </div>

                    <template v-else>
                        <div class="overflow-x-auto rounded-md border">
                            <table class="min-w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Data</th>
                                        <th class="px-3 py-2 text-left font-medium">Estado</th>
                                        <th class="px-3 py-2 text-left font-medium">Registos</th>
                                        <th class="px-3 py-2 text-left font-medium">Origem</th>
                                        <th class="px-3 py-2 text-left font-medium">Dados</th>
                                        <th class="px-3 py-2 text-right font-medium">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="submission in submissions.data" :key="submission.id" class="border-t align-top">
                                        <td class="px-3 py-2">{{ submission.submitted_at || '-' }}</td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(submission.status)">
                                                {{ statusLabel(submission.status) }}
                                            </span>
                                            <div v-if="submission.converted_at" class="mt-1 text-xs text-muted-foreground">
                                                Convertida em {{ submission.converted_at }} por {{ submission.converted_by || '-' }}
                                            </div>
                                            <div v-if="submission.ignored_at" class="mt-1 text-xs text-muted-foreground">
                                                Ignorada em {{ submission.ignored_at }} por {{ submission.ignored_by || '-' }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div v-if="submission.contact">
                                                <span class="text-xs text-muted-foreground">Pessoa</span>
                                                <div>
                                                    <Link :href="`/people/${submission.contact.id}`" class="font-medium text-blue-700 underline">
                                                        {{ submission.contact.name }}
                                                    </Link>
                                                </div>
                                            </div>
                                            <div v-if="submission.entity" class="mt-1">
                                                <span class="text-xs text-muted-foreground">Entidade</span>
                                                <div>
                                                    <Link :href="`/entities/${submission.entity.id}`" class="font-medium text-blue-700 underline">
                                                        {{ submission.entity.name }}
                                                    </Link>
                                                </div>
                                            </div>
                                            <div v-if="submission.deal" class="mt-1">
                                                <span class="text-xs text-muted-foreground">Negocio</span>
                                                <div>
                                                    <Link :href="`/deals/${submission.deal.id}`" class="font-medium text-blue-700 underline">
                                                        {{ submission.deal.title }}
                                                    </Link>
                                                </div>
                                            </div>
                                            <span v-if="!submission.contact && !submission.entity && !submission.deal">-</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div>{{ submission.source_type }}</div>
                                            <div v-if="submission.source_origin" class="text-xs text-muted-foreground">{{ submission.source_origin }}</div>
                                            <a
                                                v-if="submission.source_url"
                                                :href="submission.source_url"
                                                class="text-xs text-blue-700 underline"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                link
                                            </a>
                                            <div class="mt-1 text-xs text-muted-foreground">IP: {{ submission.ip_address || '-' }}</div>
                                        </td>
                                        <td class="max-w-sm px-3 py-2 text-xs text-muted-foreground">
                                            {{ prettyPayload(submission.payload) || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <div class="flex justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    :disabled="runningSubmissionId === submission.id || submission.status === 'converted'"
                                                    @click="convertSubmission(submission)"
                                                >
                                                    Converter
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    :disabled="runningSubmissionId === submission.id || submission.status === 'ignored'"
                                                    @click="ignoreSubmission(submission)"
                                                >
                                                    Ignorar
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                v-for="link in submissions.links"
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
        </div>
    </AppLayout>
</template>
