<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import NotificationBell from '../Components/NotificationBell.vue';
import FlashToast from '../Components/FlashToast.vue';
import WalkthroughPanel from '../Components/WalkthroughPanel.vue';
import AddMemberModal from '../Components/AddMemberModal.vue';
import FeedbackModal from '../Components/FeedbackModal.vue';
import { formatPeriod, currentPeriod } from '../lib/format';

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const group = computed(() => page.props.group ?? null);
const showAddMember = ref(false);
const showFeedback = ref(false);
const subscription = computed(() => page.props.subscription ?? null);
const thisMonth = computed(() => formatPeriod(currentPeriod()));

const NAV = [
    { href: '/dashboard', label: 'Dashboard', icon: 'M4 13h6V4H4zM14 20h6v-9h-6zM4 20h6v-4H4zM14 8h6V4h-6z' },
    { href: '/transactions', label: 'Transaksi', icon: 'M4 7h16M4 12h10M4 17h7' },
    { href: '/budgets', label: 'Anggaran', icon: 'M12 3v18M5 8h14M5 16h14' },
    { href: '/bills', label: 'Tagihan', icon: 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6' },
    { href: '/savings', label: 'Tabungan', icon: 'M4 8h16v10H4zM4 8a4 4 0 0 1 8 0M16 13h.01' },
    { href: '/credits', label: 'Kredit', icon: 'M3 8h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM3 8l2-4h14l2 4M7 15h4' },
    { href: '/accounts', label: 'Akun', icon: 'M3 7h18v10H3zM3 11h18' },
    { href: '/categories', label: 'Kategori', icon: 'M4 5h7v7H4zM13 5h7v7h-7zM4 14h7v5H4zM13 14h7v5h-7z' },
];

// Menu bawah ponsel hanya muat 5 item; sisanya dipindah ke menu profil.
const MOBILE_NAV_HREFS = ['/dashboard', '/transactions', '/budgets', '/bills', '/accounts'];
const MOBILE_NAV = MOBILE_NAV_HREFS.map((href) => NAV.find((item) => item.href === href));
const MORE_NAV = NAV.filter((item) => !MOBILE_NAV_HREFS.includes(item.href));

function isActive(href) {
    return page.url === href || page.url.startsWith(`${href}?`);
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-dvh overflow-x-clip bg-gray-50">
        <!-- Top navigation -->
        <header class="fixed inset-x-0 top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6">
                <Link href="/dashboard" class="flex items-center gap-2.5">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-emerald-600 text-white">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4.5">
                            <path d="M5 19V10M12 19V5M19 19v-6" stroke-linecap="round" />
                        </svg>
                    </span>
                    <span class="hidden text-sm font-semibold text-slate-900 sm:block">Keuangan</span>
                </Link>

                <div class="min-w-0 flex-1 pl-1">
                    <p class="truncate text-sm font-semibold text-slate-900">
                        Halo, {{ user?.name?.split(' ')[0] ?? 'Pengguna' }}
                    </p>
                    <p class="truncate text-xs text-slate-500">{{ thisMonth }}</p>
                </div>

                <NotificationBell />

                <div class="group relative">
                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20"
                        :aria-label="user?.name"
                    >
                        {{ user?.initials ?? 'U' }}
                    </button>

                    <div
                        class="invisible absolute right-0 z-40 mt-2 w-48 rounded-xl bg-white p-1.5 opacity-0 shadow-lg ring-1 ring-slate-200 transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100"
                    >
                        <div class="px-2.5 py-2">
                            <p class="truncate text-xs font-semibold text-slate-800">{{ user?.name }}</p>
                            <p class="truncate text-[11px] text-slate-400">{{ user?.email }}</p>
                        </div>
                        <!-- Modul yang tidak muat di menu bawah ponsel -->
                        <div class="border-b border-slate-100 pb-1.5 mb-1.5 sm:hidden">
                            <Link
                                v-for="item in MORE_NAV"
                                :key="item.href"
                                :href="item.href"
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium transition hover:bg-slate-100"
                                :class="isActive(item.href) ? 'text-emerald-700' : 'text-slate-600'"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4 shrink-0">
                                    <path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ item.label }}
                            </Link>
                        </div>
                        <button
                            v-if="group"
                            type="button"
                            class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-slate-600 transition hover:bg-slate-100"
                            @click="showAddMember = true"
                        >
                            <span>Tambah Anggota</span>
                            <span
                                class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                                :class="group.quota_full ? 'bg-amber-100 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                            >
                                {{ group.member_count }}{{ group.member_quota === null ? '' : `/${group.member_quota}` }}
                            </span>
                        </button>
                        <Link
                            v-if="group"
                            href="/billing"
                            class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-slate-600 transition hover:bg-slate-100"
                        >
                            <span>Langganan</span>
                            <span
                                v-if="subscription"
                                class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                                :class="subscription.expired || subscription.is_demo ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ subscription.plan_name }}
                            </span>
                        </Link>
                        <button
                            type="button"
                            class="w-full rounded-lg px-2.5 py-2 text-left text-xs font-medium text-slate-600 transition hover:bg-slate-100"
                            @click="showFeedback = true"
                        >
                            Kirim Saran
                        </button>
                        <Link
                            v-if="user?.is_admin"
                            href="/admin"
                            class="block w-full rounded-lg px-2.5 py-2 text-left text-xs font-medium text-slate-600 transition hover:bg-slate-100"
                        >
                            Panel Admin
                        </Link>
                        <button
                            type="button"
                            class="w-full rounded-lg px-2.5 py-2 text-left text-xs font-medium text-red-600 transition hover:bg-red-50"
                            @click="logout"
                        >
                            Keluar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Menu horizontal (desktop / tablet) -->
            <nav class="mx-auto hidden max-w-6xl gap-1 px-4 pb-2 sm:flex sm:px-6">
                <Link
                    v-for="item in NAV"
                    :key="item.href"
                    :href="item.href"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                    :class="
                        isActive(item.href)
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl px-4 pb-24 pt-[5.25rem] sm:px-6 sm:pb-10 sm:pt-[7.5rem]">
            <slot />
        </main>

        <!-- Bottom navigation (mobile) -->
        <nav
            class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur sm:hidden"
        >
            <div class="grid grid-cols-5">
                <Link
                    v-for="item in MOBILE_NAV"
                    :key="item.href"
                    :href="item.href"
                    class="flex flex-col items-center gap-1 py-2.5 text-[10px] font-medium transition"
                    :class="isActive(item.href) ? 'text-emerald-600' : 'text-slate-400'"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5">
                        <path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    {{ item.label }}
                </Link>
            </div>
        </nav>

        <AddMemberModal :open="showAddMember" @close="showAddMember = false" />
        <FeedbackModal :open="showFeedback" @close="showFeedback = false" />

        <WalkthroughPanel />

        <FlashToast />
    </div>
</template>
