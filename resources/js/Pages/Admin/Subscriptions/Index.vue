<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/Card.vue';
import Modal from '../../../Components/Modal.vue';
import FormField from '../../../Components/FormField.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import MoneyInput from '../../../Components/MoneyInput.vue';
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
const creatingPlan = ref(false);
const planForm = useForm({ name: '', price: 0, max_members: '', extra_member_price: 0 });

function editPlan(plan) {
    creatingPlan.value = false;
    editingPlan.value = plan;
    planForm.clearErrors();
    planForm.name = plan.name;
    planForm.price = plan.price;
    planForm.max_members = plan.max_members ?? '';
    planForm.extra_member_price = plan.extra_member_price ?? 0;
}

function createPlan() {
    editingPlan.value = null;
    planForm.reset();
    planForm.clearErrors();
    creatingPlan.value = true;
}

function submitPlan() {
    const payload = (data) => ({
        ...data,
        price: Number(data.price) || 0,
        extra_member_price: Number(data.extra_member_price) || 0,
        max_members: data.max_members === '' ? null : data.max_members,
    });
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editingPlan.value = null;
            creatingPlan.value = false;
        },
    };

    if (creatingPlan.value) {
        planForm.transform(payload).post('/admin/plans', options);
    } else {
        planForm.transform(payload).put(`/admin/plans/${editingPlan.value.id}`, options);
    }
}

/* ---------------------------------------------------------------------
 * Opsi durasi per paket (1/3/6/12 bulan ...)
 * ------------------------------------------------------------------- */
const pricingPlanId = ref(null);
const pricingPlan = computed(() => props.plans.find((p) => p.id === pricingPlanId.value) ?? null);
const priceEdits = reactive({});
const newPriceForm = useForm({ months: 3, price: '' });
const DURATIONS = [1, 3, 6, 12];

function managePrices(plan) {
    pricingPlanId.value = plan.id;
    newPriceForm.reset();
    newPriceForm.clearErrors();
    Object.keys(priceEdits).forEach((key) => delete priceEdits[key]);
    plan.prices.forEach((price) => (priceEdits[price.id] = price.price));
    newPriceForm.months = DURATIONS.find((m) => !plan.prices.some((p) => p.months === m)) ?? 3;
}

function savePrice(price, isActive = price.is_active) {
    router.put(
        `/admin/plan-prices/${price.id}`,
        { price: Number(priceEdits[price.id]) || price.price, is_active: isActive },
        { preserveScroll: true },
    );
}

function deletePrice(price) {
    if (!confirm(`Hapus opsi ${price.months} bulan?`)) {
        return;
    }

    router.delete(`/admin/plan-prices/${price.id}`, {
        preserveScroll: true,
        onSuccess: () => delete priceEdits[price.id],
    });
}

