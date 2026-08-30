<script setup>
import { reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
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
});

const editing = ref(null);
const form = reactive({ group_id: '', subscription_plan_id: '', expires_at: '' });
const errors = reactive({});

function edit(subscription) {
    editing.value = subscription;
    form.group_id = subscription.group_id;
    form.subscription_plan_id = props.plans.find((p) => p.code === subscription.plan_code)?.id ?? '';
    form.expires_at = subscription.expires_at ?? '';
}

function submit() {
    router.put('/admin/subscriptions', form, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
        onError: (e) => Object.assign(errors, e),
    });
}

function toneFor(status) {
    return status === 'active'
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
        : status === 'canceled'
          ? 'bg-slate-100 text-slate-500 ring-slate-300'
          : 'bg-red-50 text-red-700 ring-red-600/20';
}
</script>

<template>
    <Head title="Admin - Subscription" />

    <div class="space-y-5">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-white">Manajemen Subscription</h1>
            <p class="mt-0.5 text-xs text-slate-400">{{ subscriptions.length }} tenant (kas bersama)</p>
        </div>

        <Card :padded="false">
            <ul v-if="subscriptions.length" class="divide-y divide-slate-100">
                <li v-for="sub in subscriptions" :key="sub.group_id" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 text-sm font-medium text-slate-800">
                            {{ sub.group_name }}
                            <span
                                v-if="sub.status_label"
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
                                :class="toneFor(sub.status)"
                            >
                                {{ sub.status_label }}
                            </span>
                        </p>
                        <p class="truncate text-xs text-slate-400">
                            {{ sub.members?.join(', ') || 'Belum ada anggota' }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center justify-between gap-4 sm:justify-end">
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-900">{{ sub.plan_name }}</p>
                            <p class="text-xs text-slate-400">
                                {{ sub.price > 0 ? formatRupiah(sub.price) + '/bln' : 'Gratis' }}
                                <span v-if="sub.expires_label"> - s/d {{ sub.expires_label }}</span>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                            @click="edit(sub)"
                        >
                            Ubah Paket
                        </button>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="Belum ada tenant" />
        </Card>

        <Modal :open="!!editing" title="Ubah Paket Langganan" @close="editing = null">
            <form class="space-y-4" @submit.prevent="submit">
                <FormField label="Tenant">
                    <select v-model="form.group_id" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200">
                        <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                    </select>
                </FormField>
                <FormField label="Paket" :error="errors.subscription_plan_id">
                    <select v-model="form.subscription_plan_id" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200">
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }} - {{ p.price > 0 ? formatRupiah(p.price) + '/bln' : 'Gratis' }}</option>
                    </select>
                </FormField>
                <FormField label="Kedaluwarsa (paket berbayar)" :error="errors.expires_at">
                    <input v-model="form.expires_at" type="date" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" />
                </FormField>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500" @click="editing = null">Batal</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Simpan</button>
                </div>
            </form>
        </Modal>
    </div>
</template>
