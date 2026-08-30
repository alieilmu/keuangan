<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Card from '../../../Components/Card.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    health: Object,
    checked_at: String,
});

function refresh() {
    router.reload({ only: ['health', 'checked_at'] });
}
</script>

<template>
    <Head title="Admin - System Health" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-white">System Health</h1>
                <p class="mt-0.5 text-xs text-slate-400">Diperiksa {{ checked_at }} - data nyata dari server ini</p>
            </div>
            <button type="button" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700" @click="refresh">
                Periksa Ulang
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <Card title="Database (MySQL)">
                <template v-if="health.database.ok">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Ukuran</dt><dd class="font-medium text-slate-800">{{ health.database.size_mb }} MB</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Jumlah Tabel</dt><dd class="font-medium text-slate-800">{{ health.database.table_count }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Koneksi Aktif</dt><dd class="font-medium text-slate-800">{{ health.database.connections }} / {{ health.database.max_connections }}</dd></div>
                    </dl>
                    <div class="mt-3 h-1.5 rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${Math.min(health.database.connection_usage_pct, 100)}%` }" />
                    </div>
                    <div v-if="health.database.largest_tables?.length" class="mt-4 space-y-1">
                        <p class="text-[11px] font-medium text-slate-400">Tabel terbesar</p>
                        <div v-for="t in health.database.largest_tables" :key="t.table" class="flex justify-between text-xs">
                            <span class="text-slate-600">{{ t.table }}</span>
                            <span class="text-slate-400">{{ t.rows }} baris</span>
                        </div>
                    </div>
                </template>
                <p v-else class="text-sm text-red-600">Gagal terhubung: {{ health.database.error }}</p>
            </Card>

            <Card title="Redis (Cache & Queue)">
                <template v-if="health.redis.ok">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Memori Terpakai</dt><dd class="font-medium text-slate-800">{{ health.redis.used_memory_mb }} MB</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Klien Terhubung</dt><dd class="font-medium text-slate-800">{{ health.redis.connected_clients }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Uptime</dt><dd class="font-medium text-slate-800">{{ health.redis.uptime_days }} hari</dd></div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Antrean Queue</dt>
                            <dd class="font-medium" :class="health.redis.queue_length > 0 ? 'text-amber-600' : 'text-slate-800'">
                                {{ health.redis.queue_length }} job menunggu
                            </dd>
                        </div>
                    </dl>
                </template>
                <p v-else class="text-sm text-red-600">Gagal terhubung: {{ health.redis.error }}</p>
            </Card>

            <Card title="Aplikasi (PHP)">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Versi PHP</dt><dd class="font-medium text-slate-800">{{ health.app.php_version }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Memori Terpakai</dt><dd class="font-medium text-slate-800">{{ health.app.memory_used_mb }} MB</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Batas Memori</dt><dd class="font-medium text-slate-800">{{ health.app.memory_limit }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">OPcache</dt><dd class="font-medium" :class="health.app.opcache_enabled ? 'text-emerald-600' : 'text-red-600'">{{ health.app.opcache_enabled ? 'Aktif' : 'Nonaktif' }}</dd></div>
                </dl>
            </Card>

            <Card title="Disk (Storage Aplikasi)">
                <template v-if="health.disk.ok">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Terpakai</dt><dd class="font-medium text-slate-800">{{ health.disk.used_gb }} GB / {{ health.disk.total_gb }} GB</dd></div>
                    </dl>
                    <div class="mt-3 h-1.5 rounded-full bg-slate-100">
                        <div
                            class="h-full rounded-full"
                            :class="health.disk.used_pct > 85 ? 'bg-red-500' : 'bg-emerald-500'"
                            :style="{ width: `${Math.min(health.disk.used_pct, 100)}%` }"
                        />
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">
                        Dilihat dari dalam container aplikasi, bukan kapasitas host VPS secara langsung.
                    </p>
                </template>
                <p v-else class="text-sm text-red-600">Gagal membaca: {{ health.disk.error }}</p>
            </Card>
        </div>
    </div>
</template>
