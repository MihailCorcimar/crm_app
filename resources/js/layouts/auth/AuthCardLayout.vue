<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import { type AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const companyName = computed(() => {
    const name = String(page.props.company?.name ?? '').trim();

    return name === '' || name === 'Laravel' || name === 'Laravel Starter Kit'
        ? 'CRM'
        : name;
});
const companyLogoUrl = computed(() => page.props.company?.logo_url ?? null);

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10"
    >
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="flex items-center gap-2 self-center font-medium"
            >
                <div class="flex h-9 w-9 items-center justify-center">
                    <img
                        v-if="companyLogoUrl"
                        :src="companyLogoUrl"
                        alt="Company logo"
                        class="size-9 object-contain"
                    >
                    <AppLogoIcon
                        v-else
                        class="size-9 fill-current text-black dark:text-white"
                    />
                </div>
                <span>{{ companyName }}</span>
            </Link>

            <div class="flex flex-col gap-6">
                <Card class="rounded-xl">
                    <CardHeader class="px-10 pt-8 pb-0 text-center">
                        <CardTitle class="text-xl">{{ title }}</CardTitle>
                        <CardDescription>
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-10 py-8">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
