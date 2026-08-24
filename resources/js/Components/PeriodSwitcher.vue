<script setup>
import { router } from '@inertiajs/vue3';
import { formatPeriod, shiftPeriod } from '../lib/format';

const props = defineProps({
    period: { type: String, required: true },
    only: { type: Array, default: () => [] },
});

function go(delta) {
    router.get(
        window.location.pathname,
        { ...currentQuery(), period: shiftPeriod(props.period, delta) },
        { preserveScroll: true, preserveState: true, only: props.only.length ? props.only : undefined },
    );
}

function currentQuery() {
    return Object.fromEntries(new URLSearchParams(window.location.search));
}
</script>

<template>
    <div class="inline-flex items-center gap-1 rounded-xl bg-white p-1 ring-1 ring-slate-200">
        <button
            type="button"
            class="grid size-7 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100"
            aria-label="Bulan sebelumnya"
            @click="go(-1)"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4">
                <path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        <span class="min-w-[7.5rem] text-center text-xs font-medium text-slate-700">
            {{ formatPeriod(period) }}
        </span>

        <button
            type="button"
            class="grid size-7 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100"
            aria-label="Bulan berikutnya"
            @click="go(1)"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4">
                <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </div>
</template>
