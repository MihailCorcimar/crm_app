<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type InvoicePayload = {
    id: number;
    number: number;
    invoice_date: string | null;
    due_date: string | null;
    supplier_id: number;
    supplier: string | null;
    supplier_email: string | null;
    supplier_order_id: number | null;
    supplier_order: number | null;
    total: number;
    status: string;
    document_url: string | null;
    payment_proof_url: string | null;
};

const props = defineProps<{ invoice: InvoicePayload }>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Faturas Fornecedor', href: '/supplier-invoices' },
    { title: `Fatura #${props.invoice.number}`, href: `/supplier-invoices/${props.invoice.id}` },
];

function destroyInvoice(): void {
    if (!window.confirm('Eliminar esta fatura fornecedor?')) {
        return;
    }

    router.delete(`/supplier-invoices/${props.invoice.id}`);
}
</script>

<template>
    <Head :title="`Fatura #${invoice.number}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Fatura Fornecedor #{{ invoice.number }}</CardTitle>
                    <div class="flex gap-2">
                        <Button variant="outline" as-child><Link href="/supplier-invoices">Voltar</Link></Button>
                        <Button variant="outline" as-child><Link :href="`/supplier-invoices/${invoice.id}/edit`">Editar</Link></Button>
                        <Button variant="destructive" @click="destroyInvoice">Eliminar</Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div><dt class="text-sm text-muted-foreground">Data</dt><dd>{{ invoice.invoice_date || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Vencimento</dt><dd>{{ invoice.due_date || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Fornecedor</dt><dd>{{ invoice.supplier || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Encomenda</dt><dd>{{ invoice.supplier_order ? `#${invoice.supplier_order}` : '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Valor Total</dt><dd>{{ invoice.total.toFixed(2) }} EUR</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Estado</dt><dd>{{ invoice.status === 'paid' ? 'Paga' : 'Pendente de Pagamento' }}</dd></div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-sm text-muted-foreground">Documento</dt>
                            <dd>
                                <Link v-if="invoice.document_url" :href="invoice.document_url" class="underline">Abrir documento</Link>
                                <span v-else>-</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Comprovativo de Pagamento</dt>
                            <dd>
                                <Link v-if="invoice.payment_proof_url" :href="invoice.payment_proof_url" class="underline">Abrir comprovativo</Link>
                                <span v-else>-</span>
                            </dd>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
