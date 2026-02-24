<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type LogRow = {
    id: number;
    date: string | null;
    time: string | null;
    user: string;
    menu: string;
    action: string;
    device: string;
    ip_address: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedLogs = {
    data: LogRow[];
    links: PaginationLink[];
};

type UserOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    logs: PaginatedLogs;
    filters: {
        user_id: string | number;
        menu: string;
    };
    users: UserOption[];
    menuOptions: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Logs', href: '/logs' },
];

const selectedUserId = ref(String(props.filters.user_id ?? ''));
const selectedMenu = ref(props.filters.menu ?? '');

watch(
    () => props.filters,
    (value) => {
        selectedUserId.value = String(value.user_id ?? '');
        selectedMenu.value = value.menu ?? '';
    },
);

const columns: ColumnDef<LogRow>[] = [
    { accessorKey: 'date', header: 'Data', cell: ({ row }: { row: { original: LogRow } }) => row.original.date ?? '-' },
    { accessorKey: 'time', header: 'Hora', cell: ({ row }: { row: { original: LogRow } }) => row.original.time ?? '-' },
    { accessorKey: 'user', header: 'Utilizador' },
    { accessorKey: 'menu', header: 'Menu' },
    { accessorKey: 'action', header: 'Acao' },
    { accessorKey: 'device', header: 'Dispositivo' },
    { accessorKey: 'ip_address', header: 'IP' },
];

function applyFilters(): void {
    router.get('/logs', {
        user_id: selectedUserId.value || '',
        menu: selectedMenu.value || '',
    }, {
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Logs</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <form class="grid gap-4 md:grid-cols-3" @submit.prevent="applyFilters">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Utilizador</label>
                            <select
                                v-model="selectedUserId"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="">Todos</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Menu</label>
                            <select
                                v-model="selectedMenu"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="">Todos</option>
                                <option v-for="menu in menuOptions" :key="menu" :value="menu">
                                    {{ menu }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <Button type="submit">Filtrar</Button>
                            <Button type="button" variant="outline" as-child>
                                <Link href="/logs">Limpar</Link>
                            </Button>
                        </div>
                    </form>

                    <DataTable
                        :columns="columns"
                        :data="logs.data"
                        empty-text="Sem registos de atividade."
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-for="link in logs.links"
                            :key="link.label"
                            :variant="link.active ? 'default' : 'outline'"
                            size="sm"
                            :disabled="!link.url"
                            as-child
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                            >
                                <span v-html="link.label" />
                            </Link>
                            <span v-else v-html="link.label" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
