<script setup>
/**
 * Input nominal rupiah dengan pemisah ribuan saat mengetik: 1000000 -> 1.000.000.
 *
 * Nilai yang dikirim ke form tetap angka polos (Number), bukan teks
 * berformat, sehingga validasi dan penyimpanan di server tidak berubah.
 * Nilai awal dari server seperti "166500.00" dibaca sebagai angka lebih
 * dulu -- bukan dengan membuang semua non-digit, yang akan mengubahnya
 * diam-diam menjadi 16.650.000.
 */
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    allowNegative: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function parts(value) {
    if (value === '' || value === null || value === undefined) {
        return { negative: false, digits: '' };
    }

    if (value === '-') {
        return { negative: true, digits: '' };
    }

    const number = Number(value);

    if (Number.isFinite(number)) {
        return { negative: number < 0, digits: String(Math.trunc(Math.abs(number))) };
    }

    const text = String(value);

    return { negative: text.trim().startsWith('-'), digits: text.replace(/\D/g, '') };
}

const group = (digits) => digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

const display = computed(() => {
    const { negative, digits } = parts(props.modelValue);

    return (negative && props.allowNegative ? '-' : '') + group(digits);
});

function onInput(event) {
    const el = event.target;
    const raw = el.value;
    const caret = el.selectionStart ?? raw.length;
    const digitsBeforeCaret = raw.slice(0, caret).replace(/\D/g, '').length;
    const negative = props.allowNegative && raw.trim().startsWith('-');
    const digits = raw.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 15);

    emit('update:modelValue', digits === '' ? (negative ? '-' : '') : Number((negative ? '-' : '') + digits));

    const formatted = (negative ? '-' : '') + group(digits);
    el.value = formatted;

    // Kembalikan kursor ke posisi yang sama relatif terhadap digit, supaya
    // titik pemisah yang disisipkan tidak membuat kursor melompat ke akhir.
    let position = negative ? 1 : 0;
    let seen = 0;

    while (position < formatted.length && seen < digitsBeforeCaret) {
        if (/\d/.test(formatted[position])) {
            seen++;
        }

        position++;
    }

    el.setSelectionRange(position, position);
}
</script>

<template>
    <input
        type="text"
        :inputmode="allowNegative ? 'text' : 'numeric'"
        autocomplete="off"
        :value="display"
        @input="onInput"
    />
</template>
