<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Modal from '../../Components/Modal.vue';
import FormField from '../../Components/FormField.vue';
import ColorPicker from '../../Components/ColorPicker.vue';
import { formatRupiah } from '../../lib/format';

const props = defineProps({
    accounts: Array,
    account_types: Array,
    total_balance: Number,
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    type: 'cash',
    account_number: '',
    opening_balance: 0,
    color: '#10b981',
    is_active: true,
});

// Nomor rekening hanya relevan untuk akun digital (bank & e-wallet).
const NUMBERED_TYPES = ['bank', 'ewallet'];
const needsAccountNumber = computed(() => NUMBERED_TYPES.includes(form.type));

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
                type: editing.value.type,
                account_number: editing.value.account_number ?? '',
                opening_balance: editing.value.opening_balance,
                color: editing.value.color,
                is_active: editing.value.is_active,
            });
        } else {
            form.reset();
        }
    },
);

function create() {
    editing.value = null;
    showForm.value = true;
}

function edit(account) {
    editing.value = account;
    showForm.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };

    if (editing.value) {
        form.put(`/accounts/${editing.value.id}`, options);
    } else {
        form.post('/accounts', options);
    }
}

function destroy(account) {
    if (!window.confirm(`Hapus akun ${account.name}?`)) {
        return;
    }

    router.delete(`/accounts/${account.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Akun" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-slate-900">Sumber Dana</h1>
                <p class="mt-0.5 text-xs text-slate-500">
                    Total saldo aktif {{ formatRupiah(total_balance) }}
                </p>
            </div>

            <button
                type="button"
                class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                @click="create"
            >
                + Akun
            </button>
        </div>

        <div v-if="accounts.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="account in accounts"
                :key="account.id"
                class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70"
                :class="account.is_active ? '' : 'opacity-60'"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="size-2.5 rounded-full" :style="{ backgroundColor: account.color }" />
                        <p class="truncate text-sm font-semibold text-slate-900">{{ account.name }}</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-0.5">
                        <button
                            type="button"
                            class="grid size-7 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                            aria-label="Ubah"
                            @click="edit(account)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                <path d="M4 20h4l10-10-4-4L4 16z" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="grid size-7 place-items-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                            aria-label="Hapus"
                            @click="destroy(account)"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                <path d="M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>

                <p class="mt-3 text-lg font-semibold tracking-tight text-slate-900">
                    {{ formatRupiah(account.balance) }}
                </p>
                <p class="mt-1 text-xs text-slate-400">
                    {{ account.type_label }} - {{ account.transactions_count }} transaksi
                </p>

                <p
                    v-if="account.account_number"
                    class="mt-1 truncate font-mono text-[11px] tracking-wide text-slate-400"
                >
                    No. {{ account.account_number }}
                </p>
                <p
                    v-else-if="account.requires_account_number"
                    class="mt-1 text-[11px] font-medium text-amber-600"
                >
                    Nomor rekening belum diisi
                </p>
            </article>
        </div>

        <Card v-else>
            <EmptyState title="Belum ada akun" description="Tambahkan sumber dana seperti tunai, bank, atau e-wallet." />
        </Card>
    </div>

    <Modal :open="showForm" :title="editing ? 'Ubah Akun' : 'Tambah Akun'" @close="showForm = false">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Nama akun" required :error="form.errors.name">
                <input
                    v-model="form.name"
                    type="text"
                    maxlength="60"
                    placeholder="Contoh: BCA Utama"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 placeholder:text-slate-300 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <FormField label="Jenis" required :error="form.errors.type">
                <select
                    v-model="form.type"
                    class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                >
                    <option v-for="type in account_types" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </option>
                </select>
            </FormField>

            <FormField
                label="Saldo awal"
                required
                :error="form.errors.opening_balance"
                hint="Saldo sebelum transaksi apa pun dicatat di aplikasi ini."
            >
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">Rp</span>
                    <input
                        v-model="form.opening_balance"
                        type="number"
                        step="0.01"
                        inputmode="decimal"
                        class="w-full rounded-xl border-0 py-2.5 pl-9 pr-3 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </div>
            </FormField>

            <FormField
                v-if="needsAccountNumber"
                label="Nomor rekening"
                required
                :error="form.errors.account_number"
                hint="Wajib untuk akun bank dan e-wallet."
            >
                <input
                    v-model="form.account_number"
                    type="text"
                    maxlength="40"
                    inputmode="numeric"
                    placeholder="Contoh: 1234567890"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <FormField label="Warna" :error="form.errors.color">
                <ColorPicker v-model="form.color" />
            </FormField>

            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                />
                Akun aktif (dihitung pada total saldo)
            </label>

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
