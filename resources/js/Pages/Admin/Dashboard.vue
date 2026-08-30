<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Card from '../../Components/Card.vue';
import { formatNumber, formatPercent, formatRupiah } from '../../lib/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    summary: Object,
    total_admins: Number,
    blocked_users: Number,
    total_groups: Number,
    health_ok: Boolean,
});
</script>

<template>
    <Head title="Admin - Ringkasan" />

    <div class="space-y-5">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-white">Ringkasan Bisnis</h1>
            <p class="mt-0.5 text-xs text-slate-400">Kondisi platform saat ini, real-time dari data produksi.</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card><p class="text-xs text-slate-500">MRR</p><p class="mt-1 text-xl font-bold text-slate-900">{{ formatRupiah(summary.mrr) }}</p></Card>
            <Card><p class="text-xs text-slate-500">Churn Rate Bulan Ini</p><p class="mt-1 text-xl font-bold text-slate-900">{{ formatPercent(summary.churn_rate) }}</p></Card>
            <Card><p class="text-xs text-slate-500">Total User</p><p class="mt-1 text-xl font-bold text-slate-900">{{ formatNumber(summary.total_users) }}</p></Card>
            <Card><p class="text-xs text-slate-500">Tenant Berbayar</p><p class="mt-1 text-xl font-bold text-slate-900">{{ summary.paying_tenants }} / {{ summary.total_tenants }}</p></Card>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <Link href="/admin/users" class="block">
                <Card title="Manajemen Akun" subtitle="Lihat & kelola user">
                    <p class="text-sm text-slate-600">
                        {{ blocked_users }} akun diblokir - {{ total_admins }} admin
                    </p>
                </Card>
            </Link>
            <Link href="/admin/subscriptions" class="block">
                <Card title="Subscription" subtitle="Paket & tanggal kedaluwarsa">
                    <p class="text-sm text-slate-600">Kelola upgrade/downgrade tenant</p>
                </Card>
            </Link>
            <Link href="/admin/system-health" class="block">
                <Card title="System Health" subtitle="VPS, Redis, database">
                    <p class="flex items-center gap-1.5 text-sm">
                        <span class="size-2 rounded-full" :class="health_ok ? 'bg-emerald-500' : 'bg-red-500'" />
                        <span :class="health_ok ? 'text-emerald-700' : 'text-red-700'">
                            {{ health_ok ? 'Sehat' : 'Perlu diperiksa' }}
                        </span>
                    </p>
                </Card>
            </Link>
        </div>
    </div>
</template>
