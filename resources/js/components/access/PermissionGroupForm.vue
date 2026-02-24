<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

defineProps<{
    form: {
        name: string;
        menu_a_create: boolean;
        menu_a_read: boolean;
        menu_a_update: boolean;
        menu_a_delete: boolean;
        menu_b_create: boolean;
        menu_b_read: boolean;
        menu_b_update: boolean;
        menu_b_delete: boolean;
        status: string;
        errors: Record<string, string>;
        processing: boolean;
    };
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <FormField name="name">
            <FormItem>
                <FormLabel>Nome do Grupo</FormLabel>
                <FormControl>
                    <Input v-model="form.name" required />
                </FormControl>
                <FormMessage>{{ form.errors.name }}</FormMessage>
            </FormItem>
        </FormField>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-3 rounded-lg border p-4">
                <p class="text-sm font-semibold">Menu A</p>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model:checked="form.menu_a_create" />
                    Create
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model:checked="form.menu_a_read" />
                    Read
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model:checked="form.menu_a_update" />
                    Update
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model:checked="form.menu_a_delete" />
                    Delete
                </label>
            </div>

            <div class="space-y-3 rounded-lg border p-4">
                <p class="text-sm font-semibold">Menu B</p>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model:checked="form.menu_b_create" />
                    Create
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model:checked="form.menu_b_read" />
                    Read
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model:checked="form.menu_b_update" />
                    Update
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model:checked="form.menu_b_delete" />
                    Delete
                </label>
            </div>
        </div>

        <FormField name="status">
            <FormItem>
                <FormLabel>Estado</FormLabel>
                <FormControl>
                    <select
                        v-model="form.status"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </FormControl>
                <FormMessage>{{ form.errors.status }}</FormMessage>
            </FormItem>
        </FormField>

        <div class="flex gap-3">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
            <Button type="button" variant="outline" as-child>
                <Link href="/access/permission-groups">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
