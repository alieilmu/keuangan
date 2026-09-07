<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/Card.vue';
import EmptyState from '../../../Components/EmptyState.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users: Object,
    filters: Object,
});

const page = usePage();
const filters = reactive({ search: props.filters.search ?? '' });

let debounce = null;
watch(
    () => filters.search,
    (value) => {
        window.clearTimeout(debounce);
        debounce = window.setTimeout(() => {
            router.get('/admin/users', value ? { search: value } : {}, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
);

function toggleStatus(user) {
    const verb = user.is_blocked ? 'mengaktifkan kembali' : 'memblokir';

    if (!confirm(`Yakin ingin ${verb} akun ${user.name}?`)) {
        return;
    }

    router.post(`/admin/users/${user.id}/toggle-status`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin - Manajemen Akun" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-white">Manajemen Akun</h1>
                <p class="mt-0.5 text-xs text-slate-400">{{ users.total }} user terdaftar</p>
            </div>
            <input
                v-model="filters.search"
                type="search"
                placeholder="Cari nama / email..."
                class="w-56 rounded-xl border-0 bg-white px-3 py-2 text-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500"
            />
        </div>

        <div
            v-if="page.props.flash.generated_password"
            class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-300"
        >
            <p class="text-sm font-semibold text-amber-900">Kata sandi baru dibuat</p>
            <p class="mt-1 text-xs text-amber-800">
                Sampaikan kepada pengguna secara langsung -- tidak dikirim otomatis lewat email.
            </p>
            <code class="mt-2 block w-fit rounded-lg bg-white px-3 py-1.5 font-mono text-sm text-slate-900 ring-1 ring-amber-200">
                {{ page.props.flash.generated_password }}
            </code>
        </div>

        <Card :padded="false">
            <ul v-if="users.data.length" class="divide-y divide-slate-100">
                <li v-for="user in users.data" :key="user.id" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                            {{ user.initials }}
                        </span>
                        <div class="min-w-0">
                            <p class="flex items-center gap-1.5 truncate text-sm font-medium text-slate-800">
                                <Link :href="`/admin/users/${user.id}`" class="hover:underline">{{ user.name }}</Link>
                                <span v-if="user.is_admin" class="shrink-0 rounded-md bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700">Admin</span>
                                <span v-if="user.is_blocked" class="shrink-0 rounded-md bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">Diblokir</span>
                            </p>
                            <p class="flex flex-wrap items-center gap-1.5 truncate text-xs text-slate-400">
                                <span class="truncate">{{ user.email }} - {{ user.plan_name }}</span>
                                <span
                                    v-if="user.is_demo"
                                    class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                                    :class="
                                        user.expired
                                            ? 'bg-red-100 text-red-700'
                                            : user.remaining_days <= 2
                                              ? 'bg-amber-100 text-amber-700'
                                              : 'bg-emerald-100 text-emerald-700'
                                    "
                                >
                                    {{ user.expired ? 'Demo habis' : `Demo ${user.remaining_days} hari lagi` }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center justify-between gap-3 pl-12 sm:justify-end sm:pl-0">
                        <p class="text-xs text-slate-500">{{ user.accounts_count }} akun - {{ user.transactions_count }} transaksi</p>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                                @click="router.post(`/admin/users/${user.id}/reset-password`, {}, { preserveScroll: true })"
                            >
                                Reset Sandi
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                :class="user.is_blocked ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-red-50 text-red-600 hover:bg-red-100'"
                                @click="toggleStatus(user)"
                            >
                                {{ user.is_blocked ? 'Aktifkan' : 'Blokir' }}
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="Tidak ada user" description="Tidak ditemukan user yang cocok dengan pencarian." />
        </Card>

        <div v-if="users.links.length > 3" class="flex flex-wrap gap-1.5">
            <Link
                v-for="link in users.links"
                :key="link.label"
                :href="link.url ?? '#'"
                v-html="link.label"
                class="rounded-lg px-3 py-1.5 text-xs font-medium"
                :class="[
                    link.active ? 'bg-emerald-500 text-slate-900' : 'bg-white/5 text-slate-300 hover:bg-white/10',
                    !link.url && 'pointer-events-none opacity-40',
                ]"
            />
        </div>
    </div>
</template>
