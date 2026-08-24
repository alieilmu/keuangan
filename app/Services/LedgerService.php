<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat saldo `accounts` boleh berubah.
 *
 * Semua mutasi dibungkus DB transaction + SELECT ... FOR UPDATE pada baris akun
 * supaya perhitungan saldo tetap benar walau ada request paralel.
 */
class LedgerService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Transaction
    {
        return DB::transaction(function () use ($user, $data): Transaction {
            $transaction = new Transaction($data);
            $transaction->user_id = $user->getKey();
            $transaction->save();

            $this->applyBalance(
                (int) $transaction->account_id,
                $this->signedAmount($transaction)
            );

            return $transaction;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data): Transaction {
            // Batalkan efek lama sebelum menerapkan nilai baru; akun bisa berpindah.
            $this->applyBalance((int) $transaction->account_id, -$this->signedAmount($transaction));

            $transaction->fill($data);
            $transaction->save();

            $this->applyBalance((int) $transaction->account_id, $this->signedAmount($transaction));

            return $transaction;
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $this->applyBalance((int) $transaction->account_id, -$this->signedAmount($transaction));

            $transaction->delete();
        });
    }

    /**
     * Nilai bertanda: pemasukan menambah saldo, pengeluaran mengurangi.
     */
    private function signedAmount(Transaction $transaction): float
    {
        $type = $transaction->type instanceof TransactionType
            ? $transaction->type
            : TransactionType::from((string) $transaction->type);

        return round((float) $transaction->amount * $type->sign(), 2);
    }

    private function applyBalance(int $accountId, float $delta): void
    {
        if (abs($delta) < 0.005) {
            return;
        }

        /** @var Account $account */
        $account = Account::query()->lockForUpdate()->findOrFail($accountId);
        $account->balance = round((float) $account->balance + $delta, 2);
        $account->save();
    }

    /**
     * Hitung ulang saldo seluruh akun milik user dari histori transaksi.
     * Dipakai setelah import massal agar saldo dijamin konsisten.
     */
    public function recalculate(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $totals = Transaction::query()
                ->select('account_id', DB::raw(
                    "SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as net"
                ))
                ->where('user_id', $user->getKey())
                ->groupBy('account_id')
                ->pluck('net', 'account_id');

            Account::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->get()
                ->each(function (Account $account) use ($totals): void {
                    $account->balance = round(
                        (float) $account->opening_balance + (float) ($totals[$account->getKey()] ?? 0),
                        2
                    );
                    $account->save();
                });
        });
    }
}
