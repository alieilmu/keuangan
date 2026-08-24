<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import FormField from './FormField.vue';
import FileUploadField from './FileUploadField.vue';
import DocumentChip from './DocumentChip.vue';
import { formatRupiah, todayIso } from '../lib/format';

const props = defineProps({
    bill: { type: Object, default: null },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const form = useForm({
    account_id: '',
    category_id: '',
    paid_on: todayIso(),
    receipt: null,
});

watch(
    () => props.bill,
    (bill) => {
        if (!bill) {
            return;
        }

        form.clearErrors();
        form.account_id = bill.account_id ?? props.accounts[0]?.id ?? '';
        form.category_id = bill.category_id ?? '';
        form.paid_on = todayIso();
        form.receipt = null;
    },
    { immediate: true },
);

function submit() {
    form.post(`/bills/${props.bill.id}/pay`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :open="Boolean(bill)" title="Bayar Tagihan" @close="emit('close')">
        <form v-if="bill" class="space-y-4" @submit.prevent="submit">
            <div class="rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs text-slate-500">{{ bill.title }}</p>
                <p class="mt-0.5 text-lg font-semibold text-slate-900">{{ formatRupiah(bill.amount) }}</p>
                <p class="mt-0.5 text-xs text-slate-400">Jatuh tempo {{ bill.due_label }}</p>

                <DocumentChip
                    v-if="bill.invoice_document"
                    :document="bill.invoice_document"
                    tone="sky"
                    class="mt-2"
                >
                    Lihat dokumen tagihan
                </DocumentChip>
            </div>

            <FormField label="Bayar dari akun" required :error="form.errors.account_id">
                <select
                    v-model="form.account_id"
                    class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="" disabled>Pilih akun</option>
                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                        {{ account.name }} - {{ formatRupiah(account.balance) }}
                    </option>
                </select>
            </FormField>

            <FormField
                label="Kategori pengeluaran"
                :error="form.errors.category_id"
                hint="Dipakai untuk perhitungan anggaran bulan ini."
            >
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

            <FormField label="Tanggal bayar" :error="form.errors.paid_on">
                <input
                    v-model="form.paid_on"
                    type="date"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <FileUploadField
                v-model="form.receipt"
                label="Nota pembayaran"
                :error="form.errors.receipt"
                hint="Opsional - PDF atau foto struk, maksimal 5 MB."
            />

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
                    {{ form.processing ? 'Memproses...' : 'Bayar Sekarang' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
