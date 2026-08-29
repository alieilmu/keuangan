<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import FormField from './FormField.vue';
import { formatRupiah, todayIso } from '../lib/format';

const props = defineProps({
    open: { type: Boolean, default: false },
    accounts: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const form = useForm({
    from_account_id: '',
    to_account_id: '',
    amount: '',
    transfer_date: todayIso(),
    description: '',
    reference: '',
});

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        form.reset();
        form.clearErrors();
        form.transfer_date = todayIso();
    },
);

function label(account) {
    return account.account_number ? `${account.name} - ${account.account_number}` : account.name;
}

const source = computed(() => props.accounts.find((a) => a.id === Number(form.from_account_id)) ?? null);
const target = computed(() => props.accounts.find((a) => a.id === Number(form.to_account_id)) ?? null);

// Transfer sesama bank / nomor rekening sama tetap sah, hanya diberi catatan.
const sameNumber = computed(
    () =>
        source.value &&
        target.value &&
        source.value.account_number &&
        source.value.account_number === target.value.account_number,
);

const insufficient = computed(
    () => source.value && Number(form.amount) > Number(source.value.balance),
);

function submit() {
    form.post('/transfers', {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :open="open" title="Transfer Antar Akun" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Dari akun" required :error="form.errors.from_account_id">
                <select
                    v-model="form.from_account_id"
                    class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="" disabled>Pilih akun asal</option>
                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                        {{ label(account) }} - {{ formatRupiah(account.balance) }}
                    </option>
                </select>
            </FormField>

            <FormField label="Ke akun" required :error="form.errors.to_account_id">
                <select
                    v-model="form.to_account_id"
                    class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="" disabled>Pilih akun tujuan</option>
                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                        {{ label(account) }} - {{ formatRupiah(account.balance) }}
                    </option>
                </select>
            </FormField>

            <p
                v-if="sameNumber"
                class="rounded-xl bg-sky-50 px-3 py-2 text-[11px] font-medium text-sky-800 ring-1 ring-inset ring-sky-600/15"
            >
                Nomor rekening kedua akun sama. Transfer tetap diproses dan dicatat dua sisi:
                mutasi keluar dan mutasi masuk.
            </p>

            <FormField label="Nominal" required :error="form.errors.amount">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-xs text-slate-400">
                        Rp
                    </span>
                    <input
                        v-model="form.amount"
                        type="number"
                        min="1"
                        step="0.01"
                        class="w-full rounded-xl border-0 py-2.5 pl-9 pr-3 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </div>
            </FormField>

            <p v-if="insufficient" class="-mt-2 text-[11px] font-medium text-amber-600">
                Nominal melebihi saldo akun asal. Transfer tetap bisa disimpan bila saldo memang minus.
            </p>

            <FormField label="Tanggal" required :error="form.errors.transfer_date">
                <input
                    v-model="form.transfer_date"
                    type="date"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <FormField label="Keterangan" :error="form.errors.description">
                <input
                    v-model="form.description"
                    type="text"
                    maxlength="255"
                    placeholder="Contoh: top up e-wallet"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <FormField label="No. referensi" :error="form.errors.reference" hint="Opsional, dari bukti transfer bank.">
                <input
                    v-model="form.reference"
                    type="text"
                    maxlength="60"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
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
                    {{ form.processing ? 'Memproses...' : 'Transfer' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
