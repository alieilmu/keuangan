<script setup>
import { computed } from 'vue';
import { COLOR_PALETTE, SHADE_LABELS, normalizeHex } from '../lib/palette';

const props = defineProps({
    modelValue: { type: String, default: '#10b981' },
});

const emit = defineEmits(['update:modelValue']);

const current = computed(() => normalizeHex(props.modelValue));

// Grid dibaca per baris kecerahan: 5 baris x 10 kolom keluarga warna.
const rows = computed(() =>
    SHADE_LABELS.map((label, shadeIndex) => ({
        label,
        colors: COLOR_PALETTE.map((family) => ({
            hex: family.shades[shadeIndex],
            title: `${family.name} ${(shadeIndex + 3) * 100}`,
        })),
    })),
);

function pick(hex) {
    emit('update:modelValue', hex);
}

function onCustomInput(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
    <div class="space-y-3">
        <!-- Palet siap pilih: 10 warna x 5 tingkat kecerahan -->
        <div class="rounded-xl bg-slate-50 p-2.5 ring-1 ring-slate-200">
            <div v-for="row in rows" :key="row.label" class="grid grid-cols-10 gap-1 [&+&]:mt-1">
                <button
                    v-for="color in row.colors"
                    :key="color.hex"
                    type="button"
                    class="group relative aspect-square rounded-md transition hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-1"
                    :style="{ backgroundColor: color.hex }"
                    :title="`${color.title} - ${color.hex}`"
                    :aria-label="color.title"
                    :aria-pressed="current === color.hex"
                    @click="pick(color.hex)"
                >
                    <svg
                        v-if="current === color.hex"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="white"
                        stroke-width="4"
                        class="absolute inset-0 m-auto size-3 drop-shadow-[0_1px_1px_rgba(0,0,0,0.45)]"
                    >
                        <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Pemilihan bebas berbasis RGB tetap tersedia -->
        <div class="flex items-center gap-2.5">
            <input
                :value="current"
                type="color"
                class="h-9 w-14 shrink-0 cursor-pointer rounded-lg border-0 bg-white p-1 ring-1 ring-slate-200"
                aria-label="Warna kustom (RGB)"
                @input="onCustomInput"
            />

            <input
                :value="current"
                type="text"
                maxlength="7"
                spellcheck="false"
                placeholder="#10b981"
                class="w-28 rounded-lg border-0 px-2.5 py-2 font-mono text-xs uppercase ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500"
                aria-label="Kode heksadesimal warna"
                @input="onCustomInput"
            />

            <span class="text-[11px] text-slate-400">Atau pilih warna bebas (RGB)</span>
        </div>
    </div>
</template>
