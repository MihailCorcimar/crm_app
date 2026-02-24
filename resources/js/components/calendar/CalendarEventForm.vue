<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type SelectOption = {
    id: number;
    name: string;
};

defineProps<{
    form: {
        event_date: string;
        event_time: string;
        duration_minutes: number;
        share: string;
        knowledge: string;
        entity_id: number | '';
        user_id: number | '';
        calendar_type_id: number | '';
        calendar_action_id: number | '';
        description: string;
        status: string;
        errors: Record<string, string>;
        processing: boolean;
    };
    users: SelectOption[];
    entities: SelectOption[];
    types: SelectOption[];
    actions: SelectOption[];
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-3">
            <FormField name="event_date">
                <FormItem>
                    <FormLabel>Data</FormLabel>
                    <FormControl>
                        <Input v-model="form.event_date" type="date" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.event_date }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="event_time">
                <FormItem>
                    <FormLabel>Hora</FormLabel>
                    <FormControl>
                        <Input v-model="form.event_time" type="time" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.event_time }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="duration_minutes">
                <FormItem>
                    <FormLabel>Duracao (min)</FormLabel>
                    <FormControl>
                        <Input v-model.number="form.duration_minutes" type="number" min="5" step="5" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.duration_minutes }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="share">
                <FormItem>
                    <FormLabel>Partilha</FormLabel>
                    <FormControl>
                        <Input v-model="form.share" />
                    </FormControl>
                    <FormMessage>{{ form.errors.share }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="knowledge">
                <FormItem>
                    <FormLabel>Conhecimento</FormLabel>
                    <FormControl>
                        <Input v-model="form.knowledge" />
                    </FormControl>
                    <FormMessage>{{ form.errors.knowledge }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="entity_id">
                <FormItem>
                    <FormLabel>Entidade</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.entity_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Selecionar entidade</option>
                            <option v-for="entity in entities" :key="entity.id" :value="entity.id">
                                {{ entity.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.entity_id }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="user_id">
                <FormItem>
                    <FormLabel>Utilizador</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.user_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Selecionar utilizador</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.user_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="calendar_type_id">
                <FormItem>
                    <FormLabel>Tipo</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.calendar_type_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Selecionar tipo</option>
                            <option v-for="type in types" :key="type.id" :value="type.id">
                                {{ type.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.calendar_type_id }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="calendar_action_id">
                <FormItem>
                    <FormLabel>Acao</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.calendar_action_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Selecionar acao</option>
                            <option v-for="action in actions" :key="action.id" :value="action.id">
                                {{ action.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.calendar_action_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <FormField name="description">
            <FormItem>
                <FormLabel>Descricao</FormLabel>
                <FormControl>
                    <textarea
                        v-model="form.description"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-24 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                </FormControl>
                <FormMessage>{{ form.errors.description }}</FormMessage>
            </FormItem>
        </FormField>

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
                <FormDescription>Permite controlar visibilidade da atividade.</FormDescription>
                <FormMessage>{{ form.errors.status }}</FormMessage>
            </FormItem>
        </FormField>

        <div class="flex gap-3">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
            <Button type="button" variant="outline" as-child>
                <Link href="/calendar">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
