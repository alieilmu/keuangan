<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\PaymentSimulationController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel Admin SaaS
|--------------------------------------------------------------------------
| Terpisah dari routes/web.php agar batas panel admin terlihat jelas.
| Middleware 'auth' + 'admin' menegakkan bahwa hanya user dengan is_admin
| yang bisa menembus seluruh grup route ini.
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    // --- Manajemen Akun ------------------------------------------------
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // --- Manajemen Subscription -----------------------------------------
    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::put('subscriptions', [SubscriptionController::class, 'update'])->name('subscriptions.update');

    // --- Katalog paket: kuota anggota & harga -----------------------------
    Route::put('plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('plans.update');

    // --- Keanggotaan grup/keluarga (ditegakkan sesuai kuota paket) --------
    Route::post('groups/{group}/members', [GroupController::class, 'addMember'])->name('groups.members.add');
    Route::delete('groups/{group}/members/{user}', [GroupController::class, 'removeMember'])->name('groups.members.remove');

    // --- Business Analytics ----------------------------------------------
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // --- System Health -----------------------------------------------------
    Route::get('system-health', [SystemHealthController::class, 'index'])->name('system-health.index');

    // --- Payment Method (simulasi QRIS Doku) --------------------------------
    Route::get('payments', [PaymentSimulationController::class, 'index'])->name('payments.index');
    Route::post('payments', [PaymentSimulationController::class, 'store'])->name('payments.store');
    Route::post('payments/{payment}/mark-paid', [PaymentSimulationController::class, 'markPaid'])->name('payments.mark-paid');
    Route::post('payments/{payment}/mark-failed', [PaymentSimulationController::class, 'markFailed'])->name('payments.mark-failed');
});
