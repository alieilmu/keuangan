<script setup>
import { computed } from 'vue';
import { budgetStyle } from '../lib/budget';
import { formatPercent, formatRupiah } from '../lib/format';

const props = defineProps({
    budget: { type: Object, required: true },
    compact: { type: Boolean, default: false },
});

const style = computed(() => budgetStyle(props.budget.status));
</script>

<template>
    <div class="py-3">
        <div class="flex items-baseline justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
                <span
                    class="size-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: budget.category_color || '#94a3b8' }"
                />
                <p class="truncate text-sm font-medium text-slate-800">{{ budget.category_name }}</p>
            </div>

            <p :class="style.text" class="shrink-0 text-xs font-semibold tabular-nums">
                {{ formatPercent(budget.percentage) }}
            </p>
        </div>

        <div :class="style.track" class="mt-2 h-2 w-full overflow-hidden rounded-full">
            <div
                :class="style.bar"
                class="h-full rounded-full transition-[width] duration-500 ease-out"
                :style="{ width: `${budget.bar_width}%` }"
            />
        </div>

        <div class="mt-1.5 flex items-center justify-between gap-3 text-xs">
            <p class="truncate tabular-nums text-slate-500">
                {{ formatRupiah(budget.spent) }}
                <span class="text-slate-400">/ {{ formatRupiah(budget.limit_amount) }}</span>
            </p>

            <p v-if="!compact" :class="style.text" class="shrink-0 font-medium">
                <span v-if="budget.remaining >= 0">sisa {{ formatRupiah(budget.remaining) }}</span>
                <span v-else>lebih {{ formatRupiah(Math.abs(budget.remaining)) }}</span>
            </p>
        </div>
    </div>
</template>
