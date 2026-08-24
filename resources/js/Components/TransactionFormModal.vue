<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import FormField from './FormField.vue';
import { todayIso } from '../lib/format';

const props = defineProps({
    open: { type: Boolean, default: false },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    transaction: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
    account_id: '',
    category_id: '',
    type: 'expense',
    amount: '',
    transaction_date: todayIso(),
    description: '',
});

const isEdit = computed(() => Boolean(props.transaction?.id));

// Kategori disaring mengikuti tipe transaksi yang dipilih.
const availableCategories = computed(() =>
    props.categories.filter((category) => category.type === form.type),
);

watch(
    () => [props.open, props.transaction],
    () => {
        if (!props.open) {
            return;
        }

        form.clearErrors();

        if (props.transaction) {
            form.account_id = props.transaction.account_id ?? '';
            form.category_id = props.transaction.category_id ?? '';
            form.type = props.transaction.type ?? 'expense';
            form.amount = props.transaction.amount ?? '';
            form.transaction_date = props.transaction.transaction_date ?? todayIso();
            form.description = props.transaction.description ?? '';
        } else {
            form.reset();
            form.account_id = props.accounts[0]?.id ?? '';
            form.transaction_date = todayIso();
        }
    },
    { immediate: true },
);

watch(
    () => form.type,
    () => {
        if (!availableCategories.value.some((category) => category.id === form.category_id)) {
            form.category_id = '';
        }
    },
);

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    };

    if (isEdit.value) {
        form.put(`/transactions/${props.transaction.id}`, options);
    } else {
        form.post('/transactions', options);
    }
}
</script>

<template>
    <Modal :open="open" :title="isEdit ? 'Ubah Transaksi' : 'Catat Transaksi'" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="option in [
                        { value: 'expense', label: 'Pengeluaran' },
                        { value: 'income', label: 'Pemasukan' },
                    ]"
                    :key="option.value"
                    type="button"
                    class="rounded-xl px-3 py-2.5 text-sm font-semibold ring-1 transition"
                    :class="
                        form.type === option.value
                            ? option.value === 'income'
                                ? 'bg-emerald-600 text-white ring-emerald-600'
                                : 'bg-slate-900 text-white ring-slate-900'
                            : 'bg-white text-slate-500 ring-slate-200 hover:bg-slate-50'
                    "
                    @click="form.type = option.value"
                >
                    {{ option.label }}
                </button>
            </div>

            <FormField label="Nominal" required :error="form.errors.amount">
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">Rp</span>
                    <input
                        v-model="form.amount"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="0"
                        class="w-full rounded-xl border-0 py-2.5 pl-9 pr-3 text-sm tabular-nums ring-1 ring-slate-200 transition placeholder:text-slate-300 focus:ring-2 focus:ring-emerald-500"
                    />
                </div>
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Akun" required :error="form.errors.account_id">
                    <select
                        v-model="form.account_id"
                        class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="" disabled>Pilih akun</option>
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
                        <option v-for="category in availableCategories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                </FormField>
            </div>

            <FormField label="Tanggal" required :error="form.errors.transaction_date">
                <input
                    v-model="form.transaction_date"
                    type="date"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <FormField label="Keterangan" :error="form.errors.description">
                <input
                    v-model="form.description"
                    type="text"
                    maxlength="255"
                    placeholder="Contoh: makan siang"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 placeholder:text-slate-300 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <div class="flex gap-2 pt-1">
                <button
                    type="button"
                    class="flex-1 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200"
                    @click="emit('close')"
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
