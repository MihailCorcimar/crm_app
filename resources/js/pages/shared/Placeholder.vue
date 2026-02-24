<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
    }>(),
    {
        description: 'Modulo em desenvolvimento.',
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.title,
        href: '#',
    },
];
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>{{ props.title }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">{{ props.description }}</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
