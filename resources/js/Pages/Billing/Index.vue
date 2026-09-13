<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { formatRupiah } from '../../lib/format';

const props = defineProps({
    group: Object,
    subscription: Object,
    plans: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    quote: { type: Object, default: null },
    max_extra_per_order: { type: Number, default: 20 },
});

const pending = computed(() => props.orders.find((o) => o.status === 'pending') ?? null);

/* ---------------------------------------------------------------------
 * Pesanan baru: upgrade/perpanjang paket, atau tambah slot anggota
 * ------------------------------------------------------------------- */
const mode = ref('plan');
const selectedPriceId = ref(null);
const extraCount = ref(1);
const coupon = ref('');
const loadingQuote = ref(false);
const form = useForm({ type: 'plan', plan_price_id: null, count: null, coupon_code: '' });

const canPreview = computed(() => (mode.value === 'plan' ? Boolean(selectedPriceId.value) : extraCount.value >= 1));

// Pratinjau harga dihitung di server lewat partial reload -- tidak ada
// pesanan yang dibuat sampai tombol "Buat Pesanan" ditekan.
function refreshQuote() {
    if (!canPreview.value) {
        return;
    }

    const params =
        mode.value === 'plan'
            ? { preview: 'plan', plan_price_id: selectedPriceId.value }
            : { preview: 'add_member', count: extraCount.value };

    if (coupon.value.trim()) {
        params.coupon = coupon.value.trim();
    }

    router.get('/billing', params, {
        only: ['quote'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loadingQuote.value = true),
        onFinish: () => (loadingQuote.value = false),
    });
}

watch([mode, selectedPriceId, extraCount], refreshQuote);

function changeCount(delta) {
    extraCount.value = Math.min(props.max_extra_per_order, Math.max(1, extraCount.value + delta));
}

function submitOrder() {
    form.type = mode.value;
    form.plan_price_id = mode.value === 'plan' ? selectedPriceId.value : null;
    form.count = mode.value === 'add_member' ? extraCount.value : null;
    form.coupon_code = coupon.value.trim();

    form.post('/billing/orders', {
        preserveScroll: true,
        onSuccess: () => {
            coupon.value = '';
            selectedPriceId.value = null;
        },
    });
}

function cancelOrder(order) {
    if (!confirm(`Batalkan pesanan ${order.reference}?`)) {
        return;
    }

    router.post(`/billing/orders/${order.id}/cancel`, {}, { preserveScroll: true });
}

const statusTone = (status) =>
    status === 'paid'
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
        : status === 'pending'
          ? 'bg-amber-50 text-amber-700 ring-amber-600/20'
          : 'bg-slate-100 text-slate-500 ring-slate-300';

const firstError = computed(
    () => form.errors.order || form.errors.plan_price_id || form.errors.count || form.errors.coupon_code || null,
);
</script>

