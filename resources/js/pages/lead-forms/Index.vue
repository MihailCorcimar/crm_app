<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type FormRow = {
    id: number;
    name: string;
    slug: string;
    status: 'active' | 'inactive';
    requires_captcha: boolean;
    enabled_fields_count: number;
    submissions_count: number;
    public_url: string;
    updated_at: string | null;
};

type FormPaginator = {
    data: FormRow[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

const props = defineProps<{
    forms: FormPaginator;
    filters: {
        q: string;
        status: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Formulários publicos', href: '/lead-forms' }];

const filterForm = useForm({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

function applyFilters(): void {
    filterForm.get('/lead-forms', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters(): void {
    filterForm.q = '';
    filterForm.status = '';
    applyFilters();
}

function removeForm(formId: number): void {
    if (!window.confirm('Remover este formulário publico?')) {
        return;
    }

    router.delete(`/lead-forms/${formId}`);
}
</script>

<template>
    <Head title="Formulários publicos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Filtros</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-3 md:grid-cols-3" @submit.prevent="applyFilters">
                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Pesquisar</label>
                            <Input v-model="filterForm.q" placeholder="Nome ou slug" />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Estado</label>
                            <select
                                v-model="filterForm.status"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option :value="''">Todos</option>
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <Button type="submit" :disabled="filterForm.processing">Filtrar</Button>
                            <Button type="button" variant="outline" :disabled="filterForm.processing" @click="clearFilters">Limpar</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Formulários publicos</CardTitle>
                    <Button as-child>
                        <Link href="/lead-forms/create">Criar formulário</Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <div v-if="forms.data.length === 0" class="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        Sem formulários para os filtros selecionados.
                    </div>

                    <template v-else>
                        <div class="overflow-x-auto rounded-md border">
                            <table class="min-w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Formulário</th>
                                        <th class="px-3 py-2 text-left font-medium">Estado</th>
                                        <th class="px-3 py-2 text-right font-medium">Campos</th>
                                        <th class="px-3 py-2 text-right font-medium">Submissoes</th>
                                        <th class="px-3 py-2 text-left font-medium">URL publica</th>
                                        <th class="px-3 py-2 text-right font-medium">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="formRow in forms.data" :key="formRow.id" class="border-t">
                                        <td class="px-3 py-2">
                                            <div class="font-medium">{{ formRow.name }}</div>
                                            <div class="text-xs text-muted-foreground">{{ formRow.slug }}</div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span
                                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                                :class="formRow.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-200 text-zinc-700'"
                                            >
                                                {{ formRow.status === 'active' ? 'Ativo' : 'Inativo' }}
                                            </span>
                                            <div class="mt-1 text-xs text-muted-foreground">
                                                Captcha: {{ formRow.requires_captcha ? 'Sim' : 'Nao' }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ formRow.enabled_fields_count }}</td>
                                        <td class="px-3 py-2 text-right">{{ formRow.submissions_count }}</td>
                                        <td class="px-3 py-2">
                                            <a :href="formRow.public_url" target="_blank" rel="noopener noreferrer" class="text-xs text-blue-700 underline">
                                                Abrir formulário
                                            </a>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <div class="flex justify-end gap-2">
                                                <Button as-child size="sm" variant="outline">
                                                    <Link :href="`/lead-forms/${formRow.id}`">Detalhe</Link>
                                                </Button>
                                                <Button as-child size="sm" variant="outline">
                                                    <Link :href="`/lead-forms/${formRow.id}/edit`">Editar</Link>
                                                </Button>
                                                <Button size="sm" variant="destructive" @click="removeForm(formRow.id)">
                                                    Remover
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                v-for="link in forms.links"
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

