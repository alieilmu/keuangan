<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import FlashToast from '../Components/FlashToast.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const NAV = [
    { href: '/admin', label: 'Ringkasan', icon: 'M4 13h6V4H4zM14 20h6v-9h-6zM4 20h6v-4H4zM14 8h6V4h-6z' },
    { href: '/admin/users', label: 'Akun', icon: 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM3 21a7 7 0 0 1 14 0' },
    { href: '/admin/subscriptions', label: 'Subscription', icon: 'M4 6h16v12H4zM4 10h16' },
    { href: '/admin/analytics', label: 'Analytics', icon: 'M4 19V9M11 19V4M18 19v-6' },
    { href: '/admin/system-health', label: 'System Health', icon: 'M13 2 3 14h7l-1 8 10-12h-7z' },
    { href: '/admin/payments', label: 'Payment', icon: 'M3 7h18v10H3zM3 11h18' },
];

function isActive(href) {
    return href === '/admin' ? page.url === '/admin' : page.url.startsWith(href);
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-dvh overflow-x-clip bg-slate-950">
        <header class="fixed inset-x-0 top-0 z-30 border-b border-white/10 bg-slate-900/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
                <Link href="/admin" class="flex items-center gap-2.5">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-emerald-500 text-slate-900">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="size-4.5">
                            <path d="M13 2 3 14h7l-1 8 10-12h-7z" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span class="hidden text-sm font-semibold text-white sm:block">Admin Panel</span>
                </Link>

                <div class="min-w-0 flex-1 pl-1">
                    <p class="truncate text-sm font-semibold text-white">{{ user?.name }}</p>
                    <p class="truncate text-xs text-slate-400">Administrator</p>
                </div>

                <Link
                    href="/dashboard"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-300 ring-1 ring-white/10 transition hover:bg-white/5"
                >
                    Ke Aplikasi
                </Link>

                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-red-300 ring-1 ring-red-500/20 transition hover:bg-red-500/10"
                    @click="logout"
                >
                    Keluar
                </button>
            </div>

            <nav class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 pb-2 sm:px-6">
                <Link
                    v-for="item in NAV"
                    :key="item.href"
                    :href="item.href"
                    class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition"
                    :class="
                        isActive(item.href)
                            ? 'bg-emerald-500/15 text-emerald-400'
                            : 'text-slate-400 hover:bg-white/5 hover:text-slate-200'
                    "
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-3.5">
                        <path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    {{ item.label }}
                </Link>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-7xl px-4 pb-10 pt-[7.5rem] sm:px-6">
            <slot />
        </main>

        <FlashToast />
    </div>
</template>
