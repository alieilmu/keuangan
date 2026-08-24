<script setup>
import { computed } from 'vue';
import { formatRupiah, formatDate } from '../lib/format';

const props = defineProps({
    credit: { type: Object, required: true },
    compact: { type: Boolean, default: false },
});

const done = computed(() => props.credit.remaining_months === 0);
</script>

<template>
    <div class="py-3">
        <div class="flex items-baseline justify-between gap-3">
            <p class="truncate text-sm font-medium text-slate-800">{{ credit.name }}</p>

            <p
                class="shrink-0 text-xs font-semibold tabular-nums"
                :class="done ? 'text-emerald-600' : 'text-slate-600'"
            >
                {{ credit.progress_percentage }}%
            </p>
        </div>

        <!-- "Bulan ke-12 dari 36" -->
        <p class="mt-0.5 text-xs text-slate-500">
            {{ credit.progress_label }}
            <span v-if="!done && credit.next_due_date" class="text-slate-400">
                - jatuh tempo {{ formatDate(credit.next_due_date) }}
            </span>
        </p>

        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div
                class="h-full rounded-full transition-[width] duration-500 ease-out"
                :class="done ? 'bg-emerald-500' : 'bg-sky-500'"
                :style="{ width: `${Math.min(100, credit.progress_percentage)}%` }"
            />
        </div>

        <div class="mt-1.5 flex items-center justify-between gap-3 text-xs">
            <p class="truncate tabular-nums text-slate-500">
                {{ formatRupiah(credit.monthly_installment) }}<span class="text-slate-400">/bulan</span>
            </p>

            <p v-if="!compact" class="shrink-0 tabular-nums text-slate-500">
                <span v-if="done" class="font-medium text-emerald-600">Lunas</span>
                <span v-else>sisa {{ formatRupiah(credit.outstanding) }}</span>
            </p>
        </div>
    </div>
</template>
