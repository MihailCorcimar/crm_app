<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type ChatLink = {
    label: string;
    href: string;
};

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    text: string;
    created_at: string;
    links: ChatLink[];
};

type DealSummaryData = {
    type: 'deal_summary';
    stage: string | null;
    count: number;
    total: number;
    top_deals?: Array<{
        id: number;
        title: string;
        stage: string;
        stage_label: string;
        value: number;
    }>;
};

type ContactLookupData = {
    type: 'contact_lookup';
    found: boolean;
    field?: 'phone' | 'mobile' | 'email';
    value?: string | null;
    contact?: {
        id: number;
        name: string;
        entity?: string | null;
    };
};

type AiResponsePayload = {
    answer: string;
    intent: string;
    confidence: number;
    data: DealSummaryData | ContactLookupData | { type: 'unsupported' } | Record<string, unknown>;
};

type StreamPacket = {
    type: string;
    delta?: string;
    answer?: string;
    links?: ChatLink[];
};

const props = defineProps<{
    suggestedQuestions: string[];
    tenantId: number | null;
    historyMessages: ChatMessage[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chat IA', href: '/ai/chat' }];
const draft = ref('');
const sending = ref(false);
const messages = ref<ChatMessage[]>(Array.isArray(props.historyMessages) ? props.historyMessages : []);

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function nowLabel(): string {
    return new Date().toISOString();
}

function displayTimestamp(value: string): string {
    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString('pt-PT');
}

function createMessage(role: ChatMessage['role'], text: string, links: ChatLink[] = []): ChatMessage {
    return {
        id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        role,
        text,
        created_at: nowLabel(),
        links,
    };
}

function formatAssistantAnswer(payload: AiResponsePayload): string {
    return payload.answer || 'Sem resposta disponivel.';
}

function extractLinks(payload: AiResponsePayload): ChatLink[] {
    const data = payload.data;
    const links: ChatLink[] = [];

    if (typeof data === 'object' && data !== null && data.type === 'contact_lookup') {
        const typed = data as ContactLookupData;

        if (typed.found && typed.contact?.id) {
            links.push({
                label: `Abrir pessoa: ${typed.contact.name}`,
                href: `/people/${typed.contact.id}`,
            });
        }
    }

    if (typeof data === 'object' && data !== null && data.type === 'deal_summary') {
        const typed = data as DealSummaryData;
        const dealLinks = (typed.top_deals ?? []).slice(0, 3).map((deal) => ({
            label: `Abrir negocio: ${deal.title}`,
            href: `/deals/${deal.id}`,
        }));

        links.push(...dealLinks);
        links.push({ label: 'Ver Kanban de negocios', href: '/deals' });
    }

    return links;
}

function applyStreamPacket(packet: StreamPacket, assistantMessage: ChatMessage): boolean {
    if (packet.type === 'chunk' && typeof packet.delta === 'string') {
        assistantMessage.text += packet.delta;

        return false;
    }

    if ((packet.type === 'done' || packet.type === 'fallback')) {
        if (assistantMessage.text.trim() === '' && typeof packet.answer === 'string') {
            assistantMessage.text = packet.answer;
        }

        if (Array.isArray(packet.links)) {
            assistantMessage.links = packet.links;
        }

        return true;
    }

    return false;
}

async function tryStreamMessage(messageText: string, assistantMessage: ChatMessage): Promise<boolean> {
    const response = await fetch('/ai/chat/stream', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/x-ndjson',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ message: messageText }),
    });

    if (!response.ok || !response.body) {
        return false;
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let streamedAnyChunk = false;

    while (true) {
        const { done, value } = await reader.read();
        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });

        while (true) {
            const lineBreak = buffer.indexOf('\n');
            if (lineBreak < 0) {
                break;
            }

            const line = buffer.slice(0, lineBreak).trim();
            buffer = buffer.slice(lineBreak + 1);

            if (line === '') {
                continue;
            }

            try {
                const packet = JSON.parse(line) as StreamPacket;
                if (packet.type === 'chunk') {
                    streamedAnyChunk = true;
                }

                const finished = applyStreamPacket(packet, assistantMessage);
                if (finished) {
                    return true;
                }
            } catch {
                // Ignore malformed chunk and continue reading.
            }
        }
    }

    return streamedAnyChunk && assistantMessage.text.trim() !== '';
}

