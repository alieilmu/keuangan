<script setup>
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'sm:max-w-lg' },
});

const emit = defineEmits(['close']);

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

watch(
    () => props.open,
    (open) => {
        document.body.classList.toggle('overflow-hidden', open);

        if (open) {
            document.addEventListener('keydown', onKeydown);
        } else {
            document.removeEventListener('keydown', onKeydown);
        }
    },
);

onBeforeUnmount(() => {
    document.body.classList.remove('overflow-hidden');
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="emit('close')" />

                <div
                    :class="maxWidth"
                    class="relative z-10 max-h-[92dvh] w-full overflow-y-auto rounded-t-3xl bg-white shadow-xl ring-1 ring-slate-200 sm:rounded-2xl"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="sticky top-0 flex items-center justify-between gap-3 border-b border-slate-100 bg-white px-5 py-4">
                        <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
                        <button
                            type="button"
                            class="grid size-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                            @click="emit('close')"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4">
                                <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <div class="px-5 py-4">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
