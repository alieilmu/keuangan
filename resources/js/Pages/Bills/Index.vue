<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Modal from '../../Components/Modal.vue';
import FormField from '../../Components/FormField.vue';
import PayBillModal from '../../Components/PayBillModal.vue';
import FileUploadField from '../../Components/FileUploadField.vue';
import DocumentChip from '../../Components/DocumentChip.vue';
import { formatRupiah, todayIso } from '../../lib/format';

const props = defineProps({
    bills: Array,
    filters: Object,
    summary: Object,
    accounts: Array,
    categories: Array,
});

const showForm = ref(false);
const editing = ref(null);
const billBeingPaid = ref(null);

const form = useForm({
    title: '',
    amount: '',
    due_date: todayIso(),
    account_id: '',
    category_id: '',
    notes: '',
    remind_days_before: 3,
    document: null,
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
                title: editing.value.title,
                amount: editing.value.amount,
                due_date: editing.value.due_date,
                account_id: editing.value.account_id ?? '',
                category_id: editing.value.category_id ?? '',
                notes: editing.value.notes ?? '',
                remind_days_before: editing.value.remind_days_before,
                document: null,
            });
        } else {
            form.reset();
            form.due_date = todayIso();
        }
    },
);

function create() {
    editing.value = null;
    showForm.value = true;
}

function edit(bill) {
    editing.value = bill;
    showForm.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };

    if (editing.value) {
        form.put(`/bills/${editing.value.id}`, options);
    } else {
        form.post('/bills', options);
    }
}

function destroy(bill) {
    if (!window.confirm(`Hapus tagihan ${bill.title}?`)) {
        return;
    }

    router.delete(`/bills/${bill.id}`, { preserveScroll: true });
}

function unpay(bill) {
    if (!window.confirm('Batalkan pembayaran? Transaksi terkait akan dihapus dan saldo dikembalikan.')) {
        return;
    }

    router.post(`/bills/${bill.id}/unpay`, {}, { preserveScroll: true });
}

function setStatus(status) {
    router.get('/bills', status ? { status } : {}, { preserveScroll: true, preserveState: true, replace: true });
}

function tone(bill) {
    if (bill.status === 'paid') return 'bg-slate-100 text-slate-500 ring-slate-500/15';
    if (bill.days_left < 0) return 'bg-red-50 text-red-700 ring-red-600/20';
    if (bill.days_left <= bill.remind_days_before) return 'bg-amber-50 text-amber-700 ring-amber-600/20';

    return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
}

function toneLabel(bill) {
    if (bill.status === 'paid') return 'Lunas';
    if (bill.days_left < 0) return `Telat ${Math.abs(bill.days_left)} hari`;
    if (bill.days_left === 0) return 'Hari ini';

    return `${bill.days_left} hari lagi`;
}
</script>

