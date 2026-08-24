<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Modal from '../../Components/Modal.vue';
import FormField from '../../Components/FormField.vue';
import CreditProgress from '../../Components/CreditProgress.vue';
import { formatRupiah, formatDate, todayIso } from '../../lib/format';

const props = defineProps({
    credits: Array,
    summary: Object,
    accounts: Array,
    categories: Array,
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    total_amount: '',
    interest_rate: '',
    monthly_installment: '',
    start_date: todayIso(),
    end_date: '',
    due_day: Number(todayIso().slice(8, 10)),
    account_id: '',
    category_id: '',
    notes: '',
    remaining_months: '',
});

// Pratinjau tenor mengikuti tanggal mulai & target selesai yang sedang diisi.
const tenorPreview = computed(() => {
    if (!form.start_date || !form.end_date) {
        return null;
    }

    const [sy, sm] = form.start_date.split('-').map(Number);
    const [ey, em] = form.end_date.split('-').map(Number);
    const months = (ey - sy) * 12 + (em - sm) + 1;

    return months >= 1 ? months : null;
});

watch(
    () => showForm.value,
    (open) => {
        if (!open) {
            return;
        }

        form.clearErrors();

        if (editing.value) {
            Object.assign(form, {
                name: editing.value.name,
                total_amount: editing.value.total_amount,
                interest_rate: editing.value.interest_rate ?? '',
                monthly_installment: editing.value.monthly_installment,
                start_date: editing.value.start_date,
                end_date: editing.value.end_date,
                due_day: editing.value.due_day,
                account_id: editing.value.account_id ?? '',
                category_id: editing.value.category_id ?? '',
                notes: editing.value.notes ?? '',
                remaining_months: editing.value.remaining_months,
            });
        } else {
            form.reset();
            form.start_date = todayIso();
            form.due_day = Number(todayIso().slice(8, 10));
        }
    },
);

function create() {
    editing.value = null;
    showForm.value = true;
}

function edit(credit) {
    editing.value = credit;
    showForm.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };

    if (editing.value) {
        form.put(`/credits/${editing.value.id}`, options);
    } else {
        form.post('/credits', options);
    }
}

function destroy(credit) {
    if (!window.confirm(`Hapus kredit ${credit.name}? Tagihan angsuran yang belum dibayar ikut terhapus.`)) {
        return;
    }

    router.delete(`/credits/${credit.id}`, { preserveScroll: true });
}

function billNextEarly(credit) {
    router.post(`/credits/${credit.id}/next-installment`, {}, { preserveScroll: true });
}

function statusTone(credit) {
    if (credit.status === 'paid_off') return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
    if (credit.status === 'closed') return 'bg-slate-100 text-slate-500 ring-slate-500/15';

    return 'bg-sky-50 text-sky-700 ring-sky-600/20';
}
</script>

