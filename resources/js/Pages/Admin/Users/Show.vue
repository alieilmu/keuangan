<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/Card.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ user: Object });
const page = usePage();

function destroy() {
    const typed = prompt(
        `Hapus akun ${props.user.name} beserta SELURUH data keuangannya?\n\n`
            + `Tindakan ini permanen dan tidak bisa dibatalkan.\n`
            + `Ketik nama akun untuk mengonfirmasi:`,
    );

    if (typed !== props.user.name) {
        if (typed !== null) {
            alert('Nama tidak cocok. Penghapusan dibatalkan.');
        }

        return;
    }

    router.delete(`/admin/users/${props.user.id}`);
}

function toggleStatus() {
    const verb = props.user.is_blocked ? 'mengaktifkan kembali' : 'memblokir';

    if (!confirm(`Yakin ingin ${verb} akun ${props.user.name}?`)) {
        return;
    }

    router.post(`/admin/users/${props.user.id}/toggle-status`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Admin - ${user.name}`" />

    <div class="space-y-5">
        <Link href="/admin/users" class="text-xs font-medium text-slate-400 hover:text-slate-200">&larr; Kembali ke daftar akun</Link>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="grid size-12 place-items-center rounded-full bg-emerald-50 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                    {{ user.initials }}
                </span>
                <div>
                    <p class="flex items-center gap-2 text-lg font-semibold text-white">
                        {{ user.name }}
                        <span v-if="user.is_admin" class="rounded-md bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700">Admin</span>
                        <span v-if="user.is_blocked" class="rounded-md bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">Diblokir</span>
                    </p>
                    <p class="text-xs text-slate-400">{{ user.email }} - bergabung {{ user.created_at }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-200"
                    @click="router.post(`/admin/users/${user.id}/reset-password`, {}, { preserveScroll: true })"
                >
                    Reset Kata Sandi
                </button>
                <button
                    type="button"
                    class="rounded-xl px-3.5 py-2 text-xs font-semibold transition"
                    :class="user.is_blocked ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-red-50 text-red-600 hover:bg-red-100'"
                    @click="toggleStatus"
                >
                    {{ user.is_blocked ? 'Aktifkan Akun' : 'Blokir Akun' }}
                </button>
                <button
                    v-if="!user.is_admin"
                    type="button"
                    class="rounded-xl bg-red-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-red-700"
                    @click="destroy"
                >
                    Hapus Akun
                </button>
            </div>
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

        <div class="grid gap-3 sm:grid-cols-3">
            <Card><p class="text-xs text-slate-500">Akun Dana</p><p class="mt-1 text-xl font-bold text-slate-900">{{ user.accounts_count }}</p></Card>
            <Card><p class="text-xs text-slate-500">Transaksi</p><p class="mt-1 text-xl font-bold text-slate-900">{{ user.transactions_count }}</p></Card>
            <Card><p class="text-xs text-slate-500">Tagihan</p><p class="mt-1 text-xl font-bold text-slate-900">{{ user.bills_count }}</p></Card>
        </div>

        <Card title="Tenant / Kas Bersama" :subtitle="user.group_name ?? 'Belum tergabung grup'">
            <p class="text-sm text-slate-600">Paket saat ini: <strong>{{ user.plan_name }}</strong></p>
            <div v-if="user.group_members?.length" class="mt-3 flex flex-wrap gap-1.5">
                <span
                    v-for="member in user.group_members"
                    :key="member.id"
                    class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
                >
                    {{ member.name }}
                </span>
            </div>
        </Card>
    </div>
</template>
