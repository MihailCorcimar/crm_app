<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type VatRateOption = {
    id: number;
    name: string;
    rate: number;
};

const props = defineProps<{
    form: {
        reference: string;
        code: string;
        name: string;
        description: string;
        price: string | number;
        vat: string | number;
        vat_rate_id: number | '';
        status: 'active' | 'inactive';
        notes: string;
        errors: Record<string, string>;
        processing: boolean;
    };
    vatRates: VatRateOption[];
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();

watch(
    () => props.form.vat_rate_id,
    (value) => {
        if (value === '') {
            return;
        }

        const selected = props.vatRates.find((rate) => rate.id === Number(value));
        if (selected) {
            props.form.vat = selected.rate;
        }
    },
);
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="reference">
                <FormItem>
                    <FormLabel>SKU</FormLabel>
                    <FormControl>
                        <Input v-model="form.reference" required placeholder="SKU do produto" />
                    </FormControl>
                    <FormMessage>{{ form.errors.reference }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="code">
                <FormItem>
                    <FormLabel>Código interno</FormLabel>
                    <FormControl>
                        <Input v-model="form.code" required placeholder="Codigo interno" />
                    </FormControl>
                    <FormMessage>{{ form.errors.code }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="name">
                <FormItem>
                    <FormLabel>Nome</FormLabel>
                    <FormControl>
                        <Input v-model="form.name" required placeholder="Nome do produto" />
                    </FormControl>
                    <FormMessage>{{ form.errors.name }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="status">
                <FormItem>
                    <FormLabel>Estado</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.status"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            required
                        >
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.status }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <FormField name="description">
            <FormItem>
                <FormLabel>Descrição</FormLabel>
                <FormControl>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        placeholder="Descricao curta"
                    />
                </FormControl>
                <FormMessage>{{ form.errors.description }}</FormMessage>
            </FormItem>
        </FormField>

        <div class="grid gap-4 md:grid-cols-3">
            <FormField name="price">
                <FormItem>
                    <FormLabel>Preço (EUR)</FormLabel>
                    <FormControl>
                        <Input v-model="form.price" type="number" min="0" step="0.01" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.price }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="vat">
                <FormItem>
                    <FormLabel>IVA (%)</FormLabel>
                    <FormControl>
                        <Input v-model="form.vat" type="number" min="0" max="100" step="0.01" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.vat }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="vat_rate_id">
                <FormItem>
                    <FormLabel>Taxa de IVA (opcional)</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.vat_rate_id"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        >
                            <option :value="''">Sem taxa associada</option>
                            <option v-for="vatRate in vatRates" :key="vatRate.id" :value="vatRate.id">
                                {{ vatRate.name }} ({{ vatRate.rate }}%)
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.vat_rate_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <FormField name="notes">
            <FormItem>
                <FormLabel>Notas</FormLabel>
                <FormControl>
                    <textarea
                        v-model="form.notes"
                        rows="4"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        placeholder="Notas internas"
                    />
                </FormControl>
                <FormMessage>{{ form.errors.notes }}</FormMessage>
            </FormItem>
        </FormField>

        <div class="flex gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel }}
            </Button>
            <Button type="button" variant="outline" as-child>
                <Link href="/items">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>

