<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Modal from '../../Components/Modal.vue';
import TransferModal from '../../Components/TransferModal.vue';
import PeriodSwitcher from '../../Components/PeriodSwitcher.vue';
import ScopeSwitcher from '../../Components/ScopeSwitcher.vue';
import TransactionFormModal from '../../Components/TransactionFormModal.vue';
import { formatDate, formatRupiah } from '../../lib/format';

const props = defineProps({
    transactions: Object,
    filters: Object,
    period: Object,
    accounts: Array,
    categories: Array,
    transfers: Array,
    scope_options: { type: Array, default: () => [] },
});

const showForm = ref(false);
const editing = ref(null);
const showImport = ref(false);
const showTransfer = ref(false);

const filters = reactive({
    type: props.filters.type ?? '',
    category_id: props.filters.category_id ?? '',
    account_id: props.filters.account_id ?? '',
    search: props.filters.search ?? '',
});

let debounce = null;

watch(
    filters,
    (value) => {
        window.clearTimeout(debounce);

        debounce = window.setTimeout(() => {
            router.get(
                '/transactions',
                { period: props.period.iso, scope: props.filters.scope, ...cleaned(value) },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);
    },
    { deep: true },
);

function cleaned(value) {
    return Object.fromEntries(Object.entries(value).filter(([, item]) => item !== '' && item !== null));
}

const exportUrl = computed(() => {
    const query = new URLSearchParams(
        cleaned({ period: props.period.iso, scope: props.filters.scope, type: filters.type }),
    );

    return `/transactions/export?${query.toString()}`;
});

function edit(transaction) {
    editing.value = transaction;
    showForm.value = true;
}

function create() {
    editing.value = null;
    showForm.value = true;
}

function destroy(transaction) {
    if (!window.confirm('Hapus transaksi ini? Saldo akun akan dikembalikan.')) {
        return;
    }

    router.delete(`/transactions/${transaction.id}`, { preserveScroll: true });
}

const importForm = useForm({ file: null, skip_duplicates: true });

/* ---------------------------------------------------------------------
 * Import dua langkah: periksa (pratinjau tabel) -> import baris valid
 * ------------------------------------------------------------------- */
const importPreview = ref(null);
const previewFilter = ref('all');
const previewPage = ref(1);
const PREVIEW_PAGE_SIZE = 100;

const previewSummary = computed(() => importPreview.value?.summary ?? null);

const previewRows = computed(() => {
    const rows = importPreview.value?.rows ?? [];

    if (previewFilter.value === 'errors') {
        return rows.filter((row) => row.errors.length);
    }

    if (previewFilter.value === 'duplicates') {
        return rows.filter((row) => row.duplicate);
    }

    return rows;
});

const previewPageCount = computed(() => Math.max(1, Math.ceil(previewRows.value.length / PREVIEW_PAGE_SIZE)));
const pagedPreviewRows = computed(() =>
    previewRows.value.slice((previewPage.value - 1) * PREVIEW_PAGE_SIZE, previewPage.value * PREVIEW_PAGE_SIZE),
);

watch(previewFilter, () => (previewPage.value = 1));

const importableCount = computed(() => {
    const summary = previewSummary.value;

    return summary ? summary.valid - (importForm.skip_duplicates ? summary.duplicates : 0) : 0;
});

// Pemasukan & transfer masuk menambah saldo, sisanya mengurangi.
const PLUS_TYPES = ['income', 'transfer_in'];

function amountSign(transaction) {
    return PLUS_TYPES.includes(transaction.type) ? '+' : '-';
}

function amountTone(transaction) {
    if (transaction.is_transfer) {
        return 'text-sky-600';
    }

    return transaction.type === 'income' ? 'text-emerald-600' : 'text-slate-900';
}

function cancelTransfer(transfer) {
    if (!window.confirm('Batalkan transfer ini? Saldo kedua akun akan dikembalikan.')) {
        return;
    }

    router.delete(`/transfers/${transfer.id}`, { preserveScroll: true });
}

function chooseImportFile(event) {
    importForm.file = event.target.files[0] ?? null;
    importForm.clearErrors();
    importPreview.value = null;
}

function checkImport() {
    importForm.post('/transactions/import/preview', {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: (page) => {
            importPreview.value = page.props.flash?.import_preview ?? null;
            previewFilter.value = importPreview.value?.summary?.invalid ? 'errors' : 'all';
            previewPage.value = 1;
        },
    });
}

function submitImport() {
    importForm
        .transform((data) => ({ ...data, skip_duplicates: data.skip_duplicates ? 1 : 0 }))
        .post('/transactions/import', {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            // Tetap buka popup bila server menolak, supaya alasannya terlihat.
            onSuccess: (page) => {
                if (!page.props.flash?.error) {
                    closeImport();
                }
            },
        });
}

function closeImport() {
    showImport.value = false;
    importPreview.value = null;
    previewFilter.value = 'all';
    importForm.reset();
    importForm.clearErrors();
}
</script>

<template>
    <Head title="Transaksi" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-slate-900">Transaksi</h1>
                <p class="mt-0.5 text-xs text-slate-500">{{ transactions.total }} catatan pada {{ period.label }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <ScopeSwitcher :scope="props.filters.scope" :options="scope_options" />
                <PeriodSwitcher :period="period.iso" />

                <a
                    href="/transactions/template"
                    class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50"
                >
                    Template
                </a>
                <button
                    type="button"
                    class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50"
                    @click="showImport = true"
                >
                    Import
                </button>
                <a
                    :href="exportUrl"
                    class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50"
                >
                    Export
                </a>
                <button
                    type="button"
                    class="rounded-xl bg-sky-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-sky-700"
                    @click="showTransfer = true"
                >
                    Transfer
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                    @click="create"
                >
                    + Catat
                </button>
            </div>
        </div>

        <!-- Filter -->
        <div class="grid gap-2 sm:grid-cols-4">
            <input
                v-model="filters.search"
                type="search"
                placeholder="Cari keterangan..."
                class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 placeholder:text-slate-300 focus:ring-2 focus:ring-emerald-500"
            />
            <select
                v-model="filters.type"
                class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
            >
                <option value="">Semua tipe</option>
                <option value="income">Pemasukan</option>
                <option value="expense">Pengeluaran</option>
                <option value="transfer_out">Transfer Keluar</option>
                <option value="transfer_in">Transfer Masuk</option>
            </select>
            <select
                v-model="filters.category_id"
                class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
            >
                <option value="">Semua kategori</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                </option>
            </select>
            <select
                v-model="filters.account_id"
                class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
            >
                <option value="">Semua akun</option>
                <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
            </select>
        </div>

        <!-- Daftar transaksi -->
        <Card>
            <ul v-if="transactions.data.length" class="divide-y divide-slate-100">
                <li
                    v-for="transaction in transactions.data"
                    :key="transaction.id"
                    class="group flex items-center gap-3 py-3"
                >
                    <span
                        v-if="!transaction.is_transfer"
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: transaction.category_color || '#cbd5e1' }"
                    />
                    <span
                        v-else
                        class="grid size-5 shrink-0 place-items-center rounded-full bg-sky-50 text-sky-600"
                        title="Mutasi transfer"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="size-3">
                            <path d="M4 8h13l-3-3M20 16H7l3 3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-slate-800">
                            {{ transaction.description || transaction.category || 'Transaksi' }}
                        </p>
                        <p class="truncate text-xs text-slate-400">
                            {{ formatDate(transaction.transaction_date) }} - {{ transaction.account }}
                            <span v-if="transaction.category"> - {{ transaction.category }}</span>
                            <span v-if="transaction.transfer_counterpart">
                                - {{ transaction.type === 'transfer_out' ? 'ke' : 'dari' }}
                                {{ transaction.transfer_counterpart }}
                            </span>
                        </p>
                    </div>

                    <p class="shrink-0 text-sm font-semibold tabular-nums" :class="amountTone(transaction)">
                        {{ amountSign(transaction) }}{{ formatRupiah(transaction.amount) }}
                    </p>

                    <div class="flex shrink-0 items-center gap-1">
                        <span
                            v-if="transaction.is_transfer"
                            class="px-2 text-[11px] font-medium text-slate-400"
                            title="Kelola lewat panel Transfer di bawah"
                        >
                            Transfer
                        </span>
                        <button
                            v-if="!transaction.is_transfer"
                            type="button"
                            class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                            aria-label="Ubah"
                            @click="edit(transaction)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                <path d="M4 20h4l10-10-4-4L4 16z" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <button
                            v-if="!transaction.is_transfer"
                            type="button"
                            class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                            aria-label="Hapus"
                            @click="destroy(transaction)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                <path d="M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </li>
            </ul>

            <EmptyState
                v-else
                title="Belum ada transaksi"
                description="Ubah filter atau catat transaksi baru untuk periode ini."
            />

            <!-- Pagination -->
            <nav v-if="transactions.last_page > 1" class="mt-4 flex flex-wrap items-center justify-center gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="link in transactions.links"
                    :key="link.label"
                    :href="link.url"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                    :class="
                        link.active
                            ? 'bg-emerald-600 text-white'
                            : link.url
                              ? 'text-slate-500 hover:bg-slate-100'
                              : 'text-slate-300'
                    "
                    v-html="link.label"
                />
            </nav>
        </Card>
    </div>

    <TransactionFormModal
        :open="showForm"
        :accounts="accounts"
        :categories="categories"
        :transaction="editing"
        @close="showForm = false"
    />

    <!-- Riwayat transfer periode ini -->
    <Card
        v-if="transfers.length"
        class="mt-5"
        title="Transfer Antar Akun"
        :subtitle="`${transfers.length} transfer pada ${period.label}`"
    >
        <ul class="divide-y divide-slate-100">
            <li v-for="transfer in transfers" :key="transfer.id" class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center">
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-600">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                            <path d="M4 8h13l-3-3M20 16H7l3 3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="flex min-w-0 items-center gap-1.5 text-sm font-medium text-slate-800">
                            <span class="truncate">
                                {{ transfer.from_account }} &rarr; {{ transfer.to_account }}
                            </span>
                            <span
                                v-if="transfer.kind !== 'transfer'"
                                class="shrink-0 rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800"
                            >
                                {{ transfer.kind_label }}
                            </span>
                        </p>
                        <p class="truncate text-xs text-slate-400">
                            {{ transfer.date_label }}
                            <span v-if="transfer.description"> - {{ transfer.description }}</span>
                            <span v-if="transfer.reference"> - Ref {{ transfer.reference }}</span>
                        </p>
                        <p v-if="transfer.same_institution" class="mt-0.5 text-[11px] font-medium text-sky-600">
                            Nomor rekening sama - mutasi keluar &amp; masuk tetap tercatat
                        </p>
                        <p v-if="transfer.savings_goal" class="mt-0.5 text-[11px] font-medium text-emerald-600">
                            Setoran tabungan: {{ transfer.savings_goal }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center justify-between gap-3 pl-11 sm:justify-end sm:pl-0">
                    <p class="shrink-0 text-sm font-semibold tabular-nums text-slate-900">
                        {{ formatRupiah(transfer.amount) }}
                    </p>

                    <button
                        v-if="!transfer.savings_goal_id"
                        type="button"
                        class="grid size-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                        aria-label="Batalkan transfer"
                        @click="cancelTransfer(transfer)"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                            <path d="M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </li>
        </ul>
    </Card>

    <!-- Import Excel -->
    <Modal
        :open="showImport"
        title="Import Transaksi dari Excel"
        :max-width="importPreview ? 'sm:max-w-5xl' : 'sm:max-w-lg'"
        @close="closeImport"
    >
        <div class="space-y-4">
            <ol v-if="!importPreview" class="space-y-1.5 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                <li>1. Unduh <a href="/transactions/template" class="font-semibold text-emerald-700">template baku</a>.</li>
                <li>2. Isi sheet <span class="font-medium">Transaksi</span> sesuai sheet <span class="font-medium">Panduan</span>.</li>
                <li>3. Pilih file, lalu periksa datanya sebelum diimpor.</li>
            </ol>

            <div>
                <input
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    class="w-full rounded-xl text-xs text-slate-500 ring-1 ring-slate-200 file:mr-3 file:rounded-l-xl file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-xs file:font-semibold file:text-slate-600"
                    @input="chooseImportFile"
                />
                <p v-if="importForm.errors.file" class="mt-1 text-xs text-red-600">{{ importForm.errors.file }}</p>
                <p v-if="importForm.progress" class="mt-1 text-xs text-slate-400">
                    Mengunggah {{ importForm.progress.percentage }}%
                </p>
            </div>

            <template v-if="previewSummary">
                <!-- Kesalahan tingkat file (kolom hilang, file kosong, dsb.) -->
                <div
                    v-if="previewSummary.file_error"
                    class="rounded-xl bg-red-50 px-4 py-3 text-xs leading-relaxed text-red-700 ring-1 ring-inset ring-red-600/15"
                >
                    <p class="mb-1 font-semibold">File tidak bisa diproses</p>
                    {{ previewSummary.file_error }}
                </div>

                <template v-else>
                    <!-- Ringkasan -->
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                            <p class="text-[11px] text-slate-500">Total baris</p>
                            <p class="text-lg font-bold tabular-nums text-slate-900">{{ previewSummary.total }}</p>
                        </div>
                        <div class="rounded-xl bg-emerald-50 px-3 py-2">
                            <p class="text-[11px] text-emerald-700">Siap diimpor</p>
                            <p class="text-lg font-bold tabular-nums text-emerald-700">{{ importableCount }}</p>
                        </div>
                        <div class="rounded-xl bg-red-50 px-3 py-2">
                            <p class="text-[11px] text-red-700">Bermasalah</p>
                            <p class="text-lg font-bold tabular-nums text-red-700">{{ previewSummary.invalid }}</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 px-3 py-2">
                            <p class="text-[11px] text-amber-700">Kemungkinan duplikat</p>
                            <p class="text-lg font-bold tabular-nums text-amber-700">{{ previewSummary.duplicates }}</p>
                        </div>
                    </div>

                    <!-- Keterangan error, di atas tabel -->
                    <div
                        v-if="previewSummary.error_groups.length"
                        class="rounded-xl bg-red-50 px-4 py-3 text-xs text-red-700 ring-1 ring-inset ring-red-600/15"
                    >
                        <p class="mb-1.5 font-semibold">Kesalahan yang ditemukan &mdash; baris ini tidak akan diimpor:</p>
                        <ul class="max-h-40 space-y-1 overflow-y-auto">
                            <li v-for="group in previewSummary.error_groups" :key="group.message" class="leading-relaxed">
                                <span class="font-medium">{{ group.message }}</span>
                                <span class="text-red-600/80">
                                    &mdash; {{ group.count }} baris (baris {{ group.rows.join(', ') }}{{ group.count > group.rows.length ? ', ...' : '' }})
                                </span>
                            </li>
                        </ul>
                        <p v-if="previewSummary.accounts_hint.length" class="mt-2 border-t border-red-600/10 pt-2 text-red-600/90">
                            Nama akun yang dikenali di grup Anda:
                            <span class="font-medium">{{ previewSummary.accounts_hint.join(' | ') }}</span>
                        </p>
                    </div>

                    <label
                        v-if="previewSummary.duplicates"
                        class="flex cursor-pointer items-start gap-2 rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-800 ring-1 ring-inset ring-amber-600/20"
                    >
                        <input v-model="importForm.skip_duplicates" type="checkbox" class="mt-0.5 size-3.5 accent-amber-600" />
                        <span>
                            Lewati <strong>{{ previewSummary.duplicates }}</strong> baris yang tampaknya sudah pernah dicatat
                            (tanggal, tipe, nominal, akun, dan keterangan sama persis dengan transaksi yang ada).
                        </span>
                    </label>

                    <p
                        v-if="previewSummary.new_categories.length"
                        class="rounded-xl bg-sky-50 px-4 py-2.5 text-xs text-sky-800 ring-1 ring-inset ring-sky-600/15"
                    >
                        Kategori baru yang akan dibuat otomatis:
                        <span class="font-medium">{{ previewSummary.new_categories.join(', ') }}</span>
                    </p>

                    <!-- Filter tabel -->
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="tab in [
                                { value: 'all', label: `Semua (${previewSummary.total})` },
                                { value: 'errors', label: `Bermasalah (${previewSummary.invalid})` },
                                { value: 'duplicates', label: `Duplikat (${previewSummary.duplicates})` },
                            ]"
                            :key="tab.value"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                            :class="previewFilter === tab.value ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            @click="previewFilter = tab.value"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- Tabel pratinjau -->
                    <div class="max-h-[45dvh] overflow-auto rounded-xl ring-1 ring-slate-200">
                        <table class="min-w-full text-left text-xs">
                            <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Baris</th>
                                    <th class="px-3 py-2 font-semibold">Tanggal</th>
                                    <th class="px-3 py-2 font-semibold">Tipe</th>
                                    <th class="px-3 py-2 font-semibold">Kategori</th>
                                    <th class="px-3 py-2 font-semibold">Akun</th>
                                    <th class="px-3 py-2 text-right font-semibold">Nominal</th>
                                    <th class="px-3 py-2 font-semibold">Keterangan</th>
                                    <th class="px-3 py-2 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="row in pagedPreviewRows"
                                    :key="row.row"
                                    :class="row.errors.length ? 'bg-red-50/70' : row.duplicate ? 'bg-amber-50/70' : ''"
                                >
                                    <td class="whitespace-nowrap px-3 py-2 tabular-nums text-slate-400">{{ row.row }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 tabular-nums text-slate-700">{{ row.tanggal || '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-700">{{ row.tipe_label || '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-700">
                                        {{ row.kategori || '-' }}
                                        <span v-if="row.new_category" class="ml-1 rounded bg-sky-100 px-1 text-[10px] font-semibold text-sky-700">baru</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-700">{{ row.akun || '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-900">
                                        {{ row.nominal !== null ? formatRupiah(row.nominal) : row.nominal_raw || '-' }}
                                    </td>
                                    <td class="max-w-[14rem] truncate px-3 py-2 text-slate-500" :title="row.keterangan">{{ row.keterangan || '-' }}</td>
                                    <td class="min-w-[12rem] px-3 py-2">
                                        <ul v-if="row.errors.length" class="space-y-0.5 text-[11px] text-red-700">
                                            <li v-for="error in row.errors" :key="error">{{ error }}</li>
                                        </ul>
                                        <span v-else-if="row.duplicate" class="text-[11px] font-medium text-amber-700">
                                            {{ importForm.skip_duplicates ? 'Duplikat, dilewati' : 'Duplikat, tetap diimpor' }}
                                        </span>
                                        <span v-else class="text-[11px] font-medium text-emerald-700">Siap diimpor</span>
                                    </td>
                                </tr>
                                <tr v-if="!pagedPreviewRows.length">
                                    <td colspan="8" class="px-3 py-6 text-center text-slate-400">Tidak ada baris pada filter ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="previewPageCount > 1" class="flex items-center justify-between text-xs text-slate-500">
                        <button type="button" class="rounded-lg px-2.5 py-1 hover:bg-slate-100 disabled:opacity-40" :disabled="previewPage === 1" @click="previewPage--">
                            &larr; Sebelumnya
                        </button>
                        <span>Halaman {{ previewPage }} dari {{ previewPageCount }}</span>
                        <button
                            type="button"
                            class="rounded-lg px-2.5 py-1 hover:bg-slate-100 disabled:opacity-40"
                            :disabled="previewPage === previewPageCount"
                            @click="previewPage++"
                        >
                            Berikutnya &rarr;
                        </button>
                    </div>
                </template>
            </template>

            <div class="flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200"
                    @click="closeImport"
                >
                    Batal
                </button>
                <button
                    v-if="!previewSummary || previewSummary.file_error"
                    type="button"
                    class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="importForm.processing || !importForm.file"
                    @click="checkImport"
                >
                    {{ importForm.processing ? 'Memeriksa...' : 'Periksa Data' }}
                </button>
                <button
                    v-else
                    type="button"
                    class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="importForm.processing || importableCount === 0"
                    @click="submitImport"
                >
                    {{ importForm.processing ? 'Mengimpor...' : importableCount ? `Import ${importableCount} Transaksi` : 'Tidak ada baris yang bisa diimpor' }}
                </button>
            </div>
        </div>
    </Modal>

    <TransferModal :open="showTransfer" :accounts="accounts" @close="showTransfer = false" />
</template>
