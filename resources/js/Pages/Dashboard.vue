<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '../Components/Card.vue';
import EmptyState from '../Components/EmptyState.vue';
import HeroCard from '../Components/HeroCard.vue';
import ExpensePieChart from '../Components/ExpensePieChart.vue';
import BillsCarousel from '../Components/BillsCarousel.vue';
import BudgetProgress from '../Components/BudgetProgress.vue';
import CreditProgress from '../Components/CreditProgress.vue';
import TransactionFormModal from '../Components/TransactionFormModal.vue';
import PayBillModal from '../Components/PayBillModal.vue';
import PeriodSwitcher from '../Components/PeriodSwitcher.vue';
import { formatRupiah } from '../lib/format';

const props = defineProps({
    greeting: String,
    period: Object,
    summary: Object,
    expense_breakdown: Array,
    upcoming_bills: Array,
    budgets: Array,
    credits: { type: Array, default: () => [] },
    recent_transactions: Array,
    accounts: Array,
    categories: Array,
});

const showTransactionModal = ref(false);
const billBeingPaid = ref(null);

const expenseCategories = computed(() => props.categories.filter((item) => item.type === 'expense'));

const attention = computed(() =>
    props.budgets.filter((budget) => budget.status === 'danger' || budget.status === 'over'),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-5">
        <!-- Judul + aksi -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-lg font-semibold tracking-tight text-slate-900">Ringkasan Arus Kas</h1>
                <p class="mt-0.5 text-xs text-slate-500">Periode {{ period.label }}</p>
            </div>

            <div class="flex items-center gap-2">
                <PeriodSwitcher :period="period.iso" />
                <button
                    type="button"
                    class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:scale-[0.98]"
                    @click="showTransactionModal = true"
                >
                    + Catat
                </button>
            </div>
        </div>

        <!-- 3 hero card -->
        <div class="grid gap-3 sm:grid-cols-3">
            <HeroCard
                label="Total Saldo Gabungan"
                :value="summary.total_balance"
                tone="neutral"
                :caption="`${accounts.length} akun aktif`"
            />
            <HeroCard
                label="Pemasukan Bulan Ini"
                :value="summary.income"
                tone="income"
                :caption="`Selisih ${formatRupiah(summary.net)}`"
            >
                <template #icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4">
                        <path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </template>
            </HeroCard>
            <HeroCard label="Pengeluaran Bulan Ini" :value="summary.expense" tone="expense">
                <template #icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4">
                        <path d="M12 5v14M5 12l7 7 7-7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </template>
            </HeroCard>
        </div>

        <!-- Widget tagihan (carousel) -->
        <Card title="Tagihan Terdekat" subtitle="Geser untuk melihat tagihan berikutnya">
            <template #actions>
                <Link href="/bills" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">
                    Semua tagihan
                </Link>
            </template>

            <BillsCarousel
                v-if="upcoming_bills.length"
                :bills="upcoming_bills"
                @pay="(bill) => (billBeingPaid = bill)"
            />

            <EmptyState
                v-else
                title="Tidak ada tagihan menunggu"
                description="Semua tagihan sudah lunas. Tambahkan tagihan baru untuk mulai mengingatkan."
            >
                <Link
                    href="/bills"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                >
                    Tambah tagihan
                </Link>
            </EmptyState>
        </Card>

        <!-- Grafik + indikator anggaran -->
        <div class="grid gap-5 lg:grid-cols-5">
            <Card class="lg:col-span-3" title="Alokasi Pengeluaran" :subtitle="period.label">
                <ExpensePieChart :items="expense_breakdown">
                    <template #empty>
                        <EmptyState
                            title="Belum ada pengeluaran"
                            description="Catat transaksi pengeluaran untuk melihat alokasinya di sini."
                        />
                    </template>
                </ExpensePieChart>
            </Card>

            <Card class="lg:col-span-2" title="Indikator Anggaran" :subtitle="`${budgets.length} kategori dipantau`">
                <template #actions>
                    <Link href="/budgets" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">
                        Atur
                    </Link>
                </template>

                <div v-if="budgets.length" class="divide-y divide-slate-100">
                    <BudgetProgress v-for="budget in budgets" :key="budget.id" :budget="budget" compact />
                </div>

                <EmptyState
                    v-else
                    title="Belum ada anggaran"
                    description="Tetapkan plafon bulanan per kategori agar pengeluaran lebih terkendali."
                >
                    <Link
                        href="/budgets"
                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                    >
                        Buat anggaran
                    </Link>
                </EmptyState>

                <p
                    v-if="attention.length"
                    class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-[11px] font-medium text-red-700 ring-1 ring-inset ring-red-600/15"
                >
                    {{ attention.length }} kategori sudah melewati 70% dari limit bulan ini.
                </p>
            </Card>
        </div>

        <!-- Progress kredit & cicilan -->
        <Card
            v-if="credits.length"
            title="Kredit & Cicilan"
            :subtitle="`${credits.length} kredit berjalan`"
        >
            <template #actions>
                <Link href="/credits" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">
                    Lihat semua
                </Link>
            </template>

            <div class="grid gap-x-6 divide-y divide-slate-100 sm:grid-cols-2 sm:divide-y-0">
                <CreditProgress v-for="credit in credits" :key="credit.id" :credit="credit" compact />
            </div>
        </Card>

        <!-- Transaksi terakhir -->
        <Card title="Transaksi Terakhir">
            <template #actions>
                <Link href="/transactions" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">
                    Lihat semua
                </Link>
            </template>

            <ul v-if="recent_transactions.length" class="divide-y divide-slate-100">
                <li
                    v-for="transaction in recent_transactions"
                    :key="transaction.id"
                    class="flex items-center gap-3 py-2.5"
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
                            {{ transaction.date_label }} - {{ transaction.account }}
                        </p>
                    </div>
                    <p
                        class="shrink-0 text-sm font-semibold tabular-nums"
                        :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-slate-900'"
                    >
                        {{ transaction.type === 'income' ? '+' : '-' }}{{ formatRupiah(transaction.amount) }}
                    </p>
                </li>
            </ul>

            <EmptyState v-else title="Belum ada transaksi" description="Mulai catat pemasukan dan pengeluaran harian." />
        </Card>
    </div>

    <TransactionFormModal
        :open="showTransactionModal"
        :accounts="accounts"
        :categories="categories"
        @close="showTransactionModal = false"
    />

    <PayBillModal
        :bill="billBeingPaid"
        :accounts="accounts"
        :categories="expenseCategories"
        @close="billBeingPaid = null"
    />
</template>
