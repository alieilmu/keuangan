<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Pemberitahuan masa coba (Demo) di dashboard. Muncul hanya untuk tenant
 * berpaket Demo -- pengguna berbayar tidak perlu melihatnya.
 */
const page = usePage();
const subscription = computed(() => page.props.subscription ?? null);

const visible = computed(() => subscription.value?.is_demo === true);

const tone = computed(() => {
    if (subscription.value?.expired) {
        return 'bg-red-50 text-red-900 ring-red-600/20';
    }

    return (subscription.value?.remaining_days ?? 99) <= 2
        ? 'bg-amber-50 text-amber-900 ring-amber-600/20'
        : 'bg-emerald-50 text-emerald-900 ring-emerald-600/20';
});
</script>

<template>
    <div v-if="visible" class="rounded-2xl px-4 py-3 ring-1 ring-inset" :class="tone">
        <div class="flex items-start gap-2.5">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="mt-0.5 size-4 shrink-0">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <div class="min-w-0 flex-1">
                <p v-if="subscription.expired" class="text-sm font-semibold">
                    Masa coba Anda sudah berakhir
                </p>
                <p v-else class="text-sm font-semibold">
                    Masa coba Demo &mdash;
                    {{ subscription.remaining_days }} hari lagi
                </p>

                <p class="mt-0.5 text-xs opacity-80">
                    <template v-if="subscription.expired">
                        Berakhir {{ subscription.expires_label }}. Hubungi admin untuk melanjutkan ke paket berbayar.
                    </template>
                    <template v-else>
                        Akun ini memakai paket <strong>{{ subscription.plan_name }}</strong> yang berlaku sampai
                        {{ subscription.expires_label }}. Seluruh fitur terbuka selama masa coba.
                    </template>
                </p>
            </div>
        </div>
    </div>
</template>
