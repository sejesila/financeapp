<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\LoanController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CategoryController;

Route::get('/', function () {
    return redirect('/dashboard');
});
Route::resource('transactions', TransactionController::class);
// Transaction detail and reversal routes (add after existing transaction routes)
Route::get('/transactions/{transaction}/reverse', [TransactionController::class, 'reverseForm'])->name('transactions.reverse');
Route::post('/transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])->name('transactions.reverse.store');
Route::resource('categories', CategoryController::class);

Route::get('budgets/{year?}', [BudgetController::class,'index'])->name('budgets.index');
Route::post('budgets/update', [BudgetController::class,'updateBulk'])->name('budgets.update');

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
Route::get('/reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
// Accounts
Route::resource('accounts', App\Http\Controllers\AccountController::class);
Route::get('accounts/transfer/form', [App\Http\Controllers\AccountController::class, 'transferForm'])->name('accounts.transfer');
Route::post('accounts/transfer', [App\Http\Controllers\AccountController::class, 'transfer'])->name('accounts.transferPost');
// Show top-up form
Route::get('/accounts/{account}/topup', [AccountController::class, 'topUpForm'])
    ->name('accounts.topup');

// Submit top-up
Route::post('/accounts/{account}/topup', [AccountController::class, 'topUp'])
    ->name('accounts.topup.store');
// Add these routes to your web.php file

// Loan Management Routes
Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
Route::get('/loans/{loan}/payment', [LoanController::class, 'paymentForm'])->name('loans.payment');
Route::post('/loans/{loan}/payment', [LoanController::class, 'recordPayment'])->name('loans.payment.store');
Route::post('/loans/{loan}/default', [LoanController::class, 'markDefaulted'])->name('loans.default');
Route::delete('/loans/{loan}', [LoanController::class, 'destroy'])->name('loans.destroy');


