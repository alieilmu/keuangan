<script setup>
import { computed, ref } from 'vue';
import { formatPercent, formatRupiah } from '../lib/format';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const RADIUS = 60;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const active = ref(null);

const total = computed(() => props.items.reduce((sum, item) => sum + Number(item.total || 0), 0));

/** Ubah tiap kategori menjadi satu segmen donut (stroke-dasharray). */
const segments = computed(() => {
    let offset = 0;

    return props.items.map((item) => {
        const fraction = total.value > 0 ? Number(item.total) / total.value : 0;
        const length = fraction * CIRCUMFERENCE;
        const segment = {
            ...item,
            dash: `${Math.max(length - 1.5, 0)} ${CIRCUMFERENCE - Math.max(length - 1.5, 0)}`,
            offset: -offset,
        };

        offset += length;

        return segment;
    });
});

const highlighted = computed(() => active.value ?? null);
</script>

<template>
    <div v-if="items.length" class="flex flex-col items-center gap-5 sm:flex-row sm:items-center sm:gap-6">
        <div class="relative shrink-0">
            <svg viewBox="0 0 160 160" class="size-40 -rotate-90">
                <circle cx="80" cy="80" :r="RADIUS" fill="none" stroke="#f1f5f9" stroke-width="22" />
                <circle
                    v-for="segment in segments"
                    :key="segment.category_id ?? segment.name"
                    cx="80"
                    cy="80"
                    :r="RADIUS"
                    fill="none"
                    :stroke="segment.color"
                    stroke-width="22"
                    :stroke-dasharray="segment.dash"
                    :stroke-dashoffset="segment.offset"
                    class="cursor-pointer transition-opacity duration-200"
                    :class="highlighted && highlighted.name !== segment.name ? 'opacity-30' : 'opacity-100'"
                    @mouseenter="active = segment"
                    @mouseleave="active = null"
                />
            </svg>

            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                <p class="text-[11px] font-medium text-slate-400">
                    {{ highlighted ? highlighted.name : 'Total' }}
                </p>
                <p class="mt-0.5 max-w-[7rem] truncate text-sm font-semibold text-slate-900">
                    {{ formatRupiah(highlighted ? highlighted.total : total) }}
                </p>
            </div>
        </div>

        <ul class="w-full min-w-0 space-y-2">
            <li
                v-for="segment in segments.slice(0, 6)"
                :key="segment.category_id ?? segment.name"
                class="flex items-center gap-2.5 text-sm"
                @mouseenter="active = segment"
                @mouseleave="active = null"
            >
                <span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: segment.color }" />
                <span class="min-w-0 flex-1 truncate text-slate-600">{{ segment.name }}</span>
                <span class="shrink-0 tabular-nums text-xs text-slate-400">{{ formatPercent(segment.percentage) }}</span>
                <span class="shrink-0 tabular-nums text-xs font-medium text-slate-700">
                    {{ formatRupiah(segment.total) }}
                </span>
            </li>
        </ul>
    </div>

    <slot v-else name="empty" />
</template>
