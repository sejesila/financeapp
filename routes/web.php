<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Home page - redirect to dashboard or login
    Route::get('/', function () {
        return redirect('/dashboard');
    });

    // Protected routes (require authentication)
    Route::middleware('auth')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');

        // Transactions
        Route::resource('transactions', TransactionController::class);
        Route::get('/transactions/{transaction}/reverse', [TransactionController::class, 'reverseForm'])->name('transactions.reverse');
        Route::post('/transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])->name('transactions.reverse.store');

        // Categories
        Route::resource('categories', CategoryController::class);

        // Budgets
        Route::get('budgets/{year?}', [BudgetController::class, 'index'])->name('budgets.index');
        // Route::post('budgets/update', [BudgetController::class, 'updateBulk'])->name('budgets.update');

        // Accounts
        Route::resource('accounts', AccountController::class);
        Route::get('accounts/transfer/form', [AccountController::class, 'transferForm'])->name('accounts.transfer');
        Route::post('accounts/transfer', [AccountController::class, 'transfer'])->name('accounts.transferPost');
        Route::get('/accounts/{account}/topup', [AccountController::class, 'topUpForm'])->name('accounts.topup');
        Route::post('/accounts/{account}/topup', [AccountController::class, 'topUp'])->name('accounts.topup.store');

        // Loans
        Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
        Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
        Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
        Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
        Route::get('/loans/{loan}/payment', [LoanController::class, 'paymentForm'])->name('loans.payment');
        Route::post('/loans/{loan}/payment', [LoanController::class, 'recordPayment'])->name('loans.payment.store');
        Route::post('/loans/{loan}/default', [LoanController::class, 'markDefaulted'])->name('loans.default');
        Route::delete('/loans/{loan}', [LoanController::class, 'destroy'])->name('loans.destroy');

        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Session keep-alive endpoint (for session timeout management)
        Route::post('/ping', function () {
            if (auth()->check()) {
                session(['last_activity_time' => time()]);
                return response()->json(['status' => 'ok']);
            }
            return response()->json(['status' => 'unauthorized'], 401);
        })->name('session.ping');
    });
});

// Optional: Session expired redirect route
Route::get('/session-expired', function () {
    return redirect()->route('login')
        ->with('message', 'Your session has expired due to inactivity. Please login again.');
})->name('session.expired');

require __DIR__.'/auth.php';
