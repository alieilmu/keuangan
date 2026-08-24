<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Modal from '../../Components/Modal.vue';
import PeriodSwitcher from '../../Components/PeriodSwitcher.vue';
import TransactionFormModal from '../../Components/TransactionFormModal.vue';
import { formatDate, formatRupiah } from '../../lib/format';

const props = defineProps({
    transactions: Object,
    filters: Object,
    period: Object,
    accounts: Array,
    categories: Array,
});

const showForm = ref(false);
const editing = ref(null);
const showImport = ref(false);

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
                { period: props.period.iso, ...cleaned(value) },
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
    const query = new URLSearchParams(cleaned({ period: props.period.iso, type: filters.type }));

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

const importForm = useForm({ file: null });

function submitImport() {
    importForm.post('/transactions/import', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            showImport.value = false;
        },
    });
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
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: transaction.category_color || '#cbd5e1' }"
                    />

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-slate-800">
                            {{ transaction.description || transaction.category || 'Transaksi' }}
                        </p>
                        <p class="truncate text-xs text-slate-400">
                            {{ formatDate(transaction.transaction_date) }} - {{ transaction.account }}
                            <span v-if="transaction.category"> - {{ transaction.category }}</span>
                        </p>
                    </div>

                    <p
                        class="shrink-0 text-sm font-semibold tabular-nums"
                        :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-slate-900'"
                    >
                        {{ transaction.type === 'income' ? '+' : '-' }}{{ formatRupiah(transaction.amount) }}
                    </p>

                    <div class="flex shrink-0 items-center gap-1">
                        <button
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

    <!-- Import Excel -->
    <Modal :open="showImport" title="Import Transaksi dari Excel" @close="showImport = false">
        <form class="space-y-4" @submit.prevent="submitImport">
            <ol class="space-y-1.5 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                <li>1. Unduh <a href="/transactions/template" class="font-semibold text-emerald-700">template baku</a>.</li>
                <li>2. Isi sheet <span class="font-medium">Transaksi</span> sesuai sheet <span class="font-medium">Panduan</span>.</li>
                <li>3. Unggah kembali file tersebut di bawah ini.</li>
            </ol>

            <div>
                <input
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    class="w-full rounded-xl text-xs text-slate-500 ring-1 ring-slate-200 file:mr-3 file:rounded-l-xl file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-xs file:font-semibold file:text-slate-600"
                    @input="importForm.file = $event.target.files[0]"
                />
                <p v-if="importForm.errors.file" class="mt-1 text-xs text-red-600">{{ importForm.errors.file }}</p>
                <p v-if="importForm.progress" class="mt-1 text-xs text-slate-400">
                    Mengunggah {{ importForm.progress.percentage }}%
                </p>
            </div>

            <div
                v-if="$page.props.flash?.import_failures?.length"
                class="max-h-40 overflow-y-auto rounded-xl bg-red-50 px-3 py-2 text-[11px] text-red-700 ring-1 ring-inset ring-red-600/15"
            >
                <p class="mb-1 font-semibold">Baris yang dilewati:</p>
                <p v-for="failure in $page.props.flash.import_failures" :key="failure.row">
                    Baris {{ failure.row }}: {{ failure.errors.join(', ') }}
                </p>
            </div>

            <div class="flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200"
                    @click="showImport = false"
                >
                    Tutup
                </button>
                <button
                    type="submit"
                    class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="importForm.processing || !importForm.file"
                >
                    {{ importForm.processing ? 'Mengimpor...' : 'Import Sekarang' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
