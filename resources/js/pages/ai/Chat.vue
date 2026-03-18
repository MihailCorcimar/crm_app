<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type ChatLink = { label: string; href: string };
type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    text: string;
    created_at: string;
    session_id?: string | null;
    links: ChatLink[];
};
type ChatSession = {
    id: string;
    title: string;
    preview: string;
    updated_at: string;
    message_count: number;
    is_legacy?: boolean;
};
type ChatHistoryPayload = {
    messages: ChatMessage[];
    sessions: ChatSession[];
    active_session_id: string | null;
};
type SessionGroup = {
    label: string;
    items: ChatSession[];
};
type StreamPacket = { type: string; delta?: string; answer?: string; links?: ChatLink[] };
type AiResponsePayload = { answer?: string; links?: ChatLink[] };
type AiSuggestion = {
    id: number;
    title: string;
    reason: string;
    next_step: string | null;
    action_type: string;
    priority_score: number;
    suggested_for_at: string | null;
    deal: { id: number; title: string } | null;
    contact: { id: number; name: string } | null;
};

const props = defineProps<{
    tenantId: number | null;
    historyMessages: ChatMessage[];
    chatSessions?: ChatSession[];
    activeSessionId?: string | null;
    suggestions: AiSuggestion[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chat IA', href: '/ai/chat' }];
const draft = ref('');
const sending = ref(false);
const chatViewport = ref<HTMLElement | null>(null);
const copiedMessageId = ref<string | null>(null);
const selectedSessionId = ref('');
const sessionMessages = ref<Record<string, ChatMessage[]>>({});
const sessions = ref<ChatSession[]>(Array.isArray(props.chatSessions) ? [...props.chatSessions] : []);
const historyQuery = ref('');
const historySyncing = ref(false);
const suggestionItems = ref<AiSuggestion[]>([]);
const suggestionsLoading = ref(false);
const activeSuggestionId = ref<number | null>(null);
const messages = computed(() => sessionMessages.value[selectedSessionId.value] ?? []);
const filteredSessions = computed(() => {
    const query = historyQuery.value.trim().toLowerCase();
    if (query === '') return sessions.value;

    return sessions.value.filter((session) => {
        const title = normalizePtText(session.title).toLowerCase();
        const preview = normalizePtText(session.preview).toLowerCase();

        return title.includes(query) || preview.includes(query);
    });
});
const groupedSessions = computed<SessionGroup[]>(() => {
    if (filteredSessions.value.length === 0) return [];

    const buckets: Record<string, ChatSession[]> = {
        Hoje: [],
        Ontem: [],
        Anteriores: [],
    };

    for (const session of filteredSessions.value) {
        buckets[historyLabelForSession(session.updated_at)].push(session);
    }

    return Object.entries(buckets)
        .filter(([, items]) => items.length > 0)
        .map(([label, items]) => ({ label, items }));
});

function decodeUnicodeEscapes(value: string): string {
    return value.replace(/\\u([0-9a-fA-F]{4})/g, (_all, hex: string) => String.fromCharCode(Number.parseInt(hex, 16)));
}
function repairLegacyMojibake(value: string): string {
    if (!/[\u00C3\u00C2\u00E2]/.test(value)) return value;

    try {
        const bytes = Uint8Array.from(Array.from(value).map((char) => char.charCodeAt(0) & 0xff));
        const decoded = new TextDecoder('utf-8').decode(bytes);

        return decoded || value;
    } catch {
        return value;
    }
}
function normalizePtText(value: string): string {
    return repairLegacyMojibake(decodeUnicodeEscapes(String(value || '')));
}
function normalizeLinks(links: ChatLink[]): ChatLink[] {
    return links.map((link) => ({ ...link, label: normalizePtText(link.label) }));
}
function extractApiError(payload: unknown): string {
    if (payload && typeof payload === 'object') {
        const p = payload as Record<string, unknown>;
        if (typeof p.answer === 'string' && p.answer.trim() !== '') return normalizePtText(p.answer);
        if (typeof p.message === 'string' && p.message.trim() !== '') return normalizePtText(p.message);
        const errors = p.errors;
        if (errors && typeof errors === 'object') {
            const first = Object.values(errors as Record<string, unknown>).find((v) => Array.isArray(v) && v.length > 0) as unknown[] | undefined;
            if (first && typeof first[0] === 'string') return normalizePtText(String(first[0]));
        }
    }

    return 'Não foi possível processar a pergunta.';
}
function normalizeSuggestion(item: AiSuggestion): AiSuggestion {
    return {
        ...item,
        title: normalizePtText(item.title),
        reason: normalizePtText(item.reason),
        next_step: item.next_step ? normalizePtText(item.next_step) : null,
        action_type: normalizePtText(item.action_type),
        deal: item.deal ? { ...item.deal, title: normalizePtText(item.deal.title) } : null,
        contact: item.contact ? { ...item.contact, name: normalizePtText(item.contact.name) } : null,
    };
}

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}
function nowIso(): string {
    return new Date().toISOString();
}
function ts(v: string): string {
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? v : d.toLocaleString('pt-PT');
}
function normSession(v?: string | null): string {
    return typeof v === 'string' && v.trim() !== '' ? v : 'legacy';
}
function uuid(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID();

    // Fallback UUID v4 for non-secure contexts where randomUUID is unavailable.
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16);
        const value = char === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
}
function isUuid(value: string | null | undefined): value is string {
    if (typeof value !== 'string') return false;

    return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value);
}
function requestSessionId(): string | null {
    if (selectedSessionId.value === 'legacy') return null;
    if (!isUuid(selectedSessionId.value)) return null;

    return selectedSessionId.value;
}
function trimText(t: string, n: number): string {
    const c = t.replace(/\s+/g, ' ').trim();
    return c.length <= n ? c : `${c.slice(0, Math.max(0, n - 3))}...`;
}
function normalizeSession(session: ChatSession): ChatSession {
    return {
        ...session,
        title: normalizePtText(session.title),
        preview: normalizePtText(session.preview),
        updated_at: typeof session.updated_at === 'string' ? session.updated_at : '',
        message_count: Number.isFinite(Number(session.message_count)) ? Number(session.message_count) : 0,
    };
}
function historyLabelForSession(updatedAt: string): 'Hoje' | 'Ontem' | 'Anteriores' {
    const date = new Date(updatedAt);
    if (Number.isNaN(date.getTime())) return 'Anteriores';

    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
    const target = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    const diffDays = Math.floor((today - target) / (24 * 60 * 60 * 1000));

    if (diffDays <= 0) return 'Hoje';
    if (diffDays === 1) return 'Ontem';

    return 'Anteriores';
}
function ensureSession(id: string): void {
    if (!sessionMessages.value[id]) {
        sessionMessages.value = { ...sessionMessages.value, [id]: [] };
    }
    if (!sessions.value.some((s) => s.id === id)) {
        sessions.value = [{ id, title: id === 'legacy' ? 'Histórico anterior' : 'Novo chat', preview: '', updated_at: nowIso(), message_count: 0, is_legacy: id === 'legacy' }, ...sessions.value];
    }
}
function sortSessions(): void {
    sessions.value = [...sessions.value].sort((a, b) => Date.parse(b.updated_at || '') - Date.parse(a.updated_at || ''));
}
function refreshSession(id: string): void {
    const list = sessionMessages.value[id] ?? [];
    const last = list[list.length - 1];
    const firstUser = list.find((m) => m.role === 'user' && m.text.trim() !== '');
    const next: ChatSession = {
        id,
        title: id === 'legacy' ? 'Histórico anterior' : trimText(firstUser?.text || 'Novo chat', 46),
        preview: trimText(last?.text || '', 78),
        updated_at: last?.created_at || nowIso(),
        message_count: list.length,
        is_legacy: id === 'legacy',
    };
    const idx = sessions.value.findIndex((s) => s.id === id);
    if (idx >= 0) {
        const clone = [...sessions.value];
        clone[idx] = next;
        sessions.value = clone;
    } else {
        sessions.value = [next, ...sessions.value];
    }
    sortSessions();
}
function setSession(id: string): void {
    ensureSession(id);
    selectedSessionId.value = id;
}
function buildGroupedMessages(historyMessages: ChatMessage[]): Record<string, ChatMessage[]> {
    const grouped: Record<string, ChatMessage[]> = {};
    for (const row of historyMessages) {
        const id = normSession(row.session_id);
        if (!grouped[id]) grouped[id] = [];
        grouped[id].push({
            ...row,
            text: normalizePtText(row.text),
            links: normalizeLinks(Array.isArray(row.links) ? row.links : []),
        });
    }

    return grouped;
}
function applyHistoryPayload(payload: ChatHistoryPayload, preferredSessionId?: string): void {
    const grouped = buildGroupedMessages(Array.isArray(payload.messages) ? payload.messages : []);
    sessionMessages.value = grouped;

    const incomingSessions = Array.isArray(payload.sessions)
        ? payload.sessions.map(normalizeSession)
        : [];

    if (incomingSessions.length > 0) {
        sessions.value = incomingSessions;
    } else {
        sessions.value = Object.keys(grouped).map((id) => ({ id, title: id === 'legacy' ? 'Histórico anterior' : 'Novo chat', preview: '', updated_at: '', message_count: grouped[id]?.length ?? 0, is_legacy: id === 'legacy' }));
    }

    for (const id of Object.keys(grouped)) refreshSession(id);

    const preferred = preferredSessionId !== undefined
        ? normSession(preferredSessionId)
        : normSession(payload.active_session_id ?? null);

    if (grouped[preferred]) return setSession(preferred);
    if (sessions.value.length > 0) return setSession(sessions.value[0].id);
    const created = uuid();
    ensureSession(created);
    setSession(created);
}
function hydrate(): void {
    suggestionItems.value = Array.isArray(props.suggestions) ? props.suggestions.map(normalizeSuggestion) : [];

    applyHistoryPayload(
        {
            messages: Array.isArray(props.historyMessages) ? props.historyMessages : [],
            sessions: Array.isArray(props.chatSessions) ? props.chatSessions : [],
            active_session_id: props.activeSessionId ?? null,
        },
        props.activeSessionId ?? undefined,
    );
}
function push(role: 'user' | 'assistant', text: string, links: ChatLink[] = []): ChatMessage {
    const m: ChatMessage = {
        id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        role,
        text: normalizePtText(text),
        created_at: nowIso(),
        session_id: selectedSessionId.value,
        links: normalizeLinks(links),
    };
    const list = sessionMessages.value[selectedSessionId.value] ?? [];
    list.push(m);
    sessionMessages.value = { ...sessionMessages.value, [selectedSessionId.value]: list };
    refreshSession(selectedSessionId.value);
    return m;
}
function botLabel(link: ChatLink): string {
    return normalizePtText(link.label).replace(/^Abrir pessoa:\s*/i, '').replace(/^Abrir neg[oó]cio:\s*/i, '');
}
function actionLabel(kind: string): string {
    return ({ call: 'Telefonar', meeting: 'Marcar reunião', task: 'Criar tarefa', note: 'Adicionar nota', send_proposal: 'Enviar proposta', follow_up_email: 'Enviar follow-up', request_docs: 'Pedir documentos', validate_expectations: 'Validar expectativas' }[kind] ?? 'Executar ação');
}
function priorityClass(p: number): string {
    if (p >= 80) return 'bg-red-100 text-red-700';
    if (p >= 50) return 'bg-amber-100 text-amber-700';
    return 'bg-emerald-100 text-emerald-700';
}
function priorityLabel(p: number): string {
    if (p >= 80) return 'Prioridade Alta';
    if (p >= 50) return 'Prioridade Média';
    return 'Prioridade Baixa';
}
async function scrollBottom(smooth = true): Promise<void> {
    await nextTick();
    const el = chatViewport.value;
    if (!el) return;
    el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
}
function startNewChat(): void {
    if (sending.value) return;
    if (messages.value.length > 0 && !window.confirm('Iniciar um novo chat? A conversa atual ficará no histórico.')) return;
    const id = uuid();
    ensureSession(id);
    setSession(id);
    draft.value = '';
    void scrollBottom(false);
}
function openSession(id: string): void {
    if (sending.value) return;
    setSession(id);
    void scrollBottom(false);
}
async function copyMessage(msg: ChatMessage): Promise<void> {
    try { await navigator.clipboard.writeText(msg.text); } catch {}
    copiedMessageId.value = msg.id;
    window.setTimeout(() => { if (copiedMessageId.value === msg.id) copiedMessageId.value = null; }, 1400);
}
async function syncHistory(keepCurrentSession = true): Promise<void> {
    if (historySyncing.value) return;

    historySyncing.value = true;
    try {
        const r = await fetch('/ai/chat/history', {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        if (!r.ok) return;
        const payload = (await r.json()) as ChatHistoryPayload;

        applyHistoryPayload(payload, keepCurrentSession ? selectedSessionId.value : undefined);
    } finally {
        historySyncing.value = false;
    }
}
async function loadSuggestions(): Promise<void> {
    suggestionsLoading.value = true;
    try {
        const r = await fetch('/ai/suggestions', { method: 'GET', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() } });
        if (!r.ok) return;
        const payload = (await r.json()) as { suggestions?: AiSuggestion[] };
        suggestionItems.value = Array.isArray(payload.suggestions) ? payload.suggestions.map(normalizeSuggestion) : [];
    } finally {
        suggestionsLoading.value = false;
    }
}
async function refreshSuggestions(): Promise<void> {
    suggestionsLoading.value = true;
    try {
        const r = await fetch('/ai/suggestions/refresh', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() }, body: JSON.stringify({}) });
        if (!r.ok) return;
        const payload = (await r.json()) as { suggestions?: AiSuggestion[] };
        suggestionItems.value = Array.isArray(payload.suggestions) ? payload.suggestions.map(normalizeSuggestion) : [];
    } finally {
        suggestionsLoading.value = false;
    }
}
async function applySuggestionAction(id: number, action: 'accept' | 'defer' | 'archive'): Promise<void> {
    if (activeSuggestionId.value !== null) return;
    activeSuggestionId.value = id;
    try {
        const r = await fetch(`/ai/suggestions/${id}/${action}`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() }, body: JSON.stringify(action === 'defer' ? { days: 2 } : {}) });
        if (!r.ok) return;
        await loadSuggestions();
    } finally {
        activeSuggestionId.value = null;
    }
}
function applyPacket(packet: StreamPacket, assistant: ChatMessage): boolean {
    if (packet.type === 'chunk' && typeof packet.delta === 'string') { assistant.text += normalizePtText(packet.delta); return false; }
    if (packet.type === 'done' || packet.type === 'fallback') {
        if (assistant.text.trim() === '' && typeof packet.answer === 'string') assistant.text = normalizePtText(packet.answer);
        if (Array.isArray(packet.links)) assistant.links = normalizeLinks(packet.links);
        return true;
    }
    return false;
}
async function tryStream(messageText: string, assistant: ChatMessage): Promise<boolean> {
    const r = await fetch('/ai/chat/stream', { method: 'POST', headers: { Accept: 'application/x-ndjson', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() }, body: JSON.stringify({ message: messageText, session_id: requestSessionId() }) });
    if (!r.ok || !r.body) return false;
    const contentType = String(r.headers.get('content-type') || '');
    if (contentType.includes('application/json')) {
        try {
            const payload = (await r.json()) as Record<string, unknown>;
            assistant.text = extractApiError(payload);
            assistant.links = normalizeLinks(Array.isArray(payload.links) ? (payload.links as ChatLink[]) : []);

            return true;
        } catch {
            return false;
        }
    }
    const reader = r.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let gotChunk = false;
    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        while (true) {
            const i = buffer.indexOf('\n');
            if (i < 0) break;
            const line = buffer.slice(0, i).trim();
            buffer = buffer.slice(i + 1);
            if (line === '') continue;
            try {
                const packet = JSON.parse(line) as StreamPacket;
                if (packet.type === 'chunk') gotChunk = true;
                if (applyPacket(packet, assistant)) return true;
            } catch {}
        }
    }
    return gotChunk && assistant.text.trim() !== '';
}
async function sendClassic(messageText: string, assistant: ChatMessage): Promise<void> {
    const r = await fetch('/ai/chat', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() }, body: JSON.stringify({ message: messageText, session_id: requestSessionId() }) });
    const payload = (await r.json()) as AiResponsePayload & Record<string, unknown>;
    if (!r.ok) {
        assistant.text = extractApiError(payload);
        assistant.links = normalizeLinks(Array.isArray(payload.links) ? payload.links : []);

        return;
    }
    assistant.text = normalizePtText(payload.answer || 'Sem resposta disponível.');
    assistant.links = normalizeLinks(Array.isArray(payload.links) ? payload.links : []);
}
async function sendMessage(): Promise<void> {
    if (sending.value) return;
    const text = draft.value.trim();
    if (text === '') return;
    if (selectedSessionId.value === '' || selectedSessionId.value === 'legacy') {
        const id = uuid();
        ensureSession(id);
        setSession(id);
    }
    const user = push('user', text);
    const assistant = push('assistant', '');
    void user;
    draft.value = '';
    sending.value = true;
    await scrollBottom();
    try {
        const streamed = await tryStream(text, assistant);
        if (!streamed) await sendClassic(text, assistant);
        if (assistant.text.trim() === '') assistant.text = 'Sem resposta disponível.';
    } catch {
        try { await sendClassic(text, assistant); } catch { assistant.text = 'Ocorreu um erro ao comunicar com o chat. Tenta novamente.'; assistant.links = []; }
    } finally {
        sending.value = false;
        await syncHistory(true);
        refreshSession(selectedSessionId.value);
        await scrollBottom();
    }
}
function onTextareaKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); void sendMessage(); }
}

