<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/Card.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Modal from '../../../Components/Modal.vue';
import FormField from '../../../Components/FormField.vue';
import MoneyInput from '../../../Components/MoneyInput.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    coupons: Array,
    types: Array,
    plans: { type: Array, default: () => [] },
});

const editing = ref(null);
const open = ref(false);
const form = useForm({ code: '', description: '', type: 'percent', value: '', max_uses: '', starts_at: '', ends_at: '', plan_ids: [] });
const inputClass = 'w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200';

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function edit(coupon) {
    editing.value = coupon;
    form.clearErrors();
    Object.assign(form, {
        code: coupon.code,
        description: coupon.description ?? '',
        type: coupon.type,
        value: coupon.value,
        max_uses: coupon.max_uses ?? '',
        starts_at: coupon.starts_at ?? '',
        ends_at: coupon.ends_at ?? '',
        plan_ids: [...coupon.plan_ids],
    });
    open.value = true;
}

function submit() {
    const payload = (data) => ({ ...data, max_uses: data.max_uses === '' ? null : data.max_uses });
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editing.value) {
        form.transform(payload).put(`/admin/coupons/${editing.value.id}`, options);
    } else {
        form.transform(payload).post('/admin/coupons', options);
    }
}

function toggle(coupon) {
    router.put(`/admin/coupons/${coupon.id}`, { toggle_only: true }, { preserveScroll: true });
}

function destroy(coupon) {
    if (!confirm(`Hapus kode ${coupon.code}? Kode yang pernah dipakai hanya dinonaktifkan.`)) {
        return;
    }

    router.delete(`/admin/coupons/${coupon.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin - Kode Diskon" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-white">Kode Diskon</h1>
                <p class="mt-0.5 text-xs text-slate-400">Dipakai pengguna saat membeli paket atau slot anggota.</p>
            </div>
            <button type="button" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700" @click="create">
                + Kode Diskon
            </button>
        </div>

        <Card :padded="false">
            <ul v-if="coupons.length" class="divide-y divide-slate-100">
                <li v-for="coupon in coupons" :key="coupon.id" class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-sm font-bold text-slate-900">{{ coupon.code }}</span>
                            <span class="text-xs text-slate-600">{{ coupon.summary }}</span>
                            <span
                                class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                                :class="coupon.usable ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ coupon.usable ? 'Berlaku' : coupon.is_active ? 'Tidak berlaku' : 'Nonaktif' }}
                            </span>
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <span v-if="!coupon.plan_names.length" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">
                                Semua paket
                            </span>
                            <span
                                v-for="name in coupon.plan_names"
                                :key="name"
                                class="rounded-md bg-sky-50 px-1.5 py-0.5 text-[10px] font-medium text-sky-700"
                            >
                                {{ name }}
                            </span>
                        </div>
                        <p class="truncate text-[11px] text-slate-400">
                            {{ coupon.used }}{{ coupon.max_uses ? ` / ${coupon.max_uses}` : '' }} dipakai - {{ coupon.period_label }}
                            <span v-if="coupon.description"> - {{ coupon.description }}</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        <button type="button" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200" @click="toggle(coupon)">
                            {{ coupon.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                        <button type="button" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200" @click="edit(coupon)">Ubah</button>
                        <button type="button" class="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-100" @click="destroy(coupon)">Hapus</button>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="Belum ada kode diskon" />
        </Card>

        <Modal :open="open" :title="editing ? 'Ubah Kode Diskon' : 'Kode Diskon Baru'" @close="open = false">
            <form class="space-y-3" @submit.prevent="submit">
                <FormField label="Kode" required :error="form.errors.code" hint="Huruf, angka, strip, atau garis bawah">
                    <input v-model="form.code" type="text" maxlength="30" :class="[inputClass, 'font-mono uppercase']" />
                </FormField>
                <FormField label="Keterangan" :error="form.errors.description">
                    <input v-model="form.description" type="text" maxlength="120" :class="inputClass" />
                </FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Jenis" :error="form.errors.type">
                        <select v-model="form.type" :class="inputClass">
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </FormField>
                    <FormField :label="form.type === 'percent' ? 'Diskon (%)' : 'Potongan (Rp)'" required :error="form.errors.value">
                        <MoneyInput v-if="form.type === 'fixed'" v-model="form.value" placeholder="0" :class="inputClass" />
                        <input v-else v-model="form.value" type="number" min="1" max="100" :class="inputClass" />
                    </FormField>
                </div>
                <FormField
                    label="Berlaku untuk paket"
                    :hint="form.plan_ids.length ? `Hanya ${form.plan_ids.length} paket terpilih` : 'Tidak ada yang dicentang = berlaku untuk semua paket'"
                    :error="form.errors.plan_ids || form.errors['plan_ids.0']"
                >
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="plan in plans"
                            :key="plan.id"
                            class="flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs ring-1 transition"
                            :class="form.plan_ids.includes(plan.id) ? 'bg-emerald-50 text-emerald-700 ring-emerald-500' : 'bg-white text-slate-600 ring-slate-200'"
                        >
                            <input v-model="form.plan_ids" type="checkbox" :value="plan.id" class="size-3.5 accent-emerald-600" />
                            {{ plan.name }}
                        </label>
                    </div>
                </FormField>
                <FormField label="Batas pemakaian" hint="Kosongkan untuk tanpa batas" :error="form.errors.max_uses">
                    <input v-model="form.max_uses" type="number" min="1" :class="inputClass" />
                </FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Mulai berlaku" :error="form.errors.starts_at">
                        <input v-model="form.starts_at" type="date" :class="inputClass" />
                    </FormField>
                    <FormField label="Berakhir" :error="form.errors.ends_at">
                        <input v-model="form.ends_at" type="date" :class="inputClass" />
                    </FormField>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500" @click="open = false">Batal</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </div>
</template>
