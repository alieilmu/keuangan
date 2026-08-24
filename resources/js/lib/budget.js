/**
 * Aturan warna indikator anggaran - harus sama dengan App\Support\BudgetStatus.
 *
 *   0-50%    hijau        aman
 *   51-70%   kuning       peringatan dini
 *   71-100%  merah        mendekati / mencapai limit
 *   >100%    merah gelap  overbudget
 */
export const BUDGET_STYLES = {
    safe: {
        bar: 'bg-emerald-500',
        track: 'bg-emerald-100',
        text: 'text-emerald-700',
        chip: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    },
    warning: {
        bar: 'bg-amber-400',
        track: 'bg-amber-100',
        text: 'text-amber-700',
        chip: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    },
    danger: {
        bar: 'bg-red-500',
        track: 'bg-red-100',
        text: 'text-red-600',
        chip: 'bg-red-50 text-red-700 ring-red-600/20',
    },
    over: {
        bar: 'bg-red-800',
        track: 'bg-red-200',
        text: 'text-red-800',
        chip: 'bg-red-100 text-red-900 ring-red-800/30',
    },
};

export function budgetStyle(status) {
    return BUDGET_STYLES[status] ?? BUDGET_STYLES.safe;
}

export function statusFromPercentage(percentage) {
    if (percentage > 100) return 'over';
    if (percentage > 70) return 'danger';
    if (percentage > 50) return 'warning';

    return 'safe';
}
