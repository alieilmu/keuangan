<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import CreditProgress from '../../Components/CreditProgress.vue';
import DocumentChip from '../../Components/DocumentChip.vue';
import PayBillModal from '../../Components/PayBillModal.vue';
import { formatRupiah, formatDate } from '../../lib/format';

const props = defineProps({
    credit: Object,
    schedule: Array,
    can_bill_next_early: Boolean,
    accounts: Array,
    categories: Array,
});

const billBeingPaid = ref(null);

// "prior" = angsuran yang sudah lunas sebelum kredit dicatat di aplikasi ini.
const settledRows = computed(() => props.schedule.filter((row) => row.state === 'paid' || row.state === 'prior'));
const paidRows = computed(() => props.schedule.filter((row) => row.state === 'paid'));
const totalPaid = computed(() => settledRows.value.reduce((sum, row) => sum + Number(row.amount), 0));

// Baris yang sudah punya tagihan ditampilkan lebih dulu, sisanya rencana.
const openRow = computed(() => props.schedule.find((row) => row.state === 'billed') ?? null);

function billNextEarly() {
    router.post(`/credits/${props.credit.id}/next-installment`, {}, { preserveScroll: true });
}

function payFromRow(row) {
    billBeingPaid.value = {
        id: row.bill_id,
        title: `${props.credit.name} - Cicilan ${row.installment_number}/${props.credit.tenor_months}`,
        amount: row.amount,
        due_label: row.due_label,
        account_id: props.credit.account_id,
        category_id: props.credit.category_id,
        invoice_document: row.invoice_document,
    };
}

function stateTone(state) {
    if (state === 'paid') return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
    if (state === 'prior') return 'bg-slate-100 text-slate-600 ring-slate-500/15';
    if (state === 'billed') return 'bg-amber-50 text-amber-700 ring-amber-600/20';

    return 'bg-slate-100 text-slate-500 ring-slate-500/15';
}
</script>

<template>
    <Head :title="credit.name" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <Link href="/credits" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">
                    &larr; Kembali ke daftar kredit
                </Link>
                <h1 class="mt-1 truncate text-lg font-semibold tracking-tight text-slate-900">
                    {{ credit.name }}
                </h1>
            </div>

            <button
                v-if="can_bill_next_early"
                type="button"
                class="rounded-xl bg-sky-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-sky-700"
                @click="billNextEarly"
            >
                Tagih Angsuran Berikutnya
            </button>
        </div>

        <!-- Ringkasan progress -->
        <Card>
            <CreditProgress :credit="credit" />

            <dl class="mt-3 grid grid-cols-2 gap-3 border-t border-slate-100 pt-3 sm:grid-cols-4">
                <div>
                    <dt class="text-[11px] text-slate-400">Total pokok</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">
                        {{ formatRupiah(credit.total_amount) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] text-slate-400">Sudah dibayar</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums text-emerald-600">
                        {{ formatRupiah(totalPaid) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] text-slate-400">Sisa kewajiban</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">
                        {{ formatRupiah(credit.outstanding) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] text-slate-400">Bunga</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">
                        {{ credit.interest_rate !== null ? `${credit.interest_rate}%` : '-' }}
                    </dd>
                </div>
            </dl>
        </Card>

        <p
            v-if="!can_bill_next_early && openRow"
            class="rounded-xl bg-amber-50 px-3.5 py-2.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/15"
        >
            Angsuran ke-{{ openRow.installment_number }} masih menunggu pembayaran. Lunasi dulu untuk bisa
            menagih angsuran bulan berikutnya lebih awal.
        </p>

        <!-- Histori pembayaran -->
        <Card
            title="Histori Pembayaran"
            :subtitle="`${settledRows.length} dari ${credit.tenor_months} angsuran lunas`"
            :padded="false"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] text-left text-sm">
                    <thead class="border-b border-slate-100 text-[11px] uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-2.5 font-medium sm:px-5">Angsuran</th>
                            <th class="px-4 py-2.5 font-medium">Jatuh tempo</th>
                            <th class="px-4 py-2.5 text-right font-medium">Nominal</th>
                            <th class="px-4 py-2.5 font-medium">Dibayar</th>
                            <th class="px-4 py-2.5 font-medium">Dokumen</th>
                            <th class="px-4 py-2.5 font-medium sm:px-5"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="row in schedule"
                            :key="row.installment_number"
                            :class="row.state === 'planned' ? 'text-slate-400' : 'text-slate-700'"
                        >
                            <td class="px-4 py-2.5 sm:px-5">
                                <span class="font-medium tabular-nums">{{ row.installment_number }}</span>
                                <span class="text-slate-400">/{{ credit.tenor_months }}</span>
                            </td>

                            <td class="whitespace-nowrap px-4 py-2.5">{{ row.due_label }}</td>

                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">
                                {{ formatRupiah(row.amount) }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-2.5">
                                <span v-if="row.paid_label" class="text-xs">
                                    {{ row.paid_label }}
                                    <span v-if="row.account" class="block text-[11px] text-slate-400">
                                        {{ row.account }}
                                    </span>
                                </span>
                                <span
                                    v-else
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
                                    :class="stateTone(row.state)"
                                >
                                    {{ row.state_label }}
                                </span>
                            </td>

                            <td class="px-4 py-2.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <DocumentChip
                                        v-if="row.invoice_document"
                                        :document="row.invoice_document"
                                        tone="sky"
                                    />
                                    <DocumentChip
                                        v-if="row.receipt_document"
                                        :document="row.receipt_document"
                                        tone="emerald"
                                    />
                                    <span
                                        v-if="!row.invoice_document && !row.receipt_document"
                                        class="text-[11px] text-slate-300"
                                    >
                                        -
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-2.5 text-right sm:px-5">
                                <button
                                    v-if="row.state === 'billed'"
                                    type="button"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                                    @click="payFromRow(row)"
                                >
                                    Bayar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </div>

    <PayBillModal
        :bill="billBeingPaid"
        :accounts="accounts"
        :categories="categories"
        @close="billBeingPaid = null"
    />
</template>
