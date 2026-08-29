<script setup>
import { computed } from 'vue';
import { formatRupiah } from '../lib/format';

const props = defineProps({
    goal: { type: Object, required: true },
});

const tone = computed(() => {
    if (props.goal.status === 'completed' || props.goal.progress_percentage >= 100) {
        return 'bg-emerald-500';
    }

    return props.goal.progress_percentage >= 50 ? 'bg-emerald-400' : 'bg-sky-400';
});
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between gap-3">
            <p class="truncate text-sm font-semibold text-slate-900">{{ goal.name }}</p>
            <p class="shrink-0 text-xs tabular-nums text-slate-400">
                {{ formatRupiah(goal.saved_amount) }} / {{ formatRupiah(goal.target_amount) }}
            </p>
        </div>

        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
            <div
                class="h-full rounded-full transition-all duration-500"
                :class="tone"
                :style="{ width: `${goal.bar_width}%` }"
            />
        </div>

        <p class="mt-1.5 text-xs text-slate-500">
            {{ goal.progress_label }}
        </p>

        <!-- Alokasi akun ganda -->
        <p class="mt-1 truncate text-[11px] text-slate-400">
            {{ goal.source_account }} &rarr; {{ goal.storage_account }}
        </p>
    </div>
</template>
