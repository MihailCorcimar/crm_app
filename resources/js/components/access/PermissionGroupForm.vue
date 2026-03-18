<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type PermissionAction = 'create' | 'read' | 'update' | 'delete';

type PermissionFormState = {
    name: string;
    status: string;
    errors: Record<string, string>;
    processing: boolean;
} & Record<string, unknown>;

const props = defineProps<{
    form: PermissionFormState;
    permissionModules: Record<string, string>;
    permissionActions: Record<PermissionAction, string>;
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();

function permissionKey(module: string, action: PermissionAction): string {
    return `${module}_${action}`;
}

function isChecked(key: string): boolean {
    return Boolean(props.form[key]);
}

function setChecked(key: string, value: boolean | 'indeterminate'): void {
    props.form[key] = value === true;
}

function humanLabel(module: string): string {
    return props.permissionModules[module] ?? module;
}
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
            <div
                v-for="module in Object.keys(permissionModules)"
                :key="module"
                class="space-y-3 rounded-lg border p-4"
            >
                <p class="text-sm font-semibold">{{ humanLabel(module) }}</p>
                <label
                    v-for="(actionLabel, action) in permissionActions"
                    :key="permissionKey(module, action as PermissionAction)"
                    class="flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        :checked="isChecked(permissionKey(module, action as PermissionAction))"
                        @update:checked="(value) => setChecked(permissionKey(module, action as PermissionAction), value)"
                    />
                    {{ actionLabel }}
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
