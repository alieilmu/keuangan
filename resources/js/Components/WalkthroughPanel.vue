<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Panel panduan interaktif untuk pengguna baru.
 *
 * Menempel di bawah layar (di atas navigasi mobile) dan mengikuti pengguna
 * berpindah halaman. Kemajuannya dibaca dari data nyata di server -- kalau
 * pengguna sudah membuat akun dana, langkahnya otomatis ditandai selesai
 * meski ia membuatnya tanpa menekan tombol apa pun di panel ini.
 */
const page = usePage();
const collapsed = ref(false);

const tour = computed(() => page.props.walkthrough ?? null);
const currentPath = computed(() => page.url.split('?')[0]);
const onTargetPage = computed(() => tour.value && currentPath.value === tour.value.route);

const progressPct = computed(() =>
    tour.value ? Math.round(((tour.value.step - 1) / (tour.value.total - 1)) * 100) : 0,
);

function goToStepPage() {
    router.get(tour.value.route);
}

function advance() {
    router.post('/walkthrough/advance', {}, { preserveScroll: true });
}

function skip() {
    if (!confirm('Tutup panduan? Anda bisa mencatat sendiri tanpa panduan ini.')) {
        return;
    }

    router.post('/walkthrough/skip', {}, { preserveScroll: true });
}

function finish(choice) {
    const message =
        choice === 'reset'
            ? 'Kosongkan semua data latihan tadi? Akun dana & kategori bawaan akan disiapkan ulang. Tindakan ini tidak bisa dibatalkan.'
            : 'Simpan data yang tadi dibuat dan tutup panduan?';

    if (!confirm(message)) {
        return;
    }

    router.post('/walkthrough/finish', { choice });
}
</script>

<template>
    <div
        v-if="tour"
        class="fixed inset-x-0 bottom-16 z-40 px-3 pb-2 sm:bottom-4 sm:left-auto sm:right-4 sm:w-96 sm:px-0"
    >
        <div class="overflow-hidden rounded-2xl bg-slate-900 text-white shadow-2xl ring-1 ring-white/10">
            <!-- Kepala: progres + tombol lipat -->
            <div class="flex items-center gap-2 border-b border-white/10 px-4 py-2.5">
                <span class="grid size-6 shrink-0 place-items-center rounded-lg bg-emerald-500 text-[11px] font-bold text-slate-900">
                    {{ tour.step }}
                </span>
                <p class="min-w-0 flex-1 truncate text-xs font-semibold">
                    Panduan {{ tour.step }} dari {{ tour.total }}
                </p>
                <button
                    type="button"
                    class="shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-white/10 hover:text-white"
                    :aria-label="collapsed ? 'Buka panduan' : 'Kecilkan panduan'"
                    @click="collapsed = !collapsed"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4">
                        <path :d="collapsed ? 'M18 15l-6-6-6 6' : 'M6 9l6 6 6-6'" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>

            <div v-if="!collapsed">
                <!-- Bar progres -->
                <div class="h-1 bg-white/10">
                    <div class="h-full bg-emerald-500 transition-all duration-300" :style="{ width: `${progressPct}%` }" />
                </div>

                <div class="space-y-3 px-4 py-3.5">
                    <div>
                        <p class="text-sm font-semibold">{{ tour.title }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-300">{{ tour.body }}</p>
                    </div>

                    <!-- Petunjuk aksi, hanya saat pengguna sudah di halaman yang tepat -->
                    <p
                        v-if="tour.hint && onTargetPage && !tour.satisfied"
                        class="rounded-lg bg-white/5 px-3 py-2 text-[11px] leading-relaxed text-amber-300 ring-1 ring-inset ring-amber-400/20"
                    >
                        {{ tour.hint }}
                    </p>

                    <!-- Tanda langkah sudah terpenuhi -->
                    <p
                        v-if="tour.satisfied && !tour.is_final && tour.auto"
                        class="flex items-center gap-1.5 text-[11px] font-medium text-emerald-400"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="size-3.5">
                            <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Langkah ini sudah selesai.
                    </p>

                    <!-- Aksi langkah terakhir: pilih simpan atau kosongkan -->
                    <div v-if="tour.is_final" class="space-y-2">
                        <button
                            type="button"
                            class="w-full rounded-xl bg-emerald-500 px-3.5 py-2.5 text-xs font-semibold text-slate-900 transition hover:bg-emerald-400"
                            @click="finish('keep')"
                        >
                            Lanjutkan dengan data ini
                        </button>
                        <button
                            type="button"
                            class="w-full rounded-xl bg-white/10 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-white/20"
                            @click="finish('reset')"
                        >
                            Kosongkan &amp; mulai dari nol
                        </button>
                    </div>

                    <!-- Aksi langkah biasa -->
                    <div v-else class="flex items-center gap-2">
                        <button
                            v-if="!onTargetPage"
                            type="button"
                            class="flex-1 rounded-xl bg-emerald-500 px-3.5 py-2 text-xs font-semibold text-slate-900 transition hover:bg-emerald-400"
                            @click="goToStepPage"
                        >
                            {{ tour.cta ?? 'Buka Halaman' }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="flex-1 rounded-xl px-3.5 py-2 text-xs font-semibold transition"
                            :class="
                                tour.satisfied
                                    ? 'bg-emerald-500 text-slate-900 hover:bg-emerald-400'
                                    : 'cursor-not-allowed bg-white/10 text-slate-500'
                            "
                            :disabled="!tour.satisfied"
                            @click="advance"
                        >
                            {{ tour.satisfied ? 'Lanjut' : 'Selesaikan langkah ini' }}
                        </button>

                        <button
                            type="button"
                            class="shrink-0 rounded-xl px-3 py-2 text-xs font-medium text-slate-400 transition hover:text-white"
                            @click="skip"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
