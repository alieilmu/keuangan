<script setup>
defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <header
            v-if="title || $slots.actions"
            class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3.5 sm:px-5"
        >
            <div class="min-w-0">
                <h2 class="truncate text-sm font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="subtitle" class="mt-0.5 truncate text-xs text-slate-500">{{ subtitle }}</p>
            </div>
            <div class="shrink-0"><slot name="actions" /></div>
        </header>

        <div :class="padded ? 'px-4 py-4 sm:px-5' : ''">
            <slot />
        </div>
    </section>
</template>
