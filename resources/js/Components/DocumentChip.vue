<script setup>
defineProps({
    document: { type: Object, required: true },
    tone: { type: String, default: 'slate' },
});

const TONES = {
    slate: 'bg-slate-100 text-slate-600 hover:bg-slate-200',
    emerald: 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
    sky: 'bg-sky-50 text-sky-700 hover:bg-sky-100',
};
</script>

<template>
    <a
        :href="document.url"
        target="_blank"
        rel="noopener"
        class="inline-flex max-w-full items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-medium transition"
        :class="TONES[tone] ?? TONES.slate"
        :title="`${document.kind_label}: ${document.name} (${document.size_label})`"
    >
        <svg
            v-if="document.is_image"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            class="size-3.5 shrink-0"
        >
            <path d="M3 5h18v14H3zM3 16l5-5 4 4 3-3 6 6" stroke-linejoin="round" />
        </svg>
        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-3.5 shrink-0">
            <path d="M14 3v5h5M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" stroke-linejoin="round" />
        </svg>

        <span class="truncate"><slot>{{ document.kind_label }}</slot></span>
    </a>
</template>
