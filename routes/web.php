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
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// -------------------------
// Public / Auth Routes
// -------------------------
Route::middleware('web')->group(function () {
    // Registration
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    // Login
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    // Logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

// -------------------------
// Authenticated Routes
// -------------------------
Route::middleware(['web', 'auth'])->group(function () {

    // Home redirects to dashboard
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    // Dashboard & Reports
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

    // Accounts - IMPORTANT: Specific routes MUST come BEFORE resource routes
    Route::get('accounts/transfer/form', [AccountController::class, 'transferForm'])->name('accounts.transfer');
    Route::post('accounts/transfer', [AccountController::class, 'transfer'])->name('accounts.transferPost');
    Route::get('/accounts/{account}/topup', [AccountController::class, 'topUpForm'])->name('accounts.topup');
    Route::post('/accounts/{account}/topup', [AccountController::class, 'topUp'])->name('accounts.topup.store');
    Route::post('/accounts/{account}/adjust-balance', [AccountController::class, 'adjustBalance'])->name('accounts.adjustBalance');

    // Accounts resource routes (these use slug automatically)
    Route::resource('accounts', AccountController::class);

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

    // Session keep-alive endpoint
    Route::post('/ping', function () {
        if (auth()->check()) {
            session(['last_activity_time' => time()]);
            return response()->json(['status' => 'ok']);
        }
        return response()->json(['status' => 'unauthorized'], 401);
    })->name('session.ping');
});

// -------------------------
// Session expired redirect
// -------------------------
Route::get('/session-expired', function () {
    return redirect()->route('login')
        ->with('message', 'Your session has expired due to inactivity. Please login again.');
})->name('session.expired');
