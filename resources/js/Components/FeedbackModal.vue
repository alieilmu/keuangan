<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import FormField from './FormField.vue';

/** Mengirim saran / laporan / pertanyaan ke admin, dari menu profil. */
const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const CATEGORIES = [
    { value: 'saran', label: 'Saran' },
    { value: 'bug', label: 'Laporan Masalah' },
    { value: 'pertanyaan', label: 'Pertanyaan' },
    { value: 'lainnya', label: 'Lainnya' },
];

const form = useForm({ category: 'saran', message: '' });

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
    },
);

function submit() {
    form.post('/feedback', {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :open="open" title="Kirim Saran ke Admin" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <p class="text-xs leading-relaxed text-slate-500">
                Punya ide fitur, menemukan masalah, atau ingin bertanya? Pesan Anda langsung masuk ke panel admin.
            </p>

            <FormField label="Jenis" :error="form.errors.category">
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="option in CATEGORIES"
                        :key="option.value"
                        type="button"
                        class="rounded-xl px-3 py-2 text-xs font-medium ring-1 transition"
                        :class="
                            form.category === option.value
                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-500'
                                : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'
                        "
                        @click="form.category = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </FormField>

            <FormField label="Pesan" required :error="form.errors.message">
                <textarea
                    v-model="form.message"
                    rows="5"
                    maxlength="2000"
                    placeholder="Ceritakan sedetail mungkin..."
                    class="w-full resize-none rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                />
                <p class="mt-1 text-right text-[11px] text-slate-400">{{ form.message.length }}/2000</p>
            </FormField>

            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500" @click="emit('close')">
                    Batal
                </button>
                <button
                    type="submit"
                    class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    :disabled="form.processing || form.message.trim().length < 10"
                >
                    {{ form.processing ? 'Mengirim...' : 'Kirim' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
