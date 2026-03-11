<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type ContactShowPayload = {
    id: number;
    number: number;
    entity_id: number | null;
    entity: string | null;
    first_name: string;
    last_name: string | null;
    role: string | null;
    phone: string | null;
    mobile: string | null;
    email: string | null;
    gdpr_consent: boolean;
    notes: string | null;
    status: string;
};

type InteractionHistoryItem = {
    key: string;
    interaction_type: string;
    title: string;
    details: string;
    occurred_at: string;
};

type DuplicateCandidate = {
    id: number;
    full_name: string;
    email: string | null;
    mobile: string | null;
    entity: string | null;
    reason: string;
};

const props = defineProps<{
    contact: ContactShowPayload;
    interaction_history: InteractionHistoryItem[];
    duplicate_candidates: DuplicateCandidate[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pessoas', href: '/people' },
    {
        title: `${props.contact.first_name} ${props.contact.last_name ?? ''}`.trim(),
        href: `/people/${props.contact.id}`,
    },
];

function destroyContact(): void {
    if (!window.confirm('Tens a certeza que queres eliminar esta pessoa?')) {
        return;
    }

    router.delete(`/people/${props.contact.id}`);
}

const selectedDuplicateId = ref<string>('');
const duplicateCandidates = computed(() =>
    props.duplicate_candidates.filter((candidate) => candidate.id !== props.contact.id),
);

function mergeDuplicate(): void {
    if (selectedDuplicateId.value === '') {
        return;
    }

    if (Number(selectedDuplicateId.value) === props.contact.id) {
        return;
    }

    if (!window.confirm('Confirmas o merge deste duplicado nesta pessoa principal?')) {
        return;
    }

    router.post(`/people/${props.contact.id}/merge`, {
        duplicate_contact_id: Number(selectedDuplicateId.value),
    });
}
</script>

<template>
    <Head :title="`${contact.first_name} ${contact.last_name ?? ''}`.trim()" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ `${contact.first_name} ${contact.last_name ?? ''}`.trim() }}</CardTitle>
                    <div class="flex gap-2">
                        <Button variant="outline" as-child>
                            <Link href="/people">Voltar</Link>
                        </Button>
                        <Button variant="outline" as-child>
                            <Link :href="`/people/${contact.id}/edit`">Editar</Link>
                        </Button>
                        <Button variant="destructive" @click="destroyContact">
                            Eliminar
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-4 md:grid-cols-2">
                        <div><dt class="text-sm text-muted-foreground">Numero</dt><dd>{{ contact.number }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Entidade</dt><dd>{{ contact.entity || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Nome</dt><dd>{{ contact.first_name }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Apelido</dt><dd>{{ contact.last_name || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Funcao</dt><dd>{{ contact.role || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Estado</dt><dd>{{ contact.status }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Telefone</dt><dd>{{ contact.phone || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Telemovel</dt><dd>{{ contact.mobile || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Email</dt><dd>{{ contact.email || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Consentimento RGPD</dt><dd>{{ contact.gdpr_consent ? 'Sim' : 'Nao' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Observacoes</dt><dd>{{ contact.notes || '-' }}</dd></div>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Merge de duplicados</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p class="text-sm text-muted-foreground">
                        Junta registos duplicados nesta pessoa principal, mantendo historico e ligacoes.
                    </p>

                    <div v-if="duplicateCandidates.length === 0" class="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                        Nao foram encontrados duplicados sugeridos para esta pessoa.
                    </div>

                    <div v-else class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Selecionar duplicado</label>
                            <select
                                v-model="selectedDuplicateId"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="">Selecionar pessoa duplicada</option>
                                <option
                                    v-for="candidate in duplicateCandidates"
                                    :key="candidate.id"
                                    :value="String(candidate.id)"
                                >
                                    #{{ candidate.id }} - {{ candidate.full_name }} | {{ candidate.reason }} | {{ candidate.email || candidate.mobile || '-' }}
                                </option>
                            </select>
                        </div>
                        <Button
                            type="button"
                            :disabled="selectedDuplicateId === ''"
                            @click="mergeDuplicate"
                        >
                            Unir duplicado
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Historico de interacoes</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul v-if="interaction_history.length > 0" class="space-y-2">
                        <li
                            v-for="item in interaction_history"
                            :key="item.key"
                            class="rounded-md border p-3 text-sm"
                        >
                            <p class="font-medium">{{ item.occurred_at }} - {{ item.interaction_type }}</p>
                            <p class="text-muted-foreground">{{ item.title }}</p>
                            <p class="text-muted-foreground">{{ item.details }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">Sem interacoes para mostrar.</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
