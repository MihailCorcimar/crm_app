<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type SelectOption = {
    id: number;
    name: string;
};

type StageOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    form: {
        entity_id: number | '';
        title: string;
        stage: string;
        value: string | number;
        probability: string | number;
        expected_close_date: string;
        owner_id: number | '';
        errors: Record<string, string>;
        processing: boolean;
    };
    entities: SelectOption[];
    owners: SelectOption[];
    stageOptions: StageOption[];
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="title">
                <FormItem>
                    <FormLabel>Título</FormLabel>
                    <FormControl>
                        <Input v-model="form.title" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.title }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="entity_id">
                <FormItem>
                    <FormLabel>Entidade</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.entity_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        >
                            <option :value="''">Sem entidade associada</option>
                            <option v-for="entity in entities" :key="entity.id" :value="entity.id">
                                {{ entity.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.entity_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="stage">
                <FormItem>
                    <FormLabel>Etapa</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.stage"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            required
                        >
                            <option v-for="option in stageOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.stage }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="owner_id">
                <FormItem>
                    <FormLabel>Responsável</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.owner_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            required
                        >
                            <option :value="''" disabled>Selecionar responsável</option>
                            <option v-for="owner in owners" :key="owner.id" :value="owner.id">
                                {{ owner.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.owner_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <FormField name="value">
                <FormItem>
                    <FormLabel>Valor (EUR)</FormLabel>
                    <FormControl>
                        <Input v-model="form.value" type="number" min="0" step="0.01" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.value }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="probability">
                <FormItem>
                    <FormLabel>Probabilidade (%)</FormLabel>
                    <FormControl>
                        <Input v-model="form.probability" type="number" min="0" max="100" step="1" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.probability }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="expected_close_date">
                <FormItem>
                    <FormLabel>Data prevista de fecho</FormLabel>
                    <FormControl>
                        <Input v-model="form.expected_close_date" type="date" />
                    </FormControl>
                    <FormMessage>{{ form.errors.expected_close_date }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="flex gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel }}
            </Button>
            <Button type="button" variant="outline" as-child>
                <Link href="/deals">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
