/**
 * Palet warna siap pilih: 10 keluarga warna x 5 tingkat kecerahan.
 * Nilainya mengikuti skala Tailwind (300 - 700) supaya serasi dengan tema aplikasi.
 */
export const COLOR_PALETTE = [
    { name: 'Emerald', shades: ['#6ee7b7', '#34d399', '#10b981', '#059669', '#047857'] },
    { name: 'Teal', shades: ['#5eead4', '#2dd4bf', '#14b8a6', '#0d9488', '#0f766e'] },
    { name: 'Sky', shades: ['#7dd3fc', '#38bdf8', '#0ea5e9', '#0284c7', '#0369a1'] },
    { name: 'Blue', shades: ['#93c5fd', '#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8'] },
    { name: 'Violet', shades: ['#c4b5fd', '#a78bfa', '#8b5cf6', '#7c3aed', '#6d28d9'] },
    { name: 'Pink', shades: ['#f9a8d4', '#f472b6', '#ec4899', '#db2777', '#be185d'] },
    { name: 'Red', shades: ['#fca5a5', '#f87171', '#ef4444', '#dc2626', '#b91c1c'] },
    { name: 'Orange', shades: ['#fdba74', '#fb923c', '#f97316', '#ea580c', '#c2410c'] },
    { name: 'Amber', shades: ['#fcd34d', '#fbbf24', '#f59e0b', '#d97706', '#b45309'] },
    { name: 'Slate', shades: ['#cbd5e1', '#94a3b8', '#64748b', '#475569', '#334155'] },
];

/** Tingkat kecerahan, dipakai sebagai label baris pada grid palet. */
export const SHADE_LABELS = ['Paling terang', 'Terang', 'Sedang', 'Gelap', 'Paling gelap'];

export function normalizeHex(value) {
    if (typeof value !== 'string') {
        return '';
    }

    return value.trim().toLowerCase();
}
