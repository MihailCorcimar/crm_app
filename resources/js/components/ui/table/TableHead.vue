<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <th
        :class="
            cn(
                'h-11 px-3 text-left align-middle text-[11px] font-semibold tracking-wide text-zinc-600 uppercase whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]',
                props.class,
            )
        "
    >
        <slot />
    </th>
</template>
