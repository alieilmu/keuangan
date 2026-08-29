<script setup>
import { router } from '@inertiajs/vue3';

/**
 * Pemilih tampilan kas bersama: Gabungan / Punya Saya / anggota lain.
 * Disembunyikan bila user hanya sendirian di group-nya (opsi < 2).
 */
const props = defineProps({
    scope: { type: String, default: 'all' },
    options: { type: Array, default: () => [] },
});

function pick(value) {
    router.get(
        window.location.pathname,
        { ...currentQuery(), scope: value },
        { preserveScroll: true, preserveState: true },
    );
}

function currentQuery() {
    return Object.fromEntries(new URLSearchParams(window.location.search));
}
</script>

<template>
    <div
        v-if="options.length > 1"
        class="inline-flex items-center gap-1 rounded-xl bg-white p-1 ring-1 ring-slate-200"
        role="group"
        aria-label="Tampilkan data milik"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
            :class="
                (props.scope || 'all') === option.value
                    ? 'bg-slate-900 text-white'
                    : 'text-slate-500 hover:bg-slate-100'
            "
            :aria-pressed="(props.scope || 'all') === option.value"
            @click="pick(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
