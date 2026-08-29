<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::redirect('/', '/dashboard');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // --- Transaksi ---------------------------------------------------------
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    // Import & export Excel
    Route::get('transactions/template', [TransactionImportController::class, 'template'])->name('transactions.template');
    Route::get('transactions/export', [TransactionImportController::class, 'export'])->name('transactions.export');
    Route::post('transactions/import', [TransactionImportController::class, 'store'])->name('transactions.import');

    // --- Anggaran ----------------------------------------------------------
    Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::post('budgets/copy-previous', [BudgetController::class, 'copyPrevious'])->name('budgets.copy-previous');
    Route::put('budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

    // --- Tagihan -----------------------------------------------------------
    Route::get('bills', [BillController::class, 'index'])->name('bills.index');
    Route::post('bills', [BillController::class, 'store'])->name('bills.store');
    Route::put('bills/{bill}', [BillController::class, 'update'])->name('bills.update');
    Route::delete('bills/{bill}', [BillController::class, 'destroy'])->name('bills.destroy');
    Route::post('bills/{bill}/pay', [BillController::class, 'pay'])->name('bills.pay');
    Route::post('bills/{bill}/unpay', [BillController::class, 'unpay'])->name('bills.unpay');

    // --- Kredit & cicilan --------------------------------------------------
    Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('credits', [CreditController::class, 'store'])->name('credits.store');
    Route::get('credits/{credit}', [CreditController::class, 'show'])->name('credits.show');
    Route::post('credits/{credit}/next-installment', [CreditController::class, 'billNextEarly'])
        ->name('credits.next-installment');
    Route::put('credits/{credit}', [CreditController::class, 'update'])->name('credits.update');
    Route::delete('credits/{credit}', [CreditController::class, 'destroy'])->name('credits.destroy');

    // --- Tabungan terencana ------------------------------------------------
    Route::get('savings', [SavingsGoalController::class, 'index'])->name('savings.index');
    Route::post('savings', [SavingsGoalController::class, 'store'])->name('savings.store');
    Route::get('savings/{goal}', [SavingsGoalController::class, 'show'])->name('savings.show');
    Route::post('savings/{goal}/next-contribution', [SavingsGoalController::class, 'billNextEarly'])
        ->name('savings.next-contribution');
    Route::put('savings/{goal}', [SavingsGoalController::class, 'update'])->name('savings.update');
    Route::delete('savings/{goal}', [SavingsGoalController::class, 'destroy'])->name('savings.destroy');

    // --- Transfer antar akun -----------------------------------------------
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::delete('transfers/{transfer}', [TransferController::class, 'destroy'])->name('transfers.destroy');

    // --- Master data -------------------------------------------------------
    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // --- Dokumen (nota & berkas tagihan) -----------------------------------
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // --- Notifikasi & push -------------------------------------------------
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])
        ->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->name('push-subscriptions.destroy');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
