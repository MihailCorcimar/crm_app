<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type EntityShowPayload = {
    id: number;
    type: string;
    number: number;
    tax_id: string;
    name: string;
    phone: string | null;
    mobile: string | null;
    website: string | null;
    email: string | null;
    status: string;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    country: string | null;
    notes: string | null;
    gdpr_consent: boolean;
};

const props = defineProps<{
    entity: EntityShowPayload;
}>();

const listingHref =
    props.entity.type === 'supplier'
        ? '/entities?type=supplier'
        : props.entity.type === 'both'
          ? '/entities?type=both'
          : '/entities?type=customer';

const sectionTitle =
    props.entity.type === 'supplier'
        ? 'Fornecedores'
        : props.entity.type === 'both'
          ? 'Entidades'
          : 'Clientes';

const breadcrumbs: BreadcrumbItem[] = [
    { title: sectionTitle, href: listingHref },
    { title: props.entity.name, href: `/entities/${props.entity.id}` },
];

function destroyEntity(): void {
    if (!window.confirm('Tens a certeza que queres eliminar esta entidade?')) {
        return;
    }

    router.delete(`/entities/${props.entity.id}`);
}
</script>

<template>
    <Head :title="entity.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ entity.name }}</CardTitle>
                    <div class="flex gap-2">
                        <Button variant="outline" as-child>
                            <Link :href="listingHref">Voltar</Link>
                        </Button>
                        <Button variant="outline" as-child>
                            <Link :href="`/entities/${entity.id}/edit`">Editar</Link>
                        </Button>
                        <Button variant="destructive" @click="destroyEntity">
                            Eliminar
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-4 md:grid-cols-2">
                        <div><dt class="text-sm text-muted-foreground">Numero</dt><dd>{{ entity.number }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Tipo</dt><dd>{{ entity.type }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">NIF</dt><dd>{{ entity.tax_id }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Estado</dt><dd>{{ entity.status }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Telefone</dt><dd>{{ entity.phone || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Telemovel</dt><dd>{{ entity.mobile || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Website</dt><dd>{{ entity.website || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Email</dt><dd>{{ entity.email || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Morada</dt><dd>{{ entity.address || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Codigo postal</dt><dd>{{ entity.postal_code || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Cidade</dt><dd>{{ entity.city || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Pais</dt><dd>{{ entity.country || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Consentimento RGPD</dt><dd>{{ entity.gdpr_consent ? 'Sim' : 'Nao' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Notas</dt><dd>{{ entity.notes || '-' }}</dd></div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