onMounted(async () => {
    hydrate();
    await scrollBottom(false);
    if (suggestionItems.value.length === 0) await loadSuggestions();
});
watch(() => messages.value.length, async () => { await scrollBottom(); });
</script>

<template>
    <Head title="Chat IA" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid gap-4 xl:grid-cols-12">
                <Card class="xl:col-span-8">
                    <CardHeader class="border-b pb-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <CardTitle class="text-xl">Chat IA</CardTitle>
                                <CardDescription>Faz perguntas em linguagem natural e obtém respostas com ações diretas no CRM.</CardDescription>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button size="sm" variant="outline" type="button" @click="draft = ''">Limpar texto</Button>
                                <Button size="sm" variant="outline" type="button" @click="startNewChat">Novo chat</Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4 pt-4">
                        <div class="grid gap-3 lg:grid-cols-[300px_minmax(0,1fr)]">
                            <aside class="rounded-xl border bg-muted/20 p-3">
                                <div class="mb-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Histórico de chats</p>
                                </div>
                                <input
                                    v-model="historyQuery"
                                    type="text"
                                    class="border-input bg-background mb-3 h-9 w-full rounded-md border px-3 text-xs shadow-xs"
                                    placeholder="Pesquisar conversa..."
                                />
                                <div class="max-h-[58vh] space-y-3 overflow-y-auto pr-1">
                                    <template v-for="group in groupedSessions" :key="group.label">
                                        <div>
                                            <p class="mb-1 px-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ group.label }}</p>
                                            <div class="space-y-2">
                                                <button
                                                    v-for="session in group.items"
                                                    :key="session.id"
                                                    type="button"
                                                    class="w-full rounded-lg border px-3 py-2 text-left transition-colors"
                                                    :class="selectedSessionId === session.id ? 'border-primary bg-primary/10 shadow-xs' : 'bg-background hover:bg-muted/50'"
                                                    @click="openSession(session.id)"
                                                >
                                                    <p class="truncate text-sm font-medium">{{ session.title || 'Sem título' }}</p>
                                                    <p class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ session.preview || 'Sem mensagens nesta conversa.' }}</p>
                                                    <div class="mt-2 flex items-center justify-between">
                                                        <p class="text-[11px] text-muted-foreground">{{ ts(session.updated_at) }}</p>
                                                        <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">{{ session.message_count }} msg</span>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                    <p v-if="groupedSessions.length === 0" class="rounded-lg border border-dashed p-3 text-xs text-muted-foreground">
                                        Sem conversas para este filtro.
                                    </p>
                                </div>
                            </aside>

                            <div ref="chatViewport" class="bg-muted/30 h-[58vh] space-y-4 overflow-y-auto rounded-xl border p-4">
                            <div v-if="messages.length === 0" class="mx-auto max-w-lg rounded-xl border border-dashed bg-background px-4 py-6 text-center">
                                <p class="text-sm font-medium">Sem mensagens ainda</p>
                                <p class="mt-1 text-sm text-muted-foreground">Escreve a tua pergunta abaixo para começar.</p>
                            </div>
                            <div v-for="message in messages" :key="message.id" class="space-y-2" :class="message.role === 'user' ? 'ml-auto max-w-[85%]' : 'mr-auto max-w-[92%]'">
                                <div class="rounded-2xl border px-3 py-2 shadow-sm" :class="message.role === 'user' ? 'border-primary/20 bg-primary/10' : 'bg-card border-border'">
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <p class="text-xs font-medium text-muted-foreground">{{ message.role === 'user' ? 'Tu' : 'Assistente IA' }}</p>
                                        <p class="text-xs text-muted-foreground">{{ ts(message.created_at) }}</p>
                                    </div>
                                    <div class="text-sm whitespace-pre-wrap">
                                        <span v-if="message.text">{{ message.text }}</span>
                                        <span v-else-if="sending && message.role === 'assistant'" class="inline-flex items-center gap-1 text-muted-foreground">A responder...</span>
                                    </div>
                                    <div v-if="message.links.length > 0 || message.role === 'assistant'" class="mt-3 flex flex-wrap gap-2">
                                        <Button v-for="link in message.links" :key="`${message.id}-${link.href}`" variant="outline" size="sm" as-child>
                                            <Link :href="link.href">{{ botLabel(link) }}</Link>
                                        </Button>
                                        <Button v-if="message.role === 'assistant' && message.text.trim() !== ''" variant="ghost" size="sm" type="button" @click="copyMessage(message)">
                                            {{ copiedMessageId === message.id ? 'Copiado' : 'Copiar' }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                        <form class="space-y-2 rounded-xl border bg-background p-3" @submit.prevent="sendMessage()">
                            <label class="text-sm font-medium" for="chat-question">Mensagem</label>
                            <textarea id="chat-question" v-model="draft" rows="3" class="border-input bg-background ring-offset-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none" placeholder="Ex.: Qual o volume de negócios em negociação?" @keydown="onTextareaKeydown" />
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-muted-foreground">Enter envia. Shift+Enter muda de linha.</p>
                                <Button type="submit" :disabled="sending || draft.trim() === ''">{{ sending ? 'A processar...' : 'Enviar' }}</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card class="xl:col-span-4">
                    <CardHeader class="border-b pb-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="space-y-1">
                                <CardTitle>Sugestões do agente</CardTitle>
                                <CardDescription>Próximas ações recomendadas com maior impacto comercial.</CardDescription>
                            </div>
                            <Button type="button" variant="outline" size="sm" :disabled="suggestionsLoading" @click="refreshSuggestions">{{ suggestionsLoading ? 'A atualizar...' : 'Atualizar' }}</Button>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3 pt-4">
                        <p v-if="suggestionItems.length === 0" class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">Sem sugestões pendentes.</p>
                        <div v-for="suggestion in suggestionItems" :key="suggestion.id" class="space-y-3 rounded-lg border p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold leading-tight">{{ suggestion.title }}</p>
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="priorityClass(suggestion.priority_score)">{{ priorityLabel(suggestion.priority_score) }}</span>
                            </div>
                            <p class="text-sm">{{ suggestion.reason }}</p>
                            <p v-if="suggestion.next_step" class="text-xs text-muted-foreground">Próximo passo: {{ suggestion.next_step }}</p>
                            <div class="space-y-1 text-xs text-muted-foreground">
                                <p>Ação: {{ actionLabel(suggestion.action_type) }}</p>
                                <p v-if="suggestion.deal">Negócio: {{ suggestion.deal.title }}</p>
                                <p v-if="suggestion.contact">Pessoa: {{ suggestion.contact.name }}</p>
                                <p v-if="suggestion.suggested_for_at">Sugerido para: {{ ts(suggestion.suggested_for_at) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button size="sm" type="button" :disabled="activeSuggestionId === suggestion.id" @click="applySuggestionAction(suggestion.id, 'accept')">Aceitar</Button>
                                <Button size="sm" type="button" variant="outline" :disabled="activeSuggestionId === suggestion.id" @click="applySuggestionAction(suggestion.id, 'defer')">Adiar</Button>
                                <Button size="sm" type="button" variant="ghost" :disabled="activeSuggestionId === suggestion.id" @click="applySuggestionAction(suggestion.id, 'archive')">Arquivar</Button>
                                <Button v-if="suggestion.deal" size="sm" type="button" variant="outline" as-child>
                                    <Link :href="`/deals/${suggestion.deal.id}`">Abrir negócio</Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

