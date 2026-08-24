<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { usePushNotifications } from '../composables/usePushNotifications';

const page = usePage();
const open = ref(false);

const push = usePushNotifications();

const notifications = computed(() => page.props.notifications ?? { unread: 0, items: [] });

function markAllAsRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true, preserveState: true });
}

function openNotification(notification) {
    open.value = false;

    router.post(
        `/notifications/${notification.id}/read`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (notification.data?.url) {
                    router.visit(notification.data.url);
                }
            },
        },
    );
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative grid size-9 place-items-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
            aria-label="Notifikasi"
            @click="open = !open"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5">
                <path d="M18 8a6 6 0 10-12 0c0 6-2 7-2 7h16s-2-1-2-7" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M13.7 20a2 2 0 01-3.4 0" stroke-linecap="round" />
            </svg>

            <span
                v-if="notifications.unread > 0"
                class="absolute -right-0.5 -top-0.5 grid min-w-4 place-items-center rounded-full bg-emerald-600 px-1 text-[10px] font-semibold leading-4 text-white"
            >
                {{ notifications.unread > 9 ? '9+' : notifications.unread }}
            </span>
        </button>

        <div v-if="open" class="fixed inset-0 z-30" @click="open = false" />

        <div
            v-if="open"
            class="absolute right-0 z-40 mt-2 w-[min(21rem,calc(100vw-2rem))] overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-200"
        >
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <p class="text-sm font-semibold text-slate-900">Notifikasi</p>
                <button
                    v-if="notifications.unread > 0"
                    type="button"
                    class="text-xs font-medium text-emerald-600 hover:text-emerald-700"
                    @click="markAllAsRead"
                >
                    Tandai dibaca
                </button>
            </div>

            <div
                v-if="push.supported.value && !push.subscribed.value"
                class="border-b border-slate-100 bg-emerald-50/60 px-4 py-3"
            >
                <p class="text-xs text-slate-600">
                    Aktifkan notifikasi browser untuk pengingat tagihan dan peringatan anggaran.
                </p>
                <button
                    type="button"
                    class="mt-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="push.busy.value"
                    @click="push.subscribe()"
                >
                    {{ push.busy.value ? 'Memproses...' : 'Aktifkan notifikasi' }}
                </button>
                <p v-if="push.error.value" class="mt-1.5 text-[11px] text-red-600">{{ push.error.value }}</p>
            </div>

            <div v-else-if="push.subscribed.value" class="border-b border-slate-100 px-4 py-2.5">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] text-emerald-700">Notifikasi browser aktif</p>
                    <button
                        type="button"
                        class="text-[11px] text-slate-400 hover:text-slate-600"
                        @click="push.unsubscribe()"
                    >
                        Matikan
                    </button>
                </div>
            </div>

            <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                <li v-if="!notifications.items.length" class="px-4 py-8 text-center text-xs text-slate-400">
                    Belum ada notifikasi.
                </li>

                <li v-for="notification in notifications.items" :key="notification.id">
                    <button
                        type="button"
                        class="block w-full px-4 py-3 text-left transition hover:bg-slate-50"
                        :class="notification.read_at ? 'opacity-60' : ''"
                        @click="openNotification(notification)"
                    >
                        <div class="flex items-start gap-2.5">
                            <span
                                class="mt-1.5 size-1.5 shrink-0 rounded-full"
                                :class="notification.read_at ? 'bg-slate-300' : 'bg-emerald-500'"
                            />
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold text-slate-800">
                                    {{ notification.data.title }}
                                </p>
                                <p class="mt-0.5 line-clamp-2 text-[11px] text-slate-500">
                                    {{ notification.data.body }}
                                </p>
                                <p class="mt-1 text-[10px] text-slate-400">{{ notification.created_label }}</p>
                            </div>
                        </div>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
