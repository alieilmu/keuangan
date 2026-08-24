<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\User;

/**
 * Menyiapkan akun & kategori bawaan agar user baru langsung bisa mencatat.
 */
class DefaultDataProvisioner
{
    private const ACCOUNTS = [
        ['name' => 'Dompet Tunai', 'type' => AccountType::Cash, 'color' => '#10b981'],
        ['name' => 'Rekening Bank', 'type' => AccountType::Bank, 'color' => '#0ea5e9'],
        ['name' => 'e-Wallet', 'type' => AccountType::EWallet, 'color' => '#8b5cf6'],
    ];

    private const INCOME_CATEGORIES = [
        ['name' => 'Gaji', 'color' => '#10b981'],
        ['name' => 'Bonus', 'color' => '#22c55e'],
        ['name' => 'Investasi', 'color' => '#14b8a6'],
        ['name' => 'Pemasukan Lain', 'color' => '#84cc16'],
    ];

    private const EXPENSE_CATEGORIES = [
        ['name' => 'Makanan', 'color' => '#f97316'],
        ['name' => 'Transportasi', 'color' => '#0ea5e9'],
        ['name' => 'Belanja', 'color' => '#ec4899'],
        ['name' => 'Tagihan', 'color' => '#ef4444'],
        ['name' => 'Hiburan', 'color' => '#8b5cf6'],
        ['name' => 'Kesehatan', 'color' => '#06b6d4'],
        ['name' => 'Pendidikan', 'color' => '#6366f1'],
        ['name' => 'Pengeluaran Lain', 'color' => '#64748b'],
    ];

    public function provision(User $user): void
    {
        foreach (self::ACCOUNTS as $account) {
            $user->accounts()->firstOrCreate(
                ['name' => $account['name']],
                [
                    'type' => $account['type']->value,
                    'opening_balance' => 0,
                    'balance' => 0,
                    'color' => $account['color'],
                    'is_active' => true,
                ]
            );
        }

        foreach (self::INCOME_CATEGORIES as $category) {
            $user->categories()->firstOrCreate(
                ['name' => $category['name'], 'type' => TransactionType::Income->value],
                ['color' => $category['color']]
            );
        }

        foreach (self::EXPENSE_CATEGORIES as $category) {
            $user->categories()->firstOrCreate(
                ['name' => $category['name'], 'type' => TransactionType::Expense->value],
                ['color' => $category['color']]
            );
        }
    }
}
