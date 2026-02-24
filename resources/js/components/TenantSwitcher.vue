<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { type AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const activeTenant = computed(() => page.props?.tenant?.active ?? null);
const availableTenants = computed(() => Array.isArray(page.props?.tenant?.available)
    ? page.props.tenant.available
    : []);
const activeTenantColor = computed(() => activeTenant.value?.brand_primary_color ?? '#1F2937');
const selectedSlug = ref('');
const rememberPreference = ref(true);

watch(
    () => activeTenant.value?.slug,
    (activeSlug) => {
        selectedSlug.value = activeSlug ?? '';
    },
    { immediate: true },
);

function switchTenant(): void {
    if (
        selectedSlug.value === ''
        || selectedSlug.value === activeTenant.value?.slug
    ) {
        return;
    }

    router.post(
        `/tenants/${selectedSlug.value}/switch`,
        {
            remember: rememberPreference.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}
</script>

<template>
    <div class="ml-auto flex items-center gap-3">
        <div class="hidden items-center gap-2 text-xs text-muted-foreground md:flex">
            <span
                class="inline-block size-2 rounded-full border border-black/15"
                :style="{ backgroundColor: activeTenantColor }"
            />
            Tenant ativo:
            <span class="font-medium text-foreground">
                {{ activeTenant?.name ?? 'Nenhum' }}
            </span>
        </div>

        <template v-if="availableTenants.length > 0">
            <select
                v-model="selectedSlug"
                class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring h-9 rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                :style="{ borderColor: activeTenantColor }"
                @change="switchTenant"
            >
                <option
                    v-for="tenant in availableTenants"
                    :key="tenant.id"
                    :value="tenant.slug"
                >
                    {{ tenant.name }}
                </option>
            </select>

            <label class="hidden items-center gap-2 text-xs text-muted-foreground md:flex">
                <input
                    v-model="rememberPreference"
                    type="checkbox"
                >
                Relembrar
            </label>
        </template>

        <Link
            v-else
            href="/tenants/create"
            class="text-sm underline-offset-4 hover:underline"
        >
            Criar tenant
        </Link>
    </div>
</template>