async function sendClassicMessage(messageText: string, assistantMessage: ChatMessage): Promise<void> {
    const response = await fetch('/ai/chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ message: messageText }),
    });

    const payload = (await response.json()) as AiResponsePayload;

    if (!response.ok) {
        assistantMessage.text = typeof payload.answer === 'string'
            ? payload.answer
            : 'Nao foi possivel processar a pergunta.';
        assistantMessage.links = [];

        return;
    }

    assistantMessage.text = formatAssistantAnswer(payload);
    assistantMessage.links = extractLinks(payload);
}

async function sendMessage(preFilledQuestion?: string): Promise<void> {
    if (sending.value) {
        return;
    }

    const messageText = (preFilledQuestion ?? draft.value).trim();
    if (messageText === '') {
        return;
    }

    const userMessage = createMessage('user', messageText);
    const assistantMessage = createMessage('assistant', '');

    messages.value.push(userMessage);
    messages.value.push(assistantMessage);

    draft.value = '';
    sending.value = true;

    try {
        const streamed = await tryStreamMessage(messageText, assistantMessage);

        if (!streamed) {
            await sendClassicMessage(messageText, assistantMessage);
        }

        if (assistantMessage.text.trim() === '') {
            assistantMessage.text = 'Sem resposta disponivel.';
        }
    } catch {
        try {
            await sendClassicMessage(messageText, assistantMessage);
        } catch {
            assistantMessage.text = 'Ocorreu um erro ao comunicar com o chat. Tenta novamente.';
            assistantMessage.links = [];
        }
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <Head title="Chat IA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Chat inteligente</CardTitle>
                    <CardDescription>Faz perguntas em linguagem natural sobre negocios e pessoas.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p class="text-sm font-medium">Perguntas sugeridas</p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="question in suggestedQuestions"
                            :key="question"
                            variant="outline"
                            size="sm"
                            type="button"
                            @click="sendMessage(question)"
                        >
                            {{ question }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card class="flex min-h-[520px] flex-col">
                <CardHeader>
                    <CardTitle>Conversa</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-1 flex-col gap-4">
                    <div class="bg-muted/30 flex-1 space-y-3 overflow-y-auto rounded-lg border p-3">
                        <p v-if="messages.length === 0" class="text-sm text-muted-foreground">
                            Ainda sem mensagens. Usa uma pergunta sugerida ou escreve a tua pergunta.
                        </p>

                        <div
                            v-for="message in messages"
                            :key="message.id"
                            class="space-y-2 rounded-md border p-3"
                            :class="message.role === 'user' ? 'bg-background ml-auto max-w-[85%]' : 'bg-muted mr-auto max-w-[90%]'"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ message.role === 'user' ? 'Tu' : 'Assistente' }} - {{ displayTimestamp(message.created_at) }}
                            </p>
                            <p class="text-sm whitespace-pre-wrap">{{ message.text }}</p>

                            <div v-if="message.links.length > 0" class="flex flex-wrap gap-2">
                                <Button
                                    v-for="link in message.links"
                                    :key="`${message.id}-${link.href}`"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="link.href">{{ link.label }}</Link>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <form class="space-y-3" @submit.prevent="sendMessage()">
                        <label class="text-sm font-medium" for="chat-question">Pergunta</label>
                        <textarea
                            id="chat-question"
                            v-model="draft"
                            rows="3"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            placeholder="Ex.: Qual o volume de negocios em negociacao?"
                        />
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="sending || draft.trim() === ''">
                                {{ sending ? 'A processar...' : 'Enviar pergunta' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
