<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { type AppPageProps } from '@/types';

const page = usePage<AppPageProps>();

const companyName = computed(() => {
    const name = String(page.props.company?.name ?? '').trim();

    return name === '' || name === 'Laravel' || name === 'Laravel Starter Kit'
        ? 'App de Gestao'
        : name;
});

const companyLogoUrl = computed(() => page.props.company?.logo_url ?? null);
const tenantPrimaryColor = computed(() => page.props.tenant?.active?.brand_primary_color ?? '#1F2937');

function parseHexColor(hex: string): { r: number; g: number; b: number } | null {
    const normalized = hex.trim();

    if (!/^#[0-9A-Fa-f]{6}$/.test(normalized)) {
        return null;
    }

    return {
        r: parseInt(normalized.slice(1, 3), 16),
        g: parseInt(normalized.slice(3, 5), 16),
        b: parseInt(normalized.slice(5, 7), 16),
    };
}

function preferredForeground(hex: string): string {
    const rgb = parseHexColor(hex);

    if (rgb === null) {
        return '#FFFFFF';
    }

    const brightness = ((rgb.r * 299) + (rgb.g * 587) + (rgb.b * 114)) / 1000;

    return brightness >= 140 ? '#111827' : '#FFFFFF';
}

const logoStyle = computed(() => ({
    backgroundColor: tenantPrimaryColor.value,
    color: preferredForeground(tenantPrimaryColor.value),
}));
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md"
        :style="logoStyle"
    >
        <img
            v-if="companyLogoUrl"
            :src="companyLogoUrl"
            alt="Company logo"
            class="size-7 object-contain"
        >
        <AppLogoIcon v-else class="size-5 fill-current" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{ companyName }}</span>
    </div>
</template>
