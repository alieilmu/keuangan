<script setup>
import { computed, ref, watch } from 'vue';
import FormField from './FormField.vue';

const props = defineProps({
    modelValue: { type: [File, null], default: null },
    label: { type: String, default: 'Dokumen' },
    error: { type: String, default: null },
    hint: { type: String, default: 'PDF atau gambar, maksimal 5 MB.' },
    required: { type: Boolean, default: false },
    // Dokumen yang sudah tersimpan sebelumnya (mode ubah).
    existing: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);

const input = ref(null);
const preview = ref(null);

const fileName = computed(() => props.modelValue?.name ?? null);

watch(
    () => props.modelValue,
    (file) => {
        if (preview.value) {
            URL.revokeObjectURL(preview.value);
            preview.value = null;
        }

        if (file && file.type?.startsWith('image/')) {
            preview.value = URL.createObjectURL(file);
        }
    },
);

function onChange(event) {
    emit('update:modelValue', event.target.files?.[0] ?? null);
}

function clear() {
    if (input.value) {
        input.value.value = '';
    }

    emit('update:modelValue', null);
}
</script>

<template>
    <FormField :label="label" :required="required" :error="error" :hint="error ? null : hint">
        <div class="space-y-2">
            <!-- Dokumen yang sudah tersimpan -->
            <a
                v-if="existing && !modelValue"
                :href="existing.url"
                target="_blank"
                rel="noopener"
                class="flex items-center gap-2.5 rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-slate-200 transition hover:bg-slate-100"
            >
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-slate-500 ring-1 ring-slate-200">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                        <path d="M14 3v5h5M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" stroke-linejoin="round" />
                    </svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-medium text-slate-700">{{ existing.name }}</span>
                    <span class="block text-[11px] text-slate-400">{{ existing.size_label }} - lihat dokumen</span>
                </span>
            </a>

            <!-- Pratinjau berkas yang baru dipilih -->
            <div
                v-if="modelValue"
                class="flex items-center gap-2.5 rounded-xl bg-emerald-50 px-3 py-2.5 ring-1 ring-emerald-600/20"
            >
                <img
                    v-if="preview"
                    :src="preview"
                    alt=""
                    class="size-8 shrink-0 rounded-lg object-cover ring-1 ring-emerald-600/20"
                />
                <span
                    v-else
                    class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-emerald-600 ring-1 ring-emerald-600/20"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                        <path d="M14 3v5h5M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" stroke-linejoin="round" />
                    </svg>
                </span>

                <span class="min-w-0 flex-1 truncate text-xs font-medium text-emerald-800">{{ fileName }}</span>

                <button
                    type="button"
                    class="shrink-0 rounded-lg px-2 py-1 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-100"
                    @click="clear"
                >
                    Hapus
                </button>
            </div>

            <input
                ref="input"
                type="file"
                accept="application/pdf,image/*"
                class="block w-full cursor-pointer rounded-xl text-xs text-slate-500 ring-1 ring-slate-200 file:mr-3 file:cursor-pointer file:rounded-l-xl file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                @change="onChange"
            />
        </div>
    </FormField>
</template>
