<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Modal from '../../Components/Modal.vue';
import FormField from '../../Components/FormField.vue';
import BudgetProgress from '../../Components/BudgetProgress.vue';
import PeriodSwitcher from '../../Components/PeriodSwitcher.vue';
import { budgetStyle } from '../../lib/budget';
import { formatPercent, formatRupiah } from '../../lib/format';

const props = defineProps({
    budgets: Array,
    totals: Object,
    period: Object,
    categories: Array,
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    category_id: '',
    limit_amount: '',
    period_month: props.period.month,
    period_year: props.period.year,
});

watch(
    () => showForm.value,
    (open) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.period_month = props.period.month;
        form.period_year = props.period.year;

        if (editing.value) {
            form.category_id = editing.value.category_id;
            form.limit_amount = editing.value.limit_amount;
        } else {
            form.category_id = '';
            form.limit_amount = '';
        }
    },
);

const usedPercentage = computed(() =>
    props.totals.limit > 0 ? Math.round((props.totals.spent / props.totals.limit) * 100) : 0,
);

const overallStyle = computed(() => budgetStyle(
    usedPercentage.value > 100 ? 'over' : usedPercentage.value > 70 ? 'danger' : usedPercentage.value > 50 ? 'warning' : 'safe',
));

function create() {
    editing.value = null;
    showForm.value = true;
}

function edit(budget) {
    editing.value = budget;
    showForm.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };

    if (editing.value) {
        form.put(`/budgets/${editing.value.id}`, options);
    } else {
        form.post('/budgets', options);
    }
}

function destroy(budget) {
    if (!window.confirm(`Hapus anggaran ${budget.category_name}?`)) {
        return;
    }

    router.delete(`/budgets/${budget.id}`, { preserveScroll: true });
}

function copyPrevious() {
    router.post('/budgets/copy-previous', { period: props.period.iso }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Anggaran" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-slate-900">Anggaran</h1>
                <p class="mt-0.5 text-xs text-slate-500">Plafon pengeluaran {{ period.label }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <PeriodSwitcher :period="period.iso" />
                <button
                    type="button"
                    class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50"
                    @click="copyPrevious"
                >
                    Salin bulan lalu
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                    @click="create"
                >
                    + Anggaran
                </button>
            </div>
        </div>

        <!-- Ringkasan keseluruhan -->
        <Card v-if="budgets.length">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs text-slate-500">Total terpakai</p>
                    <p class="mt-1 text-xl font-semibold tracking-tight text-slate-900">
                        {{ formatRupiah(totals.spent) }}
                        <span class="text-sm font-normal text-slate-400">/ {{ formatRupiah(totals.limit) }}</span>
                    </p>
                </div>
                <p :class="overallStyle.text" class="text-sm font-semibold tabular-nums">
                    {{ formatPercent(usedPercentage) }}
                </p>
            </div>

            <div :class="overallStyle.track" class="mt-3 h-2.5 w-full overflow-hidden rounded-full">
                <div
                    :class="overallStyle.bar"
                    class="h-full rounded-full transition-[width] duration-500"
                    :style="{ width: `${Math.min(100, usedPercentage)}%` }"
                />
            </div>
        </Card>

        <!-- Daftar anggaran -->
        <Card title="Per Kategori">
            <ul v-if="budgets.length" class="divide-y divide-slate-100">
                <li v-for="budget in budgets" :key="budget.id" class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <BudgetProgress :budget="budget" />
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                            aria-label="Ubah"
                            @click="edit(budget)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                <path d="M4 20h4l10-10-4-4L4 16z" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                            aria-label="Hapus"
                            @click="destroy(budget)"
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
                title="Belum ada anggaran"
                description="Tetapkan plafon per kategori pengeluaran untuk periode ini."
            />
        </Card>

        <!-- Legenda warna -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div
                v-for="item in [
                    { label: '0 - 50% aman', bar: 'bg-emerald-500' },
                    { label: '51 - 70% waspada', bar: 'bg-amber-400' },
                    { label: '71 - 100% bahaya', bar: 'bg-red-500' },
                    { label: '> 100% overbudget', bar: 'bg-red-800' },
                ]"
                :key="item.label"
                class="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-[11px] text-slate-500 ring-1 ring-slate-200/70"
            >
                <span :class="item.bar" class="h-1.5 w-6 rounded-full" />
                {{ item.label }}
            </div>
        </div>
    </div>

    <Modal :open="showForm" :title="editing ? 'Ubah Anggaran' : 'Tambah Anggaran'" @close="showForm = false">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Kategori pengeluaran" required :error="form.errors.category_id">
                <select
                    v-model="form.category_id"
                    class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="" disabled>Pilih kategori</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
            </FormField>

            <FormField label="Limit per bulan" required :error="form.errors.limit_amount">
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">Rp</span>
                    <input
                        v-model="form.limit_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        class="w-full rounded-xl border-0 py-2.5 pl-9 pr-3 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </div>
            </FormField>

            <p class="rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
                Berlaku untuk periode <span class="font-medium text-slate-700">{{ period.label }}</span>.
                Notifikasi dikirim otomatis saat pemakaian menembus 70%.
            </p>

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
</template>
