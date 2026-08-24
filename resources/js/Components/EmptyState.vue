<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center">
        <div class="grid size-11 place-items-center rounded-full bg-slate-100 text-slate-400">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5">
                <path d="M3 7h18M3 12h18M3 17h10" stroke-linecap="round" />
            </svg>
        </div>
        <p class="text-sm font-medium text-slate-700">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-xs text-slate-500">{{ description }}</p>
        <div class="mt-1"><slot /></div>
    </div>
</template>
