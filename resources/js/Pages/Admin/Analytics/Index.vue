<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/Card.vue';
import { formatNumber, formatPercent, formatRupiah } from '../../../lib/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    summary: Object,
    growth: Array,
});

const maxCumulative = computed(() => Math.max(...props.growth.map((g) => g.cumulative), 1));
</script>

<template>
    <Head title="Admin - Business Analytics" />

    <div class="space-y-5">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-white">Business Analytics</h1>
            <p class="mt-0.5 text-xs text-slate-400">Dihitung langsung dari data produksi saat ini.</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <p class="text-xs text-slate-500">MRR (Monthly Recurring Revenue)</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ formatRupiah(summary.mrr) }}</p>
            </Card>
            <Card>
                <p class="text-xs text-slate-500">Churn Rate</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ formatPercent(summary.churn_rate) }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">Langganan berbayar hilang bulan ini</p>
            </Card>
            <Card>
                <p class="text-xs text-slate-500">Total User</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ formatNumber(summary.total_users) }}</p>
            </Card>
            <Card>
                <p class="text-xs text-slate-500">Tenant Berbayar</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ summary.paying_tenants }} / {{ summary.total_tenants }}</p>
            </Card>
        </div>

        <Card title="Pertumbuhan User" subtitle="6 bulan terakhir">
            <div class="flex h-48 items-end gap-3 sm:gap-4">
                <div v-for="point in growth" :key="point.month" class="flex flex-1 flex-col items-center gap-2">
                    <div class="flex h-36 w-full items-end justify-center">
                        <div
                            class="w-full max-w-8 rounded-t-md bg-emerald-500 transition-all"
                            :style="{ height: `${Math.max((point.cumulative / maxCumulative) * 100, 3)}%` }"
                            :title="`${point.cumulative} total user`"
                        />
                    </div>
                    <p class="text-[11px] font-medium text-slate-500">{{ point.label }}</p>
                    <p class="text-[11px] text-slate-400">+{{ point.new_users }}</p>
                </div>
            </div>
        </Card>
    </div>
</template>
