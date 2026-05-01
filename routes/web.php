<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\{AccountController,
    BudgetController,
    CategoryController,
    ClientFundController,
    DashboardController,
    EmailPreferenceController,
    LoanController,
    MpesaSmsController,
    ProfileController,
    ReportsController,
    RollingFundController,
    TransactionController};
use App\Http\Controllers\Auth\{AuthenticatedSessionController,
    EmailVerificationNotificationController,
    EmailVerificationPromptController,
    NewPasswordController,
    PasswordController,
    PasswordResetLinkController,
    RegisteredUserController,
    VerifyEmailController};

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
        // Session Keep-Alive (prevents timeout during active use)
        // This is called periodically by JavaScript to keep the session alive
        Route::post('/ping', function () {
            if (auth()->check()) {
                session(['last_activity_time' => time()]);
                return response()->json([
                    'status' => 'ok',
                    'user' => auth()->user()->only(['id', 'name', 'email']),
                    'message' => 'Session refreshed'
                ]);
            }
            return response()->json(['status' => 'unauthorized', 'message' => 'Not authenticated'], 401);
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
        Route::post('/limits', [RollingFundController::class, 'saveLimits'])->name('save-limits');
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
        Route::post('test-monthly', [EmailPreferenceController::class, 'sendTestMonthly'])->name('test-monthly');
        Route::post('test-annual', [EmailPreferenceController::class, 'sendTestAnnual'])->name('test-annual');
        Route::post('send-custom', [EmailPreferenceController::class, 'sendCustom'])->name('send-custom');
        // In routes/web.php, inside the email-preferences prefix group:

        Route::get('preview-monthly', function () {
            $user = Auth::user();
            $reportService = app(\App\Services\ReportDataService::class);
            $data = $reportService->generateMonthlyReport($user);
            return view('emails.pdf.monthly-report', [
                'user' => $user,
                'data' => $data,
            ]);
        })->name('email-preferences.preview-monthly');

        Route::get('preview-annual', function () {
            $user = Auth::user();
            $reportService = app(\App\Services\ReportDataService::class);
            $data = $reportService->generateAnnualReport($user);
            return view('emails.pdf.annual-report', [
                'user' => $user,
                'data' => $data,
            ]);
        })->name('email-preferences.preview-annual');

        Route::get('preview-custom', function (\Illuminate\Http\Request $request) {
            $user = Auth::user();
            $reportService = app(\App\Services\ReportDataService::class);
            $startDate = \Carbon\Carbon::parse($request->get('start', now()->startOfMonth()));
            $endDate   = \Carbon\Carbon::parse($request->get('end', now()->endOfMonth()));
            $data = $reportService->generateCustomReport($user, $startDate, $endDate);
            return view('emails.pdf.monthly-report', [
                'user' => $user,
                'data' => $data,
            ]);
        })->name('email-preferences.preview-custom');
    });

    // ======================================================================
    // Email Verification Routes
    // ======================================================================
    Route::get('/verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // ======================================================================
    // Password Confirmation Routes
    // ======================================================================

    Route::get('/confirm-password', function () {
        return view('auth.confirm-password');
    })->name('password.confirm');

    Route::post('/confirm-password', function (Request $request) {
        if (!Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            return back()->withErrors(['password' => 'Invalid password']);
        }

        $request->session()->passwordConfirmed();
        return redirect()->intended();
    })->middleware('throttle:6,1')->name('password.confirm');
});

/*
|--------------------------------------------------------------------------
| Password Reset Routes (Guest Only)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
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
/*
|--------------------------------------------------------------------------
| Mpesa SMS Webhook (from Android/Tasker — no session auth)
|--------------------------------------------------------------------------
*/
Route::match(['GET', 'POST'], '/webhook/mpesa-sms', [MpesaSmsController::class, 'handle'])
    ->name('webhook.mpesa-sms');

