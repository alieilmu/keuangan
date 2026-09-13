<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/Card.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { formatRupiah } from '../../../lib/format';

defineOptions({ layout: AdminLayout });

defineProps({
    orders: Object,
    pending_count: Number,
});

const tone = (status) =>
    status === 'paid'
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
        : status === 'pending'
          ? 'bg-amber-50 text-amber-700 ring-amber-600/20'
          : 'bg-slate-100 text-slate-500 ring-slate-300';

function confirmPaid(order) {
    if (!confirm(`Konfirmasi pembayaran ${order.reference} sebesar ${formatRupiah(order.total)}? Langganan grup akan langsung diperbarui.`)) {
        return;
    }

    router.post(`/admin/orders/${order.id}/confirm`, {}, { preserveScroll: true });
}

function cancel(order) {
    if (!confirm(`Batalkan pesanan ${order.reference}?`)) {
        return;
    }

    router.post(`/admin/orders/${order.id}/cancel`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin - Pesanan Langganan" />

    <div class="space-y-5">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-white">Pesanan Langganan</h1>
            <p class="mt-0.5 text-xs text-slate-400">
                {{ pending_count }} menunggu konfirmasi. Pembayaran masih simulasi QRIS &mdash; konfirmasi di sini
                menggantikan notifikasi otomatis dari gateway pembayaran.
            </p>
        </div>

        <Card :padded="false">
            <ul v-if="orders.data.length" class="divide-y divide-slate-100">
                <li v-for="order in orders.data" :key="order.id" class="space-y-2 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs font-semibold text-slate-700">{{ order.reference }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset" :class="tone(order.status)">{{ order.status_label }}</span>
                        <span class="ml-auto text-[11px] text-slate-400">{{ order.created_label }}</span>
                    </div>
                    <p class="text-sm font-medium text-slate-800">
                        {{ order.group_name }} &mdash; {{ order.description }}
                        <span class="text-xs font-normal text-slate-400">oleh {{ order.user_name ?? 'akun terhapus' }}</span>
                    </p>
                    <ul class="space-y-0.5 text-xs text-slate-500">
                        <li v-for="(line, i) in order.lines" :key="i" class="flex justify-between gap-3">
                            <span class="min-w-0">{{ line.label }}</span>
                            <span class="shrink-0 tabular-nums" :class="line.amount < 0 ? 'text-emerald-600' : ''">
                                {{ line.amount < 0 ? '-' : '' }}{{ formatRupiah(Math.abs(line.amount)) }}
                            </span>
                        </li>
                    </ul>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2">
                        <p class="text-sm font-bold tabular-nums text-slate-900">
                            Total {{ formatRupiah(order.total) }}
                            <span v-if="order.paid_label" class="text-[11px] font-normal text-slate-400"> - lunas {{ order.paid_label }}</span>
                        </p>
                        <div v-if="order.status === 'pending'" class="flex gap-1.5">
                            <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700" @click="confirmPaid(order)">
                                Konfirmasi Lunas
                            </button>
                            <button type="button" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200" @click="cancel(order)">
                                Batalkan
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="Belum ada pesanan" description="Pesanan dari halaman Langganan pengguna akan muncul di sini." />
        </Card>

        <div v-if="orders.links.length > 3" class="flex flex-wrap gap-1.5">
            <Link
                v-for="link in orders.links"
                :key="link.label"
                :href="link.url ?? '#'"
                v-html="link.label"
                class="rounded-lg px-3 py-1.5 text-xs font-medium"
                :class="[link.active ? 'bg-emerald-500 text-slate-900' : 'bg-white/5 text-slate-300 hover:bg-white/10', !link.url && 'pointer-events-none opacity-40']"
            />
        </div>
    </div>
</template>