<template>
    <Head title="Langganan" />

    <div class="space-y-5">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-slate-900">Langganan</h1>
            <p class="mt-0.5 text-xs text-slate-500">{{ group?.name ?? 'Belum tergabung grup' }}</p>
        </div>

        <EmptyState
            v-if="!subscription"
            title="Belum ada langganan"
            description="Akun Anda belum terhubung ke grup berlangganan. Hubungi admin."
        />

        <template v-else>
            <!-- Langganan saat ini -->
            <Card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs text-slate-500">Paket saat ini</p>
                        <p class="mt-0.5 text-xl font-bold text-slate-900">{{ subscription.plan_name }}</p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
                        :class="
                            subscription.expired
                                ? 'bg-red-50 text-red-700 ring-red-600/20'
                                : subscription.is_demo
                                  ? 'bg-amber-50 text-amber-700 ring-amber-600/20'
                                  : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                        "
                    >
                        {{ subscription.expired ? 'Berakhir' : subscription.is_demo ? 'Masa Coba' : 'Aktif' }}
                    </span>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                        <dt class="text-[11px] text-slate-500">{{ subscription.is_demo ? 'Masa coba s/d' : 'Tagihan berikutnya' }}</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ subscription.expires_label ?? 'Tanpa batas' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                        <dt class="text-[11px] text-slate-500">Sisa masa aktif</dt>
                        <dd class="mt-0.5 text-sm font-semibold" :class="subscription.expired ? 'text-red-600' : 'text-slate-900'">
                            {{ subscription.remaining_days === null ? '-' : `${subscription.remaining_days} hari` }}
                        </dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                        <dt class="text-[11px] text-slate-500">Periode tagihan</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ subscription.billing_months }} bulan</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                        <dt class="text-[11px] text-slate-500">Anggota</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">
                            {{ group.member_count }}{{ group.member_quota === null ? '' : ` / ${group.member_quota}` }}
                        </dd>
                        <dd v-if="subscription.extra_members" class="text-[10px] text-slate-400">
                            termasuk {{ subscription.extra_members }} slot tambahan
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="subscription.is_demo"
                    class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-800 ring-1 ring-inset ring-amber-600/20"
                >
                    Anda sedang memakai masa coba. Pilih paket di bawah supaya pencatatan tetap berjalan setelah
                    {{ subscription.expires_label }}.
                </p>
            </Card>

            <!-- Pesanan menunggu pembayaran -->
            <Card v-if="pending" title="Menunggu Pembayaran" :subtitle="pending.reference">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div
                        class="grid size-36 shrink-0 place-items-center self-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 text-center"
                    >
                        <div>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto size-10 text-slate-400">
                                <path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2zM14 18h2M18 14h2" stroke-linejoin="round" />
                            </svg>
                            <p class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">QRIS Simulasi</p>
                        </div>
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <p class="text-sm font-medium text-slate-800">{{ pending.description }}</p>
                        <ul class="space-y-1 text-xs text-slate-500">
                            <li v-for="(line, i) in pending.lines" :key="i" class="flex justify-between gap-3">
                                <span class="min-w-0">{{ line.label }}</span>
                                <span class="shrink-0 tabular-nums" :class="line.amount < 0 ? 'text-emerald-600' : ''">
                                    {{ line.amount < 0 ? '-' : '' }}{{ formatRupiah(Math.abs(line.amount)) }}
                                </span>
                            </li>
                        </ul>
                        <p class="flex justify-between border-t border-slate-100 pt-2 text-sm font-bold text-slate-900">
                            <span>Total bayar</span><span class="tabular-nums">{{ formatRupiah(pending.total) }}</span>
                        </p>
                        <p class="text-[11px] leading-relaxed text-slate-500">
                            Ini halaman demonstrasi: kode QR di atas tidak bisa dipindai. Langganan Anda diperbarui
                            otomatis setelah admin mengonfirmasi pembayaran.
                        </p>
                        <button
                            type="button"
                            class="text-xs font-medium text-red-600 hover:underline"
                            @click="cancelOrder(pending)"
                        >
                            Batalkan pesanan
                        </button>
                    </div>
                </div>
            </Card>

            <!-- Buat pesanan -->
            <Card v-else title="Upgrade atau Tambah Anggota">
                <div class="mb-4 inline-flex rounded-xl bg-slate-100 p-1">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                        :class="mode === 'plan' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'"
                        @click="mode = 'plan'"
                    >
                        Pilih / Perpanjang Paket
                    </button>
                    <button
                        v-if="subscription.sells_extra"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                        :class="mode === 'add_member' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'"
                        @click="mode = 'add_member'"
                    >
                        Tambah Slot Anggota
                    </button>
                </div>

                <!-- Mode paket -->
                <div v-if="mode === 'plan'" class="grid gap-3 md:grid-cols-2">
                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        class="rounded-2xl p-4 ring-1 ring-slate-200"
                        :class="plan.prices.some((p) => p.id === selectedPriceId) ? 'ring-2 ring-emerald-500' : ''"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ plan.name }}</p>
                                <p class="text-[11px] text-slate-500">
                                    {{ plan.max_members === null ? 'Anggota tanpa batas' : `Hingga ${plan.max_members} anggota` }}
                                    <span v-if="plan.extra_member_price"> - slot tambahan {{ formatRupiah(plan.extra_member_price) }}/bln</span>
                                </p>
                            </div>
                            <span
                                v-if="plan.id === subscription.plan_id && !subscription.is_demo"
                                class="shrink-0 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700"
                            >
                                Paket Anda
                            </span>
                        </div>

                        <ul v-if="plan.features.length" class="mt-2 space-y-0.5">
                            <li v-for="feature in plan.features" :key="feature" class="flex gap-1.5 text-[11px] text-slate-500">
                                <span class="text-emerald-600">&#10003;</span>{{ feature }}
                            </li>
                        </ul>

                        <div class="mt-3 grid gap-1.5">
                            <button
                                v-for="price in plan.prices"
                                :key="price.id"
                                type="button"
                                class="flex items-center justify-between rounded-xl px-3 py-2 text-left ring-1 transition"
                                :class="
                                    selectedPriceId === price.id
                                        ? 'bg-emerald-50 ring-emerald-500'
                                        : 'bg-white ring-slate-200 hover:bg-slate-50'
                                "
                                @click="selectedPriceId = price.id"
                            >
                                <span class="text-xs font-medium text-slate-700">{{ price.label }}</span>
                                <span class="text-right">
                                    <span class="block text-xs font-semibold tabular-nums text-slate-900">{{ formatRupiah(price.price) }}</span>
                                    <span v-if="price.months > 1" class="block text-[10px] tabular-nums text-slate-400">
                                        {{ formatRupiah(price.monthly) }}/bln
                                    </span>
                                </span>
                            </button>
                        </div>
                    </div>
                    <EmptyState v-if="!plans.length" title="Belum ada paket yang dijual" class="md:col-span-2" />
                </div>

                <!-- Mode tambah slot -->
                <div v-else class="space-y-3">
                    <p class="text-xs text-slate-500">
                        Slot tambahan menaikkan kuota anggota paket {{ subscription.plan_name }}. Harga
                        {{ formatRupiah(subscription.extra_member_price) }} per anggota per bulan, ditagih sampai
                        {{ subscription.expires_label }} ({{ subscription.remaining_months }} bulan), lalu ikut
                        diperpanjang bersama paket.
                    </p>
                    <div class="flex items-center gap-2">
                        <button type="button" class="grid size-9 place-items-center rounded-xl ring-1 ring-slate-200 hover:bg-slate-50" @click="changeCount(-1)">-</button>
                        <span class="w-16 text-center text-lg font-bold tabular-nums text-slate-900">{{ extraCount }}</span>
                        <button type="button" class="grid size-9 place-items-center rounded-xl ring-1 ring-slate-200 hover:bg-slate-50" @click="changeCount(1)">+</button>
                        <span class="text-xs text-slate-500">slot anggota</span>
                    </div>
                </div>

                <!-- Kode diskon & ringkasan -->
                <div class="mt-5 space-y-3 border-t border-slate-100 pt-4">
                    <div class="flex gap-2">
                        <input
                            v-model="coupon"
                            type="text"
                            maxlength="30"
                            placeholder="Kode diskon (opsional)"
                            class="min-w-0 flex-1 rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm uppercase ring-1 ring-slate-200 placeholder:normal-case focus:ring-2 focus:ring-emerald-500"
                            @keydown.enter.prevent="refreshQuote"
                        />
                        <button
                            type="button"
                            class="shrink-0 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-200 disabled:opacity-50"
                            :disabled="!canPreview || !coupon.trim()"
                            @click="refreshQuote"
                        >
                            Terapkan
                        </button>
                    </div>

                    <div v-if="canPreview && quote" class="rounded-xl bg-slate-50 px-3 py-3" :class="loadingQuote ? 'opacity-60' : ''">
                        <p v-if="quote.error" class="text-xs font-medium text-red-600">{{ quote.error }}</p>
                        <template v-else>
                            <ul class="space-y-1 text-xs text-slate-600">
                                <li v-for="(line, i) in quote.lines" :key="i" class="flex justify-between gap-3">
                                    <span class="min-w-0">{{ line.label }}</span>
                                    <span class="shrink-0 tabular-nums" :class="line.amount < 0 ? 'text-emerald-600' : ''">
                                        {{ line.amount < 0 ? '-' : '' }}{{ formatRupiah(Math.abs(line.amount)) }}
                                    </span>
                                </li>
                            </ul>
                            <p class="mt-2 flex justify-between border-t border-slate-200 pt-2 text-sm font-bold text-slate-900">
                                <span>Total</span><span class="tabular-nums">{{ formatRupiah(quote.total) }}</span>
                            </p>
                        </template>
                    </div>

                    <p v-if="firstError" class="text-xs font-medium text-red-600">{{ firstError }}</p>

                    <button
                        type="button"
                        class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="!canPreview || form.processing || loadingQuote || Boolean(quote?.error)"
                        @click="submitOrder"
                    >
                        {{ form.processing ? 'Membuat pesanan...' : 'Buat Pesanan' }}
                    </button>
                </div>
            </Card>

            <!-- Riwayat -->
            <Card title="Riwayat Pesanan" :padded="false">
                <ul v-if="orders.length" class="divide-y divide-slate-100">
                    <li v-for="order in orders" :key="order.id" class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:px-5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ order.description }}</p>
                            <p class="truncate text-[11px] text-slate-400">
                                {{ order.reference }} - {{ order.created_label }}
                                <span v-if="order.coupon_code"> - kode {{ order.coupon_code }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
                            <span class="text-sm font-semibold tabular-nums text-slate-900">{{ formatRupiah(order.total) }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset" :class="statusTone(order.status)">
                                {{ order.status_label }}
                            </span>
                        </div>
                    </li>
                </ul>
                <EmptyState v-else title="Belum ada pesanan" />
            </Card>
        </template>
    </div>
</template>
