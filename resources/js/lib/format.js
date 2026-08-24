const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const compact = new Intl.NumberFormat('id-ID', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const plain = new Intl.NumberFormat('id-ID');

export function formatRupiah(value) {
    return rupiah.format(Number(value) || 0);
}

/** Untuk angka besar pada kartu ringkasan di layar sempit. */
export function formatRupiahCompact(value) {
    return `Rp ${compact.format(Number(value) || 0)}`;
}

export function formatNumber(value) {
    return plain.format(Number(value) || 0);
}

export function formatPercent(value) {
    const number = Number(value) || 0;

    return `${Number.isInteger(number) ? number : number.toFixed(1)}%`;
}

export function formatDate(value, options = { day: '2-digit', month: 'short', year: 'numeric' }) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', options).format(new Date(value));
}

/** "2026-08" -> "Agustus 2026" */
export function formatPeriod(iso) {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' })
        .format(new Date(`${iso}-01T00:00:00`));
}

export function shiftPeriod(iso, months) {
    const [year, month] = iso.split('-').map(Number);
    const date = new Date(year, month - 1 + months, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export function todayIso() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

export function currentPeriod() {
    return todayIso().slice(0, 7);
}
