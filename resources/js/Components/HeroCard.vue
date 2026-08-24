<script setup>
import { computed } from 'vue';
import { formatRupiah } from '../lib/format';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: Number, required: true },
    tone: { type: String, default: 'neutral' }, // neutral | income | expense
    caption: { type: String, default: null },
});

const tones = {
    neutral: {
        wrap: 'bg-slate-900 text-white ring-slate-900',
        label: 'text-slate-300',
        value: 'text-white',
        icon: 'bg-white/10 text-emerald-300',
        caption: 'text-slate-400',
    },
    income: {
        wrap: 'bg-white ring-slate-200/70',
        label: 'text-slate-500',
        value: 'text-emerald-600',
        icon: 'bg-emerald-50 text-emerald-600',
        caption: 'text-slate-400',
    },
    expense: {
        wrap: 'bg-white ring-slate-200/70',
        label: 'text-slate-500',
        value: 'text-slate-900',
        icon: 'bg-red-50 text-red-500',
        caption: 'text-slate-400',
    },
};

const style = computed(() => tones[props.tone] ?? tones.neutral);
</script>

<template>
    <div :class="style.wrap" class="rounded-2xl p-4 shadow-sm ring-1 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <p :class="style.label" class="text-xs font-medium">{{ label }}</p>
            <span :class="style.icon" class="grid size-8 shrink-0 place-items-center rounded-xl">
                <slot name="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                        <path d="M3 7h18v10H3z" stroke-linejoin="round" />
                        <path d="M16 12h.01" stroke-linecap="round" />
                    </svg>
                </slot>
            </span>
        </div>

        <p :class="style.value" class="mt-3 truncate text-xl font-semibold tracking-tight sm:text-2xl">
            {{ formatRupiah(value) }}
        </p>
        <p v-if="caption" :class="style.caption" class="mt-1 truncate text-xs">{{ caption }}</p>
    </div>
</template>
