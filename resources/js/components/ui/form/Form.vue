<script setup lang="ts">
import { Form as VeeForm } from 'vee-validate';
</script>

<template>
    <VeeForm v-slot="slotProps">
        <slot v-bind="slotProps" />
    </VeeForm>
</template>
