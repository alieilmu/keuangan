<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/Card.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import Modal from '../../../Components/Modal.vue';
import FormField from '../../../Components/FormField.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    feedback: Object,
    filters: Object,
    counts: Object,
    statuses: Array,
});

const TABS = [{ value: null, label: 'Semua' }, ...props.statuses];

const tone = (status) =>
    status === 'new'
        ? 'bg-sky-100 text-sky-700'
        : status === 'read'
          ? 'bg-amber-100 text-amber-700'
          : 'bg-emerald-100 text-emerald-700';

function filter(status) {
    router.get('/admin/feedback', status ? { status } : {}, { preserveScroll: true, preserveState: true });
}

function setStatus(item, status) {
    router.put(`/admin/feedback/${item.id}`, { status, admin_note: item.admin_note }, { preserveScroll: true });
}

const editing = ref(null);
const form = useForm({ status: 'read', admin_note: '' });

function edit(item) {
    editing.value = item;
    form.clearErrors();
    form.status = item.status;
    form.admin_note = item.admin_note ?? '';
}

function submit() {
    form.put(`/admin/feedback/${editing.value.id}`, { preserveScroll: true, onSuccess: () => (editing.value = null) });
}
</script>

<template>
    <Head title="Admin - Saran Pengguna" />

    <div class="space-y-5">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-white">Saran Pengguna</h1>
            <p class="mt-0.5 text-xs text-slate-400">Pesan yang dikirim pengguna lewat menu "Kirim Saran".</p>
        </div>

        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="tab in TABS"
                :key="tab.label"
                type="button"
                class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                :class="(filters.status ?? null) === tab.value ? 'bg-emerald-500 text-slate-900' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                @click="filter(tab.value)"
            >
                {{ tab.label }}
                <span v-if="tab.value && counts[tab.value]" class="ml-1 opacity-70">{{ counts[tab.value] }}</span>
            </button>
        </div>

        <Card :padded="false">
            <ul v-if="feedback.data.length" class="divide-y divide-slate-100">
                <li v-for="item in feedback.data" :key="item.id" class="space-y-2 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-slate-800">{{ item.user_name ?? 'Akun terhapus' }}</span>
                        <span class="text-[11px] text-slate-400">{{ item.user_email }}<span v-if="item.group_name"> - {{ item.group_name }}</span></span>
                        <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ item.category_label }}</span>
                        <span class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold" :class="tone(item.status)">{{ item.status_label }}</span>
                        <span class="ml-auto text-[11px] text-slate-400">{{ item.created_label }}</span>
                    </div>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ item.message }}</p>
                    <p v-if="item.admin_note" class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        <span class="font-semibold">Catatan admin<span v-if="item.handler_name"> ({{ item.handler_name }})</span>:</span>
                        {{ item.admin_note }}
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-if="item.status === 'new'"
                            type="button"
                            class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200"
                            @click="setStatus(item, 'read')"
                        >
                            Tandai dibaca
                        </button>
                        <button
                            v-if="item.status !== 'resolved'"
                            type="button"
                            class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                            @click="setStatus(item, 'resolved')"
                        >
                            Selesai
                        </button>
                        <button type="button" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200" @click="edit(item)">
                            Catatan
                        </button>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="Belum ada saran" description="Saran dari pengguna akan muncul di sini." />
        </Card>

        <div v-if="feedback.links.length > 3" class="flex flex-wrap gap-1.5">
            <Link
                v-for="link in feedback.links"
                :key="link.label"
                :href="link.url ?? '#'"
                v-html="link.label"
                class="rounded-lg px-3 py-1.5 text-xs font-medium"
                :class="[link.active ? 'bg-emerald-500 text-slate-900' : 'bg-white/5 text-slate-300 hover:bg-white/10', !link.url && 'pointer-events-none opacity-40']"
            />
        </div>

        <Modal :open="!!editing" title="Tindak Lanjut Saran" @close="editing = null">
            <form class="space-y-4" @submit.prevent="submit">
                <FormField label="Status" :error="form.errors.status">
                    <select v-model="form.status" class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200">
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </FormField>
                <FormField label="Catatan internal" :error="form.errors.admin_note">
                    <textarea v-model="form.admin_note" rows="4" maxlength="1000" class="w-full resize-none rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200" />
                </FormField>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500" @click="editing = null">Batal</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </Modal>
    </div>
</template>
