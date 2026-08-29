<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import SavingsProgress from '../../Components/SavingsProgress.vue';
import PayBillModal from '../../Components/PayBillModal.vue';
import { formatRupiah } from '../../lib/format';

const props = defineProps({
    goal: Object,
    history: Array,
    can_bill_next_early: Boolean,
    accounts: Array,
});

const billBeingPaid = ref(null);

const paidRows = computed(() => props.history.filter((row) => row.status === 'paid'));
const openRow = computed(() => props.history.find((row) => row.status === 'unpaid') ?? null);

function billNext() {
    router.post(`/savings/${props.goal.id}/next-contribution`, {}, { preserveScroll: true });
}

function payRow(row) {
    billBeingPaid.value = {
        id: row.bill_id,
        title: `Setoran ${props.goal.name} #${row.contribution_number}`,
        amount: row.amount,
        due_label: row.due_label,
        account_id: props.goal.source_account_id,
        category_id: null,
    };
}
</script>

<template>
    <Head :title="goal.name" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <Link href="/savings" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">
                    &larr; Kembali ke daftar tabungan
                </Link>
                <h1 class="mt-1 truncate text-lg font-semibold tracking-tight text-slate-900">
                    {{ goal.name }}
                </h1>
            </div>

            <button
                v-if="can_bill_next_early"
                type="button"
                class="rounded-xl bg-sky-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-sky-700"
                @click="billNext"
            >
                Setor Lebih Awal
            </button>
        </div>

        <Card>
            <SavingsProgress :goal="goal" />

            <dl class="mt-3 grid grid-cols-2 gap-3 border-t border-slate-100 pt-3 sm:grid-cols-4">
                <div>
                    <dt class="text-[11px] text-slate-400">Terkumpul</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums text-emerald-600">
                        {{ formatRupiah(goal.saved_amount) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] text-slate-400">Sisa menuju target</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">
                        {{ formatRupiah(goal.remaining_amount) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] text-slate-400">Setoran / bulan</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">
                        {{ formatRupiah(goal.monthly_contribution) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] text-slate-400">Perkiraan sisa setoran</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">
                        {{ goal.remaining_contributions }}x
                    </dd>
                </div>
            </dl>

            <div class="mt-3 grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                    <p class="text-[11px] text-slate-400">Akun sumber dana</p>
                    <p class="mt-0.5 truncate text-xs font-medium text-slate-700">{{ goal.source_account }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 px-3 py-2.5">
                    <p class="text-[11px] text-emerald-600/70">Akun penyimpanan</p>
                    <p class="mt-0.5 truncate text-xs font-medium text-emerald-800">{{ goal.storage_account }}</p>
                </div>
            </div>
        </Card>

        <p
            v-if="!can_bill_next_early && openRow"
            class="rounded-xl bg-amber-50 px-3.5 py-2.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/15"
        >
            Setoran #{{ openRow.contribution_number }} masih menunggu pembayaran. Selesaikan dulu untuk
            bisa menyetor lebih awal.
        </p>

        <Card title="Riwayat Setoran" :subtitle="`${paidRows.length} setoran sudah masuk`" :padded="false">
            <div v-if="history.length" class="overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm">
                    <thead class="border-b border-slate-100 text-[11px] uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-2.5 font-medium sm:px-5">Setoran</th>
                            <th class="px-4 py-2.5 font-medium">Jatuh tempo</th>
                            <th class="px-4 py-2.5 text-right font-medium">Nominal</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5 font-medium sm:px-5"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in history" :key="row.contribution_number" class="text-slate-700">
                            <td class="px-4 py-2.5 font-medium tabular-nums sm:px-5">
                                #{{ row.contribution_number }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5">{{ row.due_label }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">
                                {{ formatRupiah(row.amount) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
                                    :class="
                                        row.status === 'paid'
                                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                                            : 'bg-amber-50 text-amber-700 ring-amber-600/20'
                                    "
                                >
                                    {{ row.status_label }}
                                </span>
                                <span v-if="row.paid_label" class="ml-1 text-[11px] text-slate-400">
                                    {{ row.paid_label }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right sm:px-5">
                                <button
                                    v-if="row.status === 'unpaid'"
                                    type="button"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                                    @click="payRow(row)"
                                >
                                    Setor
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="px-4 py-6 text-center text-xs text-slate-400 sm:px-5">
                Belum ada tagihan setoran. Tagihan pertama dibuat otomatis menjelang tanggal setoran.
            </p>
        </Card>
    </div>

    <PayBillModal :bill="billBeingPaid" :accounts="accounts" :categories="[]" @close="billBeingPaid = null" />
</template>
