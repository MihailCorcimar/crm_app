<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

type EnabledField = {
    key: string;
    label: string;
    type: string;
    required: boolean;
};

type SubmissionRow = {
    id: number;
    contact: {
        id: number;
        name: string;
        email: string | null;
    } | null;
    source_type: string;
    source_url: string | null;
    source_origin: string | null;
    ip_address: string | null;
    submitted_at: string | null;
    payload: Record<string, string | null>;
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
        public_url: string;
        embed_iframe_code: string;
        embed_script_code: string;
    };
    submissions: SubmissionPaginator;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formulários públicos', href: '/lead-forms' },
    { title: props.leadForm.name, href: `/lead-forms/${props.leadForm.id}` },
];

type CopyKind = 'iframe' | 'script';
type CopyState = 'idle' | 'copied' | 'error';

const copyStatus = ref<Record<CopyKind, CopyState>>({
    iframe: 'idle',
    script: 'idle',
});

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
        // Fallback para contexto não seguro (HTTP).
    }

    const copied = fallbackCopy(text);
    setCopyStatus(kind, copied ? 'copied' : 'error');
}

function prettyPayload(payload: Record<string, string | null>): string {
    return Object.entries(payload)
        .filter(([, value]) => value !== null && value !== '')
        .map(([key, value]) => `${key}: ${value}`)
        .join(' | ');
}
</script>

<template>
    <Head :title="`Formulário - ${leadForm.name}`" />

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
                            <a :href="leadForm.public_url" target="_blank" rel="noopener noreferrer">Abrir público</a>
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
                            <div class="text-sm text-muted-foreground">Captcha obrigatório</div>
                            <div class="font-medium">{{ leadForm.requires_captcha ? 'Sim' : 'Não' }}</div>
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
                        <p class="text-sm font-medium">Mensagem de confirmação</p>
                        <p class="mt-1 text-sm text-muted-foreground">{{ leadForm.confirmation_message }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Código de incorporação</CardTitle>
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
                            {{ copyStatus.iframe === 'copied' ? 'Código copiado para a área de transferência.' : 'Não foi possível copiar automaticamente.' }}
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
                            {{ copyStatus.script === 'copied' ? 'Código copiado para a área de transferência.' : 'Não foi possível copiar automaticamente.' }}
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
                    <CardTitle>Submissões</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="submissions.data.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Sem submissões ainda.
                    </div>

                    <template v-else>
                        <div class="overflow-x-auto rounded-md border">
                            <table class="min-w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Data</th>
                                        <th class="px-3 py-2 text-left font-medium">Lead criada</th>
                                        <th class="px-3 py-2 text-left font-medium">Origem</th>
                                        <th class="px-3 py-2 text-left font-medium">IP</th>
                                        <th class="px-3 py-2 text-left font-medium">Dados</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="submission in submissions.data" :key="submission.id" class="border-t">
                                        <td class="px-3 py-2">{{ submission.submitted_at || '-' }}</td>
                                        <td class="px-3 py-2">
                                            <div v-if="submission.contact">
                                                <Link :href="`/people/${submission.contact.id}`" class="font-medium text-blue-700 underline">
                                                    {{ submission.contact.name }}
                                                </Link>
                                                <div class="text-xs text-muted-foreground">{{ submission.contact.email || '-' }}</div>
                                            </div>
                                            <span v-else>-</span>
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
                                        </td>
                                        <td class="px-3 py-2">{{ submission.ip_address || '-' }}</td>
                                        <td class="px-3 py-2">{{ prettyPayload(submission.payload) || '-' }}</td>
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
