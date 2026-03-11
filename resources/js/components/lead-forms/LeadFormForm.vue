<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type FieldSchemaRow = {
    key: 'full_name' | 'email' | 'phone' | 'company' | 'message';
    label: string;
    type: 'text' | 'email' | 'tel' | 'textarea';
    enabled: boolean;
    required: boolean;
};

const props = defineProps<{
    form: {
        name: string;
        slug: string;
        status: 'active' | 'inactive';
        requires_captcha: boolean;
        confirmation_message: string;
        field_schema: FieldSchemaRow[];
        errors: Record<string, string>;
        processing: boolean;
    };
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();

function onNameChanged(): void {
    if (props.form.slug.trim() !== '') {
        return;
    }

    props.form.slug = props.form.name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 140);
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-1">
                <label class="text-sm font-medium">Nome do formulario</label>
                <Input v-model="form.name" required maxlength="120" @input="onNameChanged" />
                <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div class="grid gap-1">
                <label class="text-sm font-medium">Slug</label>
                <Input v-model="form.slug" required maxlength="140" placeholder="formulario-leads-site" />
                <p class="text-xs text-muted-foreground">Usado para identificar o formulario internamente.</p>
                <p v-if="form.errors.slug" class="text-sm text-red-600">{{ form.errors.slug }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-1">
                <label class="text-sm font-medium">Estado</label>
                <select
                    v-model="form.status"
                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                    required
                >
                    <option value="active">Ativo</option>
                    <option value="inactive">Inativo</option>
                </select>
                <p v-if="form.errors.status" class="text-sm text-red-600">{{ form.errors.status }}</p>
            </div>

            <label class="mt-6 inline-flex items-center gap-2 text-sm font-medium">
                <input v-model="form.requires_captcha" type="checkbox">
                Captcha obrigatorio
            </label>
        </div>

        <div class="grid gap-1">
            <label class="text-sm font-medium">Mensagem de confirmacao</label>
            <textarea
                v-model="form.confirmation_message"
                rows="3"
                class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                required
                maxlength="1000"
            />
            <p v-if="form.errors.confirmation_message" class="text-sm text-red-600">{{ form.errors.confirmation_message }}</p>
        </div>

        <div class="space-y-3">
            <h3 class="text-sm font-semibold">Campos configuraveis</h3>
            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Campo</th>
                            <th class="px-3 py-2 text-left font-medium">Etiqueta</th>
                            <th class="px-3 py-2 text-center font-medium">Ativo</th>
                            <th class="px-3 py-2 text-center font-medium">Obrigatorio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(field, index) in form.field_schema" :key="field.key" class="border-t">
                            <td class="px-3 py-2 font-medium">{{ field.key }}</td>
                            <td class="px-3 py-2">
                                <Input v-model="form.field_schema[index].label" maxlength="60" required />
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input v-model="form.field_schema[index].enabled" type="checkbox">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input
                                    v-model="form.field_schema[index].required"
                                    type="checkbox"
                                    :disabled="!form.field_schema[index].enabled"
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="form.errors.field_schema" class="text-sm text-red-600">{{ form.errors.field_schema }}</p>
        </div>

        <div class="flex gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel }}
            </Button>
            <Button type="button" variant="outline" as-child>
                <Link href="/lead-forms">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>