function addPrice() {
    newPriceForm.post(`/admin/plans/${pricingPlanId.value}/prices`, {
        preserveScroll: true,
        onSuccess: () => {
            pricingPlan.value?.prices.forEach((price) => (priceEdits[price.id] ??= price.price));
            newPriceForm.reset('price');
        },
    });
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
        <Card title="Katalog Paket" subtitle="Kuota anggota, harga, dan opsi durasi yang bisa dibeli pengguna">
            <template #actions>
                <button
                    type="button"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                    @click="createPlan"
                >
                    + Paket Baru
                </button>
            </template>
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
                            <span v-if="plan.extra_member_price"> - slot tambahan {{ formatRupiah(plan.extra_member_price) }}/bln</span>
                        </p>
                        <div v-if="plan.prices.length" class="mt-1.5 flex flex-wrap gap-1">
                            <span
                                v-for="price in plan.prices"
                                :key="price.id"
                                class="rounded-md px-1.5 py-0.5 text-[10px] font-medium"
                                :class="price.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400 line-through'"
                            >
                                {{ price.months }} bln - {{ formatRupiah(price.price) }}
                            </span>
                        </div>
                        <p v-else-if="plan.code !== 'demo'" class="mt-1 text-[10px] text-amber-600">Belum ada opsi durasi, paket tidak bisa dibeli.</p>
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        <button
                            v-if="plan.code !== 'demo'"
                            type="button"
                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                            @click="managePrices(plan)"
                        >
                            Durasi &amp; Harga
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                            @click="editPlan(plan)"
                        >
                            Ubah Kuota
                        </button>
                    </div>
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
        <Modal
            :open="!!editingPlan || creatingPlan"
            :title="creatingPlan ? 'Paket Baru' : 'Ubah Kuota Paket'"
            @close="editingPlan = null; creatingPlan = false"
        >
            <form class="space-y-4" @submit.prevent="submitPlan">
                <FormField label="Nama Paket" :error="planForm.errors.name">
                    <input v-model="planForm.name" type="text" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" />
                </FormField>
                <FormField label="Harga per Bulan (Rp)" :error="planForm.errors.price">
                    <MoneyInput v-model="planForm.price" placeholder="0" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" />
                </FormField>
                <FormField label="Kuota Anggota" hint="Kosongkan untuk tanpa batas" :error="planForm.errors.max_members">
                    <input v-model="planForm.max_members" type="number" min="1" placeholder="Tanpa batas" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" />
                </FormField>
                <FormField
                    label="Harga slot anggota tambahan (Rp/anggota/bulan)"
                    hint="0 = slot tambahan tidak dijual. Hanya berlaku untuk paket berkuota."
                    :error="planForm.errors.extra_member_price"
                >
                    <MoneyInput v-model="planForm.extra_member_price" placeholder="0" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" />
                </FormField>
                <p v-if="creatingPlan" class="text-[11px] text-slate-500">
                    Paket berbayar otomatis mendapat opsi durasi 1 bulan. Tambahkan durasi lain lewat tombol "Durasi &amp; Harga".
                </p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500" @click="editingPlan = null; creatingPlan = false">Batal</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700" :disabled="planForm.processing">Simpan</button>
                </div>
            </form>
        </Modal>

        <!-- Modal: opsi durasi & harga -->
        <Modal :open="!!pricingPlan" :title="`Durasi & Harga - ${pricingPlan?.name ?? ''}`" @close="pricingPlanId = null">
            <div v-if="pricingPlan" class="space-y-4">
                <p class="text-xs text-slate-500">
                    Harga adalah total yang dibayar untuk seluruh durasi. Contoh: 12 bulan Rp500.000 berarti sekitar
                    Rp41.667 per bulan.
                </p>
                <ul class="space-y-2">
                    <li v-for="price in pricingPlan.prices" :key="price.id" class="flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 px-3 py-2">
                        <span class="w-16 text-xs font-semibold text-slate-700">{{ price.months }} bulan</span>
                        <MoneyInput
                            v-model="priceEdits[price.id]"
                            class="min-w-0 flex-1 rounded-lg border-0 bg-white px-2.5 py-1.5 text-sm ring-1 ring-slate-200"
                        />
                        <span class="text-[10px] text-slate-400">{{ formatRupiah(Math.round((Number(priceEdits[price.id]) || 0) / price.months)) }}/bln</span>
                        <div class="flex gap-1">
                            <button type="button" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700" @click="savePrice(price)">Simpan</button>
                            <button
                                type="button"
                                class="rounded-lg bg-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-300"
                                @click="savePrice(price, !price.is_active)"
                            >
                                {{ price.is_active ? 'Sembunyikan' : 'Tampilkan' }}
                            </button>
                            <button type="button" class="rounded-lg px-2 py-1 text-[11px] font-semibold text-red-600 hover:bg-red-50" @click="deletePrice(price)">Hapus</button>
                        </div>
                    </li>
                    <li v-if="!pricingPlan.prices.length" class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-400">Belum ada opsi durasi.</li>
                </ul>

                <form class="space-y-2 border-t border-slate-100 pt-4" @submit.prevent="addPrice">
                    <p class="text-xs font-medium text-slate-600">Tambah opsi durasi</p>
                    <div class="flex flex-wrap items-end gap-2">
                        <select v-model.number="newPriceForm.months" class="rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200">
                            <option v-for="m in [1, 3, 6, 12, 24]" :key="m" :value="m" :disabled="pricingPlan.prices.some((p) => p.months === m)">
                                {{ m }} bulan
                            </option>
                        </select>
                        <MoneyInput
                            v-model="newPriceForm.price"
                            placeholder="Harga total"
                            class="min-w-0 flex-1 rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200"
                        />
                        <button type="submit" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700" :disabled="newPriceForm.processing">
                            Tambah
                        </button>
                    </div>
                    <p v-if="newPriceForm.errors.months || newPriceForm.errors.price" class="text-xs text-red-600">
                        {{ newPriceForm.errors.months || newPriceForm.errors.price }}
                    </p>
                </form>
            </div>
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
