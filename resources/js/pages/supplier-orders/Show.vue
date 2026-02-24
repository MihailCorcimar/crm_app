<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type SupplierOrderLine = {
    id: number;
    item: string;
    quantity: number;
    unit_price: number;
    line_total: number;
};

type SupplierInvoice = {
    id: number;
    number: number;
    invoice_date: string | null;
    total: number;
    status: string;
};

type SupplierOrderPayload = {
    id: number;
    number: number;
    order_date: string | null;
    status: string;
    total: number;
    supplier: string | null;
    supplier_tax_id: string | null;
    supplier_address: string | null;
    supplier_postal_code: string | null;
    supplier_city: string | null;
    supplier_phone: string | null;
    supplier_mobile: string | null;
    supplier_email: string | null;
    customer_order_id: number | null;
    customer_order_number: number | null;
    customer: string | null;
    lines: SupplierOrderLine[];
    invoices: SupplierInvoice[];
};

const props = defineProps<{ supplierOrder: SupplierOrderPayload }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Encomendas - Fornecedores', href: '/supplier-orders' },
    { title: `Encomenda fornecedor #${props.supplierOrder.number}`, href: `/supplier-orders/${props.supplierOrder.id}` },
];
</script>

<template>
    <Head :title="`Encomenda fornecedor #${supplierOrder.number}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Encomenda fornecedor #{{ supplierOrder.number }}</CardTitle>
                    <div class="flex gap-2">
                        <Button variant="outline" as-child><Link href="/supplier-orders">Voltar</Link></Button>
                        <Button v-if="supplierOrder.customer_order_id" variant="outline" as-child>
                            <Link :href="`/orders/${supplierOrder.customer_order_id}`">Encomenda cliente</Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div><dt class="text-sm text-muted-foreground">Data</dt><dd>{{ supplierOrder.order_date || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Fornecedor</dt><dd>{{ supplierOrder.supplier || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Estado</dt><dd>{{ supplierOrder.status === 'closed' ? 'Fechado' : 'Rascunho' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">NIF fornecedor</dt><dd>{{ supplierOrder.supplier_tax_id || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Encomenda cliente</dt><dd>{{ supplierOrder.customer_order_number ? `#${supplierOrder.customer_order_number}` : '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Cliente</dt><dd>{{ supplierOrder.customer || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Telefone</dt><dd>{{ supplierOrder.supplier_phone || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Telemovel</dt><dd>{{ supplierOrder.supplier_mobile || '-' }}</dd></div>
                        <div><dt class="text-sm text-muted-foreground">Email</dt><dd>{{ supplierOrder.supplier_email || '-' }}</dd></div>
                        <div class="md:col-span-2">
                            <dt class="text-sm text-muted-foreground">Morada</dt>
                            <dd>
                                {{ supplierOrder.supplier_address || '-' }}
                                {{ supplierOrder.supplier_postal_code ? `, ${supplierOrder.supplier_postal_code}` : '' }}
                                {{ supplierOrder.supplier_city ? ` ${supplierOrder.supplier_city}` : '' }}
                            </dd>
                        </div>
                        <div><dt class="text-sm text-muted-foreground">Total</dt><dd>{{ supplierOrder.total.toFixed(2) }} EUR</dd></div>
                    </div>

                    <div class="rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/30">
                                <tr>
                                    <th class="px-3 py-2 text-left">Artigo</th>
                                    <th class="px-3 py-2 text-left">Qtd</th>
                                    <th class="px-3 py-2 text-left">Preco unit.</th>
                                    <th class="px-3 py-2 text-left">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in supplierOrder.lines" :key="line.id" class="border-t">
                                    <td class="px-3 py-2">{{ line.item }}</td>
                                    <td class="px-3 py-2">{{ line.quantity }}</td>
                                    <td class="px-3 py-2">{{ line.unit_price.toFixed(2) }} EUR</td>
                                    <td class="px-3 py-2">{{ line.line_total.toFixed(2) }} EUR</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="supplierOrder.invoices.length" class="space-y-2">
                        <h3 class="text-sm font-medium">Faturas de fornecedor relacionadas</h3>
                        <div class="rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/30">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Numero</th>
                                        <th class="px-3 py-2 text-left">Data</th>
                                        <th class="px-3 py-2 text-left">Total</th>
                                        <th class="px-3 py-2 text-left">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="invoice in supplierOrder.invoices" :key="invoice.id" class="border-t">
                                        <td class="px-3 py-2">{{ invoice.number }}</td>
                                        <td class="px-3 py-2">{{ invoice.invoice_date || '-' }}</td>
                                        <td class="px-3 py-2">{{ invoice.total.toFixed(2) }} EUR</td>
                                        <td class="px-3 py-2">{{ invoice.status === 'paid' ? 'Paga' : 'Pendente de pagamento' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

