<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/Card.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { formatRupiah } from '../../../lib/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    history: Array,
    subscriptions: Array,
});

const selectedSubscription = ref('');

function createSimulation() {
    if (!selectedSubscription.value) {
        return;
    }

    router.post('/admin/payments', { subscription_id: selectedSubscription.value }, { preserveScroll: true });
    selectedSubscription.value = '';
}

function tone(status) {
    return status === 'success'
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
        : status === 'failed'
          ? 'bg-red-50 text-red-700 ring-red-600/20'
          : 'bg-amber-50 text-amber-700 ring-amber-600/20';
}
</script>

<template>
    <Head title="Admin - Payment Method" />

    <div class="space-y-5">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-white">Payment Method: QRIS Doku (Simulasi)</h1>
            <p class="mt-0.5 text-xs text-slate-400">
                Halaman demonstrasi. Tidak ada koneksi ke gateway pembayaran sungguhan -- kode QR dan status
                pembayaran hanya untuk memperlihatkan alur yang akan dijalankan integrasi Doku sebenarnya.
            </p>
        </div>

        <Card title="Buat Simulasi Pembayaran">
            <div class="flex flex-wrap items-end gap-3">
                <select
                    v-model="selectedSubscription"
                    class="min-w-52 flex-1 rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200"
                >
                    <option value="" disabled>Pilih tenant / langganan...</option>
                    <option v-for="s in subscriptions" :key="s.id" :value="s.id">
                        {{ s.group_name }} - {{ s.plan_name }} ({{ formatRupiah(s.price) }})
                    </option>
                </select>
                <button
                    type="button"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700"
                    @click="createSimulation"
                >
                    Buat QRIS
                </button>
            </div>
        </Card>

        <Card :padded="false" title="Riwayat Simulasi">
            <ul v-if="history.length" class="divide-y divide-slate-100">
                <li v-for="payment in history" :key="payment.id" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 text-sm font-medium text-slate-800">
                            {{ payment.group_name }} - {{ payment.plan_name }}
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset" :class="tone(payment.status)">
                                {{ payment.status_label }}
                            </span>
                        </p>
                        <p class="truncate text-xs text-slate-400">
                            {{ payment.reference }} - {{ payment.created_at }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
                        <p class="text-sm font-semibold text-slate-900">{{ formatRupiah(payment.amount) }}</p>
                        <div v-if="payment.status === 'pending'" class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                                @click="router.post(`/admin/payments/${payment.id}/mark-paid`, {}, { preserveScroll: true })"
                            >
                                Simulasikan Lunas
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200"
                                @click="router.post(`/admin/payments/${payment.id}/mark-failed`, {}, { preserveScroll: true })"
                            >
                                Simulasikan Gagal
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="Belum ada simulasi pembayaran" />
        </Card>
    </div>
</template>