<template>
    <Head title="Tagihan" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-slate-900">Tagihan</h1>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ summary.unpaid_count }} belum dibayar - {{ formatRupiah(summary.unpaid_total) }}
                    <span v-if="summary.overdue_count" class="text-red-600">
                        ({{ summary.overdue_count }} terlambat)
                    </span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <div class="inline-flex rounded-xl bg-white p-1 ring-1 ring-slate-200">
                    <button
                        v-for="option in [
                            { value: null, label: 'Semua' },
                            { value: 'unpaid', label: 'Belum' },
                            { value: 'paid', label: 'Lunas' },
                        ]"
                        :key="option.label"
                        type="button"
                        class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
                        :class="
                            (filters.status ?? null) === option.value
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'text-slate-500 hover:bg-slate-100'
                        "
                        @click="setStatus(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <button
                    type="button"
                    class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                    @click="create"
                >
                    + Tagihan
                </button>
            </div>
        </div>

        <Card>
            <ul v-if="bills.length" class="divide-y divide-slate-100">
                <li v-for="bill in bills" :key="bill.id" class="flex flex-wrap items-center gap-3 py-3">
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: bill.category_color || '#cbd5e1' }"
                    />

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-medium text-slate-800">{{ bill.title }}</p>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
                                :class="tone(bill)"
                            >
                                {{ toneLabel(bill) }}
                            </span>
                        </div>
                        <p class="truncate text-xs text-slate-400">
                            <span v-if="bill.installment_label" class="font-medium text-sky-600">
                                {{ bill.installment_label }} -
                            </span>
                            Jatuh tempo {{ bill.due_label }}
                            <span v-if="bill.account"> - {{ bill.account }}</span>
                        </p>

                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <DocumentChip v-if="bill.invoice_document" :document="bill.invoice_document" tone="sky" />
                            <DocumentChip
                                v-if="bill.receipt_document"
                                :document="bill.receipt_document"
                                tone="emerald"
                            />
                            <span
                                v-if="!bill.invoice_document"
                                class="rounded-lg bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-700"
                            >
                                Dokumen tagihan belum diunggah
                            </span>
                        </div>
                    </div>

                    <p class="shrink-0 text-sm font-semibold tabular-nums text-slate-900">
                        {{ formatRupiah(bill.amount) }}
                    </p>

                    <div class="flex shrink-0 items-center gap-1.5">
                        <button
                            v-if="bill.status === 'unpaid'"
                            type="button"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                            @click="billBeingPaid = bill"
                        >
                            Bayar
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                            @click="unpay(bill)"
                        >
                            Batalkan
                        </button>

                        <button
                            v-if="bill.status === 'unpaid'"
                            type="button"
                            class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                            aria-label="Ubah"
                            @click="edit(bill)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                <path d="M4 20h4l10-10-4-4L4 16z" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                            aria-label="Hapus"
                            @click="destroy(bill)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                <path d="M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </li>
            </ul>

            <EmptyState v-else title="Belum ada tagihan" description="Tambahkan tagihan rutin agar diingatkan otomatis." />
        </Card>
    </div>

    <Modal :open="showForm" :title="editing ? 'Ubah Tagihan' : 'Tambah Tagihan'" @close="showForm = false">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Nama tagihan" required :error="form.errors.title">
                <input
                    v-model="form.title"
                    type="text"
                    maxlength="100"
                    placeholder="Contoh: Internet Rumah"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 placeholder:text-slate-300 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Nominal" required :error="form.errors.amount">
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">Rp</span>
                        <input
                            v-model="form.amount"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            class="w-full rounded-xl border-0 py-2.5 pl-9 pr-3 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                        />
                    </div>
                </FormField>

                <FormField label="Jatuh tempo" required :error="form.errors.due_date">
                    <input
                        v-model="form.due_date"
                        type="date"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Akun pembayaran" :error="form.errors.account_id">
                    <select
                        v-model="form.account_id"
                        class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="">Tentukan nanti</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">
                            {{ account.name }}
                        </option>
                    </select>
                </FormField>

                <FormField label="Kategori" :error="form.errors.category_id">
                    <select
                        v-model="form.category_id"
                        class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="">Tanpa kategori</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                </FormField>
            </div>

            <FormField
                label="Ingatkan berapa hari sebelumnya"
                required
                :error="form.errors.remind_days_before"
                hint="Push notification dikirim otomatis setiap hari oleh scheduler."
            >
                <input
                    v-model="form.remind_days_before"
                    type="number"
                    min="0"
                    max="30"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <FormField label="Catatan" :error="form.errors.notes">
                <input
                    v-model="form.notes"
                    type="text"
                    maxlength="255"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <FileUploadField
                v-model="form.document"
                label="Dokumen tagihan"
                :required="!editing"
                :existing="editing?.invoice_document ?? null"
                :error="form.errors.document"
                :hint="
                    editing
                        ? 'Biarkan kosong bila dokumen lama masih dipakai.'
                        : 'Wajib - unggah PDF atau foto tagihan, maksimal 5 MB.'
                "
            />

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
                    class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </div>
        </form>
    </Modal>

    <PayBillModal
        :bill="billBeingPaid"
        :accounts="accounts"
        :categories="categories"
        @close="billBeingPaid = null"
    />
</template>
