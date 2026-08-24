<script setup>
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-slate-600">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </span>

        <slot />

        <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
        <span v-else-if="hint" class="mt-1 block text-xs text-slate-400">{{ hint }}</span>
    </label>
</template>
