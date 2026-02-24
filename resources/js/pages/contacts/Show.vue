<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type ContactShowPayload = {
    id: number;
    number: number;
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

const props = defineProps<{
    contact: ContactShowPayload;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contactos', href: '/contacts' },
    {
        title: `${props.contact.first_name} ${props.contact.last_name ?? ''}`.trim(),
        href: `/contacts/${props.contact.id}`,
    },
];

function destroyContact(): void {
    if (!window.confirm('Tens a certeza que queres eliminar este contacto?')) {
        return;
    }

    router.delete(`/contacts/${props.contact.id}`);
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
                            <Link href="/contacts">Voltar</Link>
                        </Button>
                        <Button variant="outline" as-child>
                            <Link :href="`/contacts/${contact.id}/edit`">Editar</Link>
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
        </div>
    </AppLayout>
</template>
