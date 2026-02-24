<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type PermissionGroupOption = {
    id: number;
    name: string;
};

defineProps<{
    form: {
        name: string;
        email: string;
        mobile: string;
        permission_group_id: number | '';
        status: string;
        errors: Record<string, string>;
        processing: boolean;
    };
    permissionGroups: PermissionGroupOption[];
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="name">
                <FormItem>
                    <FormLabel>Nome</FormLabel>
                    <FormControl>
                        <Input v-model="form.name" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.name }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="email">
                <FormItem>
                    <FormLabel>Email</FormLabel>
                    <FormControl>
                        <Input v-model="form.email" type="email" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.email }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="mobile">
                <FormItem>
                    <FormLabel>Telemovel</FormLabel>
                    <FormControl>
                        <Input
                            v-model="form.mobile"
                            inputmode="tel"
                            maxlength="14"
                            pattern="^(\\+351 ?)?9[0-9]{8}$"
                            placeholder="912345678 ou +351 912345678"
                        />
                    </FormControl>
                    <FormDescription>Formato PT: numero movel (9 digitos, opcional +351).</FormDescription>
                    <FormMessage>{{ form.errors.mobile }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="permission_group_id">
                <FormItem>
                    <FormLabel>Grupo de Permissoes</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.permission_group_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Selecionar grupo</option>
                            <option
                                v-for="permissionGroup in permissionGroups"
                                :key="permissionGroup.id"
                                :value="permissionGroup.id"
                            >
                                {{ permissionGroup.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.permission_group_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="status">
                <FormItem>
                    <FormLabel>Estado</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.status"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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

        <div class="flex gap-3">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
            <Button type="button" variant="outline" as-child>
                <Link href="/access/users">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
