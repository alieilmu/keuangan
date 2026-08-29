<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pemindahan saldo antar akun milik user sendiri.
 *
 * Satu transfer selalu menghasilkan DUA baris transaksi:
 *   - transfer_out pada akun pengirim (mutasi keluar)
 *   - transfer_in  pada akun penerima (mutasi masuk)
 *
 * Keduanya dibuat dalam satu DB transaction, sehingga saldo kedua akun
 * berubah serentak. Transfer sesama bank atau sesama nomor rekening tetap
 * dicatat lengkap dua sisi; yang ditolak hanya transfer ke baris akun yang
 * sama persis karena hasilnya nol.
 *
 * Kaki transfer memakai tipe tersendiri (bukan income/expense) supaya tidak
 * mencemari arus kas, pie chart, maupun agregasi anggaran.
 */
class TransferService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data, ?SavingsGoal $goal = null): Transfer
    {
        return DB::transaction(function () use ($user, $data, $goal): Transfer {
            $fromId = (int) $data['from_account_id'];
            $toId = (int) $data['to_account_id'];
            $amount = round((float) $data['amount'], 2);
            $date = CarbonImmutable::parse($data['transfer_date'] ?? now())->toDateString();

            // Kunci kedua akun dengan urutan id menaik agar tidak terjadi
            // deadlock ketika dua transfer berlawanan arah berjalan bersamaan.
            $this->lockAccounts($fromId, $toId);

            $transfer = Transfer::query()->create([
                'user_id' => $user->getKey(),
                'savings_goal_id' => $goal?->getKey(),
                'from_account_id' => $fromId,
                'to_account_id' => $toId,
                'amount' => $amount,
                'transfer_date' => $date,
                'description' => $data['description'] ?? null,
                'reference' => $data['reference'] ?? null,
            ]);

            $label = $data['description'] ?? null;

            $this->writeLeg($user, $transfer, $fromId, TransactionType::TransferOut, $amount, $date, $label);
            $this->writeLeg($user, $transfer, $toId, TransactionType::TransferIn, $amount, $date, $label);

            return $transfer;
        });
    }

    /**
     * Batalkan transfer: kedua kaki dihapus dan saldo kedua akun dipulihkan.
     */
    public function reverse(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $this->lockAccounts((int) $transfer->from_account_id, (int) $transfer->to_account_id);

            foreach ($transfer->transactions()->get() as $leg) {
                $this->applyBalance(
                    (int) $leg->account_id,
                    -round((float) $leg->amount * $leg->type->sign(), 2)
                );

                $leg->delete();
            }

            $transfer->delete();
        });
    }

    /**
     * Satu kaki mutasi + penyesuaian saldo akunnya.
     */
    private function writeLeg(
        User $user,
        Transfer $transfer,
        int $accountId,
        TransactionType $type,
        float $amount,
        string $date,
        ?string $description,
    ): Transaction {
        $counterpart = $type === TransactionType::TransferOut
            ? $transfer->toAccount
            : $transfer->fromAccount;

        $prefix = $type === TransactionType::TransferOut ? 'Transfer ke ' : 'Transfer dari ';

        /** @var Transaction $transaction */
        $transaction = Transaction::query()->create([
            'user_id' => $user->getKey(),
            'account_id' => $accountId,
            'category_id' => null,
            'transfer_id' => $transfer->getKey(),
            'type' => $type->value,
            'amount' => $amount,
            'transaction_date' => $date,
            'description' => $description ?: $prefix.($counterpart?->displayName() ?? 'akun lain'),
        ]);

        $this->applyBalance($accountId, round($amount * $type->sign(), 2));

        return $transaction;
    }

    /**
     * Kunci baris akun dengan urutan deterministik.
     */
    private function lockAccounts(int $first, int $second): void
    {
        $ids = array_values(array_unique([$first, $second]));
        sort($ids);

        Account::query()->whereIn('id', $ids)->lockForUpdate()->get();
    }

    private function applyBalance(int $accountId, float $delta): void
    {
        if (abs($delta) < 0.005) {
            return;
        }

        /** @var Account $account */
        $account = Account::query()->findOrFail($accountId);
        $account->balance = round((float) $account->balance + $delta, 2);
        $account->save();
    }

    /**
     * Payload riwayat transfer untuk frontend.
     *
     * @return array<string, mixed>
     */
    public static function present(Transfer $transfer): array
    {
        $transfer->loadMissing(['fromAccount', 'toAccount', 'savingsGoal']);

        return [
            'id' => $transfer->getKey(),
            'amount' => (float) $transfer->amount,
            'transfer_date' => $transfer->transfer_date?->toDateString(),
            'date_label' => $transfer->transfer_date?->translatedFormat('d M Y'),
            'description' => $transfer->description,
            'reference' => $transfer->reference,
            'from_account_id' => $transfer->from_account_id,
            'from_account' => $transfer->fromAccount?->displayName(),
            'from_account_name' => $transfer->fromAccount?->name,
            'from_account_number' => $transfer->fromAccount?->account_number,
            'to_account_id' => $transfer->to_account_id,
            'to_account' => $transfer->toAccount?->displayName(),
            'to_account_name' => $transfer->toAccount?->name,
            'to_account_number' => $transfer->toAccount?->account_number,
            'same_institution' => $transfer->isSameInstitution(),
            'savings_goal_id' => $transfer->savings_goal_id,
            'savings_goal' => $transfer->savingsGoal?->name,
        ];
    }
}
