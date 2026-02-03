<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AccountController,
    BudgetController,
    CategoryController,
    ClientFundController,
    DashboardController,
    EmailPreferenceController,
    LoanController,
    ProfileController,
    ReportsController,
    RollingFundController,
    TransactionController
};
use App\Http\Controllers\Auth\{
    AuthenticatedSessionController,
    PasswordController,
    RegisteredUserController
};

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
| Routes accessible only to unauthenticated users
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
| Routes accessible only to authenticated users
*/

Route::middleware('auth')->group(function () {

    // ======================================================================
    // Authentication & Session Management
    // ======================================================================

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Session Keep-Alive (prevents timeout during active use)
    Route::post('/ping', function () {
        if (auth()->check()) {
            session(['last_activity_time' => time()]);
            return response()->json(['status' => 'ok']);
        }
        return response()->json(['status' => 'unauthorized'], 401);
    })->name('session.ping');

    // ======================================================================
    // Main Application Routes
    // ======================================================================

    // Dashboard & Reports
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');

    // ======================================================================
    // Accounts Management
    // ======================================================================

    Route::prefix('accounts')->name('accounts.')->group(function () {
        // Transfer between accounts
        Route::get('transfer/form', [AccountController::class, 'transferForm'])->name('transfer');
        Route::post('transfer', [AccountController::class, 'transfer'])->name('transferPost');

        // Top-up account
        Route::get('{account}/topup', [AccountController::class, 'topUpForm'])->name('topup');
        Route::post('{account}/topup', [AccountController::class, 'topUp'])->name('topup.store');
    });

    // Standard CRUD operations for accounts
    Route::resource('accounts', AccountController::class);

    // ======================================================================
    // Transactions Management
    // ======================================================================

    Route::resource('transactions', TransactionController::class);

    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::post('{id}/restore', [TransactionController::class, 'restore'])->name('restore');
        Route::delete('{id}/force', [TransactionController::class, 'forceDestroy'])->name('force-destroy');
    });

    // ======================================================================
    // Categories Management
    // ======================================================================

    Route::resource('categories', CategoryController::class);

    // ======================================================================
    // Budgets
    // ======================================================================

    Route::get('/budgets/{year?}', [BudgetController::class, 'index'])->name('budgets.index');

    // ======================================================================
    // Loans Management
    // ======================================================================

    Route::resource('loans', LoanController::class)->except(['edit', 'update']);

    Route::prefix('loans')->name('loans.')->group(function () {
        // Loan payment routes
        Route::get('{loan}/payment', [LoanController::class, 'paymentForm'])->name('payment');
        Route::post('{loan}/payment', [LoanController::class, 'recordPayment'])->name('payment.store');
    });

    // ======================================================================
    // Client Funds Management
    // ======================================================================

    Route::resource('client-funds', ClientFundController::class);

    Route::prefix('client-funds')->name('client-funds.')->group(function () {
        // Record transactions
        Route::post('{clientFund}/expense', [ClientFundController::class, 'recordExpense'])->name('expense');
        Route::post('{clientFund}/profit', [ClientFundController::class, 'recordProfit'])->name('profit');

        // Complete project
        Route::post('{clientFund}/complete', [ClientFundController::class, 'complete'])->name('complete');

        // Delete transactions
        Route::delete('{clientFund}/expense/{transaction}', [ClientFundController::class, 'deleteExpense'])->name('expense.delete');
        Route::delete('{clientFund}/profit/{transaction}', [ClientFundController::class, 'deleteProfit'])->name('profit.delete');
    });

    // ======================================================================
    // Rolling Funds (Odds & Ends)
    // ======================================================================

    Route::prefix('rolling-funds')->name('rolling-funds.')->group(function () {
        Route::get('/', [RollingFundController::class, 'index'])->name('index');
        Route::get('/create', [RollingFundController::class, 'create'])->name('create');
        Route::post('/', [RollingFundController::class, 'store'])->name('store');
        Route::get('/{rollingFund}', [RollingFundController::class, 'show'])->name('show');
        Route::post('/{rollingFund}/record-outcome', [RollingFundController::class, 'recordOutcome'])->name('record-outcome');
        Route::delete('/{rollingFund}', [RollingFundController::class, 'destroy'])->name('destroy');
    });

    // ======================================================================
    // User Settings
    // ======================================================================

    // Profile Management
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    // Password Management
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    // Email Report Preferences
    Route::prefix('email-preferences')->name('email-preferences.')->group(function () {
        Route::get('/', [EmailPreferenceController::class, 'edit'])->name('edit');
        Route::put('/', [EmailPreferenceController::class, 'update'])->name('update');

        // Test email reports
        Route::post('test-weekly', [EmailPreferenceController::class, 'sendTestWeekly'])->name('test-weekly');
        Route::post('test-monthly', [EmailPreferenceController::class, 'sendTestMonthly'])->name('test-monthly');
        Route::post('send-custom', [EmailPreferenceController::class, 'sendCustom'])->name('send-custom');
    });
});

/*
|--------------------------------------------------------------------------
| Session Expired Route
|--------------------------------------------------------------------------
| Redirect users to login when session expires
*/

Route::get('/session-expired', function () {
    return redirect()
        ->route('login')
        ->with('message', 'Your session has expired due to inactivity. Please login again.');
})->name('session.expired');
