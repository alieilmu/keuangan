<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\AccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = Account::query()
            ->where('user_id', $request->user()->getKey())
            ->withCount('transactions')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->getKey(),
                'name' => $account->name,
                'type' => $account->type->value,
                'type_label' => $account->type->label(),
                'opening_balance' => (float) $account->opening_balance,
                'balance' => (float) $account->balance,
                'color' => $account->color,
                'is_active' => $account->is_active,
                'transactions_count' => $account->transactions_count,
            ]);

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'account_types' => collect(AccountType::cases())
                ->map(fn (AccountType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->values(),
            'total_balance' => round((float) $accounts->where('is_active', true)->sum('balance'), 2),
        ]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Akun baru dimulai dari saldo awalnya.
        $data['balance'] = $data['opening_balance'];

        $request->user()->accounts()->create($data);

        return back()->with('success', 'Akun berhasil ditambahkan.');
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $data = $request->validated();
        // Saldo berjalan ikut bergeser sebesar perubahan saldo awal, sehingga
        // histori transaksi yang sudah tercatat tetap terhitung benar.
        $delta = (float) $data['opening_balance'] - (float) $account->opening_balance;

        $account->fill($data);
        $account->balance = round((float) $account->balance + $delta, 2);
        $account->save();

        return back()->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        if ($account->transactions()->exists()) {
            return back()->with('error', 'Akun masih memiliki transaksi dan tidak bisa dihapus.');
        }

        $account->delete();

        return back()->with('success', 'Akun berhasil dihapus.');
    }
}
