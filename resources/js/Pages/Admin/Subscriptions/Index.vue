<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/Card.vue';
import Modal from '../../../Components/Modal.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { formatRupiah } from '../../../lib/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    subscriptions: Array,
    plans: Array,
    groups: Array,
    unassigned_users: { type: Array, default: () => [] },
});

/* ---------------------------------------------------------------------
 * Ubah paket langganan (upgrade/downgrade)
 * ------------------------------------------------------------------- */
const editingSubscription = ref(null);
const subscriptionForm = useForm({ group_id: '', subscription_plan_id: '', expires_at: '' });

function editSubscription(sub) {
    editingSubscription.value = sub;
    subscriptionForm.clearErrors();
    subscriptionForm.group_id = sub.group_id;
    subscriptionForm.subscription_plan_id = props.plans.find((p) => p.code === sub.plan_code)?.id ?? '';
    subscriptionForm.expires_at = sub.expires_at ?? '';
}

function submitSubscription() {
    subscriptionForm.put('/admin/subscriptions', {
        preserveScroll: true,
        onSuccess: () => (editingSubscription.value = null),
    });
}

function toneFor(status) {
    return status === 'active'
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
        : status === 'canceled'
          ? 'bg-slate-100 text-slate-500 ring-slate-300'
          : 'bg-red-50 text-red-700 ring-red-600/20';
}

/* ---------------------------------------------------------------------
 * Katalog paket: kuota anggota & harga
 * ------------------------------------------------------------------- */
const editingPlan = ref(null);
const planForm = useForm({ name: '', price: 0, max_members: '' });

function editPlan(plan) {
    editingPlan.value = plan;
    planForm.clearErrors();
    planForm.name = plan.name;
    planForm.price = plan.price;
    planForm.max_members = plan.max_members ?? '';
}

function submitPlan() {
    planForm.transform((data) => ({ ...data, max_members: data.max_members === '' ? null : data.max_members })).put(
        `/admin/plans/${editingPlan.value.id}`,
        { preserveScroll: true, onSuccess: () => (editingPlan.value = null) },
    );
}

/* ---------------------------------------------------------------------
 * Kelola anggota grup, sesuai kuota paket
 * ------------------------------------------------------------------- */
// Menyimpan id saja (bukan objek snapshot) supaya modal otomatis
// menampilkan data terbaru setelah anggota ditambah/dikeluarkan --
// Inertia me-refresh props.subscriptions, dan computed ini ikut ter-update.
const managingGroupId = ref(null);
const managingGroup = computed(() => props.subscriptions.find((s) => s.group_id === managingGroupId.value) ?? null);
const addMemberForm = useForm({ user_id: '' });

function manageMembers(sub) {
    managingGroupId.value = sub.group_id;
    addMemberForm.clearErrors();
    addMemberForm.user_id = '';
}

function addMember() {
    addMemberForm.post(`/admin/groups/${managingGroupId.value}/members`, {
        preserveScroll: true,
        onSuccess: () => (addMemberForm.user_id = ''),
    });
}

function removeMember(userId) {
    if (!confirm('Keluarkan anggota ini dari grup? Data keuangannya tidak dihapus.')) {
        return;
    }

    useForm({}).delete(`/admin/groups/${managingGroupId.value}/members/${userId}`, { preserveScroll: true });
}

function quotaLabel(sub) {
    return sub.member_quota === null ? `${sub.member_count} anggota` : `${sub.member_count} / ${sub.member_quota} anggota`;
}

function quotaTone(sub) {
    if (sub.member_quota === null) {
        return 'bg-slate-100 text-slate-500';
    }

    return sub.quota_full ? 'bg-amber-100 text-amber-700' : 'bg-emerald-50 text-emerald-700';
}
</script>