<template>
    <Head title="Kredit & Cicilan" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-slate-900">Kredit &amp; Cicilan</h1>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ summary.active_count }} kredit berjalan -
                    {{ formatRupiah(summary.monthly_total) }}/bulan
                </p>
            </div>

            <button
                type="button"
                class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                @click="create"
            >
                + Kredit
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <Card>
                <p class="text-xs text-slate-500">Total cicilan per bulan</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">
                    {{ formatRupiah(summary.monthly_total) }}
                </p>
            </Card>
            <Card>
                <p class="text-xs text-slate-500">Sisa kewajiban</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">
                    {{ formatRupiah(summary.outstanding_total) }}
                </p>
            </Card>
        </div>

        <Card v-if="credits.length" title="Daftar kredit" subtitle="Tagihan angsuran dibuat otomatis tiap bulan">
            <ul class="divide-y divide-slate-100">
                <li v-for="credit in credits" :key="credit.id" class="py-1">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <Link :href="`/credits/${credit.id}`" class="block hover:opacity-80">
                                <CreditProgress :credit="credit" />
                            </Link>

                            <dl class="mt-1 flex flex-wrap gap-x-4 gap-y-1 pb-3 text-[11px] text-slate-400">
                                <div class="flex gap-1">
                                    <dt>Pokok</dt>
                                    <dd class="tabular-nums text-slate-500">{{ formatRupiah(credit.total_amount) }}</dd>
                                </div>
                                <div v-if="credit.interest_rate !== null" class="flex gap-1">
                                    <dt>Bunga</dt>
                                    <dd class="tabular-nums text-slate-500">{{ credit.interest_rate }}%</dd>
                                </div>
                                <div class="flex gap-1">
                                    <dt>Selesai</dt>
                                    <dd class="text-slate-500">{{ formatDate(credit.end_date) }}</dd>
                                </div>
                                <div v-if="credit.account" class="flex gap-1">
                                    <dt>Sumber</dt>
                                    <dd class="text-slate-500">{{ credit.account }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5 pt-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
                                :class="statusTone(credit)"
                            >
                                {{ credit.status_label }}
                            </span>

                            <button
                                v-if="credit.can_bill_next_early"
                                type="button"
                                class="rounded-lg bg-sky-50 px-2.5 py-1.5 text-[11px] font-semibold text-sky-700 transition hover:bg-sky-100"
                                title="Tarik tagihan bulan berikutnya ke bulan ini"
                                @click="billNextEarly(credit)"
                            >
                                Tagih Berikutnya
                            </button>

                            <Link
                                :href="`/credits/${credit.id}`"
                                class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                aria-label="Detail"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                    <path d="M4 6h16M4 12h16M4 18h10" stroke-linecap="round" />
                                </svg>
                            </Link>

                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                aria-label="Ubah"
                                @click="edit(credit)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                    <path d="M4 20h4l10-10-4-4L4 16z" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                aria-label="Hapus"
                                @click="destroy(credit)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                    <path d="M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </Card>

        <Card v-else>
            <EmptyState
                title="Belum ada kredit"
                description="Catat KPR atau cicilan kendaraan, lalu tagihan bulanannya dibuat otomatis."
            />
        </Card>
    </div>

    <Modal :open="showForm" :title="editing ? 'Ubah Kredit' : 'Tambah Kredit'" @close="showForm = false">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Nama kredit" required :error="form.errors.name">
                <input
                    v-model="form.name"
                    type="text"
                    placeholder="Contoh: KPR Rumah"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Total pokok pinjaman" required :error="form.errors.total_amount">
                    <input
                        v-model="form.total_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField label="Bunga per tahun (%)" :error="form.errors.interest_rate" hint="Opsional">
                    <input
                        v-model="form.interest_rate"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>
            </div>

            <FormField label="Cicilan per bulan" required :error="form.errors.monthly_installment">
                <input
                    v-model="form.monthly_installment"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Tanggal mulai" required :error="form.errors.start_date">
                    <input
                        v-model="form.start_date"
                        type="date"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField label="Target selesai" required :error="form.errors.end_date">
                    <input
                        v-model="form.end_date"
                        type="date"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>
            </div>

            <p v-if="tenorPreview" class="-mt-1 text-xs text-slate-500">
                Tenor terhitung <span class="font-semibold text-slate-700">{{ tenorPreview }} bulan</span>.
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Jatuh tempo tiap tanggal" required :error="form.errors.due_day">
                    <input
                        v-model="form.due_day"
                        type="number"
                        min="1"
                        max="31"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField
                    label="Sisa tenor (bulan)"
                    :error="form.errors.remaining_months"
                    hint="Isi bila kredit sudah berjalan"
                >
                    <input
                        v-model="form.remaining_months"
                        type="number"
                        min="0"
                        :placeholder="tenorPreview ?? ''"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Sumber dana" :error="form.errors.account_id" hint="Dipakai saat tagihan dibayar">
                    <select
                        v-model="form.account_id"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="">- pilih nanti -</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">
                            {{ account.name }}
                        </option>
                    </select>
                </FormField>

                <FormField label="Kategori pengeluaran" :error="form.errors.category_id">
                    <select
                        v-model="form.category_id"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="">- tanpa kategori -</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                </FormField>
            </div>

            <FormField label="Catatan" :error="form.errors.notes">
                <input
                    v-model="form.notes"
                    type="text"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <div class="flex gap-2 pt-1">
                <button
                    type="button"
                    class="flex-1 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200"
                    @click="showForm = false"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                >
                    Simpan
                </button>
            </div>
        </form>
    </Modal>
</template>
