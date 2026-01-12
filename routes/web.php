<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{ClientFundController,
    DashboardController,
    ReportsController,
    ProfileController,
    AccountController,
    BudgetController,
    LoanController,
    RollingFundController,
    TransactionController,
    CategoryController,
    EmailPreferenceController};
use App\Http\Controllers\Auth\{
    RegisteredUserController,
    AuthenticatedSessionController,
    PasswordController,
};

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    // Registration
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    // Login
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Dashboard
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');

    // Transactions
    Route::resource('transactions', TransactionController::class);
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('trash', [TransactionController::class, 'trash'])->name('trash');
        Route::post('{id}/restore', [TransactionController::class, 'restore'])->name('restore');
        Route::delete('{id}/force', [TransactionController::class, 'forceDestroy'])->name('force-destroy');
    });

    // Categories
    Route::resource('categories', CategoryController::class);

    // Budgets
    Route::get('/budgets/{year?}', [BudgetController::class, 'index'])->name('budgets.index');

    // Accounts
    Route::prefix('accounts')->name('accounts.')->group(function () {
        // Custom routes (must be before resource routes)
        Route::get('transfer/form', [AccountController::class, 'transferForm'])->name('transfer');
        Route::post('transfer', [AccountController::class, 'transfer'])->name('transferPost');
        Route::get('{account}/topup', [AccountController::class, 'topUpForm'])->name('topup');
        Route::post('{account}/topup', [AccountController::class, 'topUp'])->name('topup.store');
        Route::post('{account}/adjust-balance', [AccountController::class, 'adjustBalance'])->name('adjustBalance');
    });
    Route::resource('accounts', AccountController::class);

    // Loans
    Route::resource('loans', LoanController::class)->except(['edit', 'update']);
    Route::prefix('loans')->name('loans.')->group(function () {
        Route::get('{loan}/payment', [LoanController::class, 'paymentForm'])->name('payment');
        Route::post('{loan}/payment', [LoanController::class, 'recordPayment'])->name('payment.store');
        // Route::post('{loan}/default', [LoanController::class, 'markDefaulted'])->name('default');
    });
    // In routes/web.php

        Route::resource('client-funds', ClientFundController::class);
        Route::post('client-funds/{clientFund}/expense', [ClientFundController::class, 'recordExpense'])
            ->name('client-funds.expense');
        Route::post('client-funds/{clientFund}/profit', [ClientFundController::class, 'recordProfit'])
            ->name('client-funds.profit');
        Route::post('client-funds/{clientFund}/complete', [ClientFundController::class, 'complete'])
            ->name('client-funds.complete');


    // Email Preferences
    Route::prefix('email-preferences')->name('email-preferences.')->group(function () {
        Route::get('/', [EmailPreferenceController::class, 'edit'])->name('edit');
        Route::put('/', [EmailPreferenceController::class, 'update'])->name('update');
        Route::post('test-weekly', [EmailPreferenceController::class, 'sendTestWeekly'])->name('test-weekly');
        Route::post('test-monthly', [EmailPreferenceController::class, 'sendTestMonthly'])->name('test-monthly');
        Route::post('send-custom', [EmailPreferenceController::class, 'sendCustom'])->name('send-custom');
    });

    // Rolling Funds Routes
    Route::get('/rolling-funds', [RollingFundController::class, 'index'])->name('rolling-funds.index');
    Route::get('/rolling-funds/create', [RollingFundController::class, 'create'])->name('rolling-funds.create');
    Route::post('/rolling-funds', [RollingFundController::class, 'store'])->name('rolling-funds.store');
    Route::get('/rolling-funds/{rollingFund}', [RollingFundController::class, 'show'])->name('rolling-funds.show');
    Route::post('/rolling-funds/{rollingFund}/record-outcome', [RollingFundController::class, 'recordOutcome'])->name('rolling-funds.record-outcome');
    Route::delete('/rolling-funds/{rollingFund}', [RollingFundController::class, 'destroy'])->name('rolling-funds.destroy');

    // Profile & Password
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    // Session Keep-Alive
    Route::post('/ping', function () {
        if (auth()->check()) {
            session(['last_activity_time' => time()]);
            return response()->json(['status' => 'ok']);
        }
        return response()->json(['status' => 'unauthorized'], 401);
    })->name('session.ping');
});

/*
|--------------------------------------------------------------------------
| Session Expired
|--------------------------------------------------------------------------
*/

Route::get('/session-expired', function () {
    return redirect()
        ->route('login')
        ->with('message', 'Your session has expired due to inactivity. Please login again.');
})->name('session.expired');
