<script setup>
import { computed } from 'vue';
import { formatRupiah } from '../lib/format';

const props = defineProps({
    bills: { type: Array, default: () => [] },
});

defineEmits(['pay']);

function urgency(bill) {
    if (bill.is_overdue) {
        return { chip: 'bg-red-50 text-red-700 ring-red-600/20', label: `Telat ${Math.abs(bill.days_left)} hari` };
    }

    if (bill.days_left === 0) {
        return { chip: 'bg-amber-50 text-amber-700 ring-amber-600/20', label: 'Jatuh tempo hari ini' };
    }

    if (bill.is_due_soon) {
        return { chip: 'bg-amber-50 text-amber-700 ring-amber-600/20', label: `${bill.days_left} hari lagi` };
    }

    return { chip: 'bg-slate-100 text-slate-600 ring-slate-500/15', label: `${bill.days_left} hari lagi` };
}

const total = computed(() => props.bills.reduce((sum, bill) => sum + Number(bill.amount || 0), 0));
</script>

<template>
    <div>
        <!-- Swipe horizontal dengan snap; di desktop tetap bisa di-scroll. -->
        <div class="no-scrollbar -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
            <article
                v-for="bill in bills"
                :key="bill.id"
                class="flex w-[15.5rem] shrink-0 snap-start flex-col justify-between rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70"
            >
                <div>
                    <div class="flex items-start justify-between gap-2">
                        <p class="min-w-0 truncate text-sm font-semibold text-slate-900">{{ bill.title }}</p>
                        <span
                            class="size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: bill.category_color || '#cbd5e1' }"
                        />
                    </div>

                    <p class="mt-2 text-lg font-semibold tracking-tight text-slate-900">
                        {{ formatRupiah(bill.amount) }}
                    </p>

                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
                            :class="urgency(bill).chip"
                        >
                            {{ urgency(bill).label }}
                        </span>
                        <span class="text-[11px] text-slate-400">{{ bill.due_label }}</span>
                    </div>
                </div>

                <button
                    type="button"
                    class="mt-4 w-full rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 active:scale-[0.98]"
                    @click="$emit('pay', bill)"
                >
                    Bayar Sekarang
                </button>
            </article>
        </div>

        <p v-if="bills.length" class="mt-3 text-xs text-slate-500">
            {{ bills.length }} tagihan belum dibayar - total
            <span class="font-medium text-slate-700">{{ formatRupiah(total) }}</span>
        </p>
    </div>
</template>
