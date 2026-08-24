<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const visible = ref(false);
const message = ref('');
const tone = ref('success');

const flash = computed(() => page.props.flash ?? {});

watch(
    flash,
    (value) => {
        const next = value?.success || value?.error;

        if (!next) {
            return;
        }

        message.value = next;
        tone.value = value?.error ? 'error' : 'success';
        visible.value = true;

        window.clearTimeout(window.__flashTimer);
        window.__flashTimer = window.setTimeout(() => (visible.value = false), 4000);
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-3 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-y-3 opacity-0"
    >
        <div
            v-if="visible"
            class="fixed inset-x-4 bottom-20 z-50 mx-auto max-w-sm rounded-xl px-4 py-3 text-sm font-medium shadow-lg ring-1 sm:bottom-6"
            :class="
                tone === 'error'
                    ? 'bg-red-600 text-white ring-red-700'
                    : 'bg-slate-900 text-white ring-slate-800'
            "
            role="status"
        >
            <div class="flex items-start gap-2.5">
                <span class="mt-0.5 shrink-0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4">
                        <path v-if="tone === 'error'" d="M12 8v5m0 3h.01M12 3l9 16H3z" stroke-linecap="round" stroke-linejoin="round" />
                        <path v-else d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <p class="min-w-0 flex-1">{{ message }}</p>
                <button type="button" class="shrink-0 text-white/60 hover:text-white" @click="visible = false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4">
                        <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
        </div>
    </Transition>
</template>