<template>
    <Head title="Admin - Subscription" />

    <div class="space-y-5">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-white">Manajemen Subscription</h1>
            <p class="mt-0.5 text-xs text-slate-400">Paket, kuota anggota, dan keanggotaan tiap grup/keluarga.</p>
        </div>

        <!-- Katalog paket -->
        <Card title="Katalog Paket" subtitle="Kuota anggota & harga berlaku untuk semua grup pada paket tersebut">
            <ul class="divide-y divide-slate-100">
                <li v-for="plan in plans" :key="plan.id" class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 text-sm font-medium text-slate-800">
                            {{ plan.name }}
                            <span v-if="!plan.is_active" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">Nonaktif</span>
                        </p>
                        <p class="text-xs text-slate-400">
                            {{ plan.price > 0 ? formatRupiah(plan.price) + '/bulan' : 'Gratis' }}
                            - kuota {{ plan.max_members === null ? 'tanpa batas' : `${plan.max_members} anggota` }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                        @click="editPlan(plan)"
                    >
                        Ubah Kuota
                    </button>
                </li>
            </ul>
        </Card>

        <!-- Grup / keluarga -->
        <Card title="Grup & Keluarga" :subtitle="`${subscriptions.length} tenant`" :padded="false">
            <ul v-if="subscriptions.length" class="divide-y divide-slate-100">
                <li v-for="sub in subscriptions" :key="sub.group_id" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-800">
                            {{ sub.group_name }}
                            <span
                                v-if="sub.status_label"
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
                                :class="toneFor(sub.status)"
                            >
                                {{ sub.status_label }}
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="quotaTone(sub)">
                                {{ quotaLabel(sub) }}
                            </span>
                        </p>
                        <p class="truncate text-xs text-slate-400">
                            {{ sub.members?.map((m) => m.name).join(', ') || 'Belum ada anggota' }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 sm:justify-end">
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-900">{{ sub.plan_name }}</p>
                            <p class="text-xs text-slate-400">
                                {{ sub.price > 0 ? formatRupiah(sub.price) + '/bln' : 'Gratis' }}
                                <span v-if="sub.expires_label"> - s/d {{ sub.expires_label }}</span>
                            </p>
                            <p
                                v-if="sub.remaining_days !== null"
                                class="text-[11px] font-semibold"
                                :class="
                                    sub.expired
                                        ? 'text-red-600'
                                        : sub.remaining_days <= 2
                                          ? 'text-amber-600'
                                          : 'text-emerald-600'
                                "
                            >
                                {{ sub.expired ? 'Sudah berakhir' : `Sisa ${sub.remaining_days} hari aktif` }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                                @click="manageMembers(sub)"
                            >
                                Kelola Anggota
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                                @click="editSubscription(sub)"
                            >
                                Ubah Paket
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="Belum ada tenant" />
        </Card>

        <!-- Modal: ubah paket -->
        <Modal :open="!!editingSubscription" title="Ubah Paket Langganan" @close="editingSubscription = null">
            <form class="space-y-4" @submit.prevent="submitSubscription">
                <FormField label="Tenant">
                    <select v-model="subscriptionForm.group_id" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200">
                        <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                    </select>
                </FormField>
                <FormField label="Paket" :error="subscriptionForm.errors.subscription_plan_id">
                    <select v-model="subscriptionForm.subscription_plan_id" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200">
                        <option v-for="p in plans.filter((p) => p.is_active)" :key="p.id" :value="p.id">
                            {{ p.name }} - {{ p.price > 0 ? formatRupiah(p.price) + '/bln' : 'Gratis' }}
                            ({{ p.max_members === null ? 'tanpa batas' : `maks ${p.max_members}` }})
                        </option>
                    </select>
                </FormField>
                <FormField label="Kedaluwarsa (paket berbayar)" :error="subscriptionForm.errors.expires_at">
                    <input v-model="subscriptionForm.expires_at" type="date" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" />
                </FormField>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500" @click="editingSubscription = null">Batal</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700" :disabled="subscriptionForm.processing">Simpan</button>
                </div>
            </form>
        </Modal>

        <!-- Modal: ubah kuota paket -->
        <Modal :open="!!editingPlan" title="Ubah Kuota Paket" @close="editingPlan = null">
            <form class="space-y-4" @submit.prevent="submitPlan">
                <FormField label="Nama Paket" :error="planForm.errors.name">
                    <input v-model="planForm.name" type="text" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" />
                </FormField>
                <FormField label="Harga per Bulan (Rp)" :error="planForm.errors.price">
                    <input v-model.number="planForm.price" type="number" min="0" step="1000" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" />
                </FormField>
                <FormField label="Kuota Anggota" hint="Kosongkan untuk tanpa batas" :error="planForm.errors.max_members">
                    <input v-model="planForm.max_members" type="number" min="1" placeholder="Tanpa batas" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" />
                </FormField>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500" @click="editingPlan = null">Batal</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700" :disabled="planForm.processing">Simpan</button>
                </div>
            </form>
        </Modal>

        <!-- Modal: kelola anggota -->
        <Modal :open="!!managingGroup" :title="`Anggota ${managingGroup?.group_name ?? ''}`" @close="managingGroupId = null">
            <div v-if="managingGroup" class="space-y-4">
                <p class="text-xs text-slate-500">
                    {{ quotaLabel(managingGroup) }}
                    <span v-if="managingGroup.quota_full" class="font-medium text-amber-600"> - kuota penuh</span>
                </p>

                <ul class="space-y-1.5">
                    <li
                        v-for="member in managingGroup.members"
                        :key="member.id"
                        class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm"
                    >
                        <span class="text-slate-700">{{ member.name }}</span>
                        <button
                            type="button"
                            class="text-xs font-medium text-red-600 hover:underline"
                            @click="removeMember(member.id)"
                        >
                            Keluarkan
                        </button>
                    </li>
                    <li v-if="!managingGroup.members?.length" class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-400">
                        Belum ada anggota.
                    </li>
                </ul>

                <form class="flex items-end gap-2 border-t border-slate-100 pt-4" @submit.prevent="addMember">
                    <FormField label="Tambah anggota" class="flex-1" :error="addMemberForm.errors.user_id">
                        <select v-model="addMemberForm.user_id" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200">
                            <option value="" disabled>Pilih user tanpa grup...</option>
                            <option v-for="u in unassigned_users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                        </select>
                    </FormField>
                    <button
                        type="submit"
                        class="shrink-0 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="!addMemberForm.user_id || addMemberForm.processing || managingGroup.quota_full"
                    >
                        Tambah
                    </button>
                </form>
                <p v-if="!unassigned_users.length" class="text-xs text-slate-400">
                    Tidak ada user tanpa grup saat ini.
                </p>
            </div>
        </Modal>
    </div>
</template>
