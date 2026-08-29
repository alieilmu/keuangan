<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Modal from '../../Components/Modal.vue';
import FormField from '../../Components/FormField.vue';
import SavingsProgress from '../../Components/SavingsProgress.vue';
import { formatRupiah, todayIso } from '../../lib/format';

const props = defineProps({
    goals: Array,
    summary: Object,
    accounts: Array,
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    target_amount: '',
    monthly_contribution: '',
    start_date: todayIso(),
    target_date: '',
    due_day: new Date().getDate(),
    source_account_id: '',
    storage_account_id: '',
    notes: '',
});

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
                target_amount: editing.value.target_amount,
                monthly_contribution: editing.value.monthly_contribution,
                start_date: editing.value.start_date,
                target_date: editing.value.target_date ?? '',
                due_day: editing.value.due_day,
                source_account_id: editing.value.source_account_id ?? '',
                storage_account_id: editing.value.storage_account_id ?? '',
                notes: editing.value.notes ?? '',
            });
        } else {
            form.reset();
            form.start_date = todayIso();
            form.due_day = new Date().getDate();
        }
    },
);

function label(account) {
    return account.account_number ? `${account.name} - ${account.account_number}` : account.name;
}

function create() {
    editing.value = null;
    showForm.value = true;
}

function edit(goal) {
    editing.value = goal;
    showForm.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };

    if (editing.value) {
        form.put(`/savings/${editing.value.id}`, options);
    } else {
        form.post('/savings', options);
    }
}

function destroy(goal) {
    if (!window.confirm(`Hapus target tabungan ${goal.name}? Riwayat setoran tetap tersimpan.`)) {
        return;
    }

    router.delete(`/savings/${goal.id}`, { preserveScroll: true });
}

function statusTone(goal) {
    if (goal.status === 'completed') return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
    if (goal.status === 'paused') return 'bg-slate-100 text-slate-500 ring-slate-500/15';

    return 'bg-sky-50 text-sky-700 ring-sky-600/20';
}
</script>

<template>
    <Head title="Tabungan" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-slate-900">Tabungan Terencana</h1>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ summary.active_count }} target berjalan -
                    {{ formatRupiah(summary.monthly_total) }}/bulan -
                    terkumpul {{ formatRupiah(summary.saved_total) }}
                </p>
            </div>

            <button
                type="button"
                class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                @click="create"
            >
                + Target
            </button>
        </div>

        <Card v-if="goals.length" :padded="false">
            <ul class="divide-y divide-slate-100">
                <li v-for="goal in goals" :key="goal.id" class="px-4 py-4 sm:px-5">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <Link :href="`/savings/${goal.id}`" class="block hover:opacity-80">
                                <SavingsProgress :goal="goal" />
                            </Link>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5 pt-1">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
                                :class="statusTone(goal)"
                            >
                                {{ goal.status_label }}
                            </span>

                            <Link
                                :href="`/savings/${goal.id}`"
                                class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                aria-label="Detail"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                    <path d="M4 6h16M4 12h16M4 18h10" stroke-linecap="round" />
                                </svg>
                            </Link>

                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                aria-label="Ubah"
                                @click="edit(goal)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                    <path d="M4 20h4l10-10-4-4L4 16z" stroke-linejoin="round" />
                                </svg>
                            </button>

                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                aria-label="Hapus"
                                @click="destroy(goal)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                    <path d="M5 7h14M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </Card>

        <EmptyState
            v-else
            title="Belum ada target tabungan"
            description="Tetapkan target dan setoran bulanannya. Sistem akan menagih setoran lewat modul Tagihan setiap bulan."
        />
    </div>

    <Modal :open="showForm" :title="editing ? 'Ubah Target Tabungan' : 'Target Tabungan Baru'" @close="showForm = false">
        <form class="space-y-4" @submit.prevent="submit">
            <FormField label="Nama target" required :error="form.errors.name">
                <input
                    v-model="form.name"
                    type="text"
                    maxlength="100"
                    placeholder="Contoh: Dana Darurat"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <div class="grid grid-cols-2 gap-3">
                <FormField label="Target dana" required :error="form.errors.target_amount">
                    <input
                        v-model="form.target_amount"
                        type="number"
                        min="1"
                        step="0.01"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField label="Setoran / bulan" required :error="form.errors.monthly_contribution">
                    <input
                        v-model="form.monthly_contribution"
                        type="number"
                        min="1"
                        step="0.01"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>
            </div>

            <!-- Alokasi akun ganda -->
            <FormField
                label="Akun sumber dana"
                required
                :error="form.errors.source_account_id"
                hint="Tempat uang setoran diambil setiap bulan."
            >
                <select
                    v-model="form.source_account_id"
                    class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="" disabled>Pilih akun</option>
                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                        {{ label(account) }} - {{ formatRupiah(account.balance) }}
                    </option>
                </select>
            </FormField>

            <FormField
                label="Akun penyimpanan"
                required
                :error="form.errors.storage_account_id"
                hint="Tempat dana tabungan dikumpulkan. Harus berbeda dari akun sumber."
            >
                <select
                    v-model="form.storage_account_id"
                    class="w-full rounded-xl border-0 py-2.5 pl-3 pr-8 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                >
                    <option value="" disabled>Pilih akun</option>
                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                        {{ label(account) }} - {{ formatRupiah(account.balance) }}
                    </option>
                </select>
            </FormField>

            <div class="grid grid-cols-2 gap-3">
                <FormField label="Mulai" required :error="form.errors.start_date">
                    <input
                        v-model="form.start_date"
                        type="date"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>

                <FormField label="Target selesai" :error="form.errors.target_date">
                    <input
                        v-model="form.target_date"
                        type="date"
                        class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                    />
                </FormField>
            </div>

            <FormField
                label="Tanggal setoran tiap bulan"
                required
                :error="form.errors.due_day"
                hint="Dipakai sebagai jatuh tempo tagihan setoran."
            >
                <input
                    v-model="form.due_day"
                    type="number"
                    min="1"
                    max="31"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm tabular-nums ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

            <FormField label="Catatan" :error="form.errors.notes">
                <input
                    v-model="form.notes"
                    type="text"
                    maxlength="255"
                    class="w-full rounded-xl border-0 px-3 py-2.5 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
            </FormField>

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
