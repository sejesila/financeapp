    <?php

    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\{
        AccountController,
        BudgetController,
        CategoryController,
        ClientFundController,
        DashboardController,
        LoanController,
        ReportsController,
        RollingFundController,
        TransactionController
    };
    use App\Http\Controllers\Auth\{
        AuthenticatedSessionController,
        RegisteredUserController,
        NewPasswordController,
        PasswordResetLinkController
    };

    /*
    |--------------------------------------------------------------------------
    | Public Routes (no token required)
    |--------------------------------------------------------------------------
    */

    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('api.password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->name('api.password.store');

    /*
    |--------------------------------------------------------------------------
    | Protected Routes (Sanctum token required)
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        // ======================================================================
        // Auth
        // ======================================================================

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

        Route::get('/user', function (Request $request) {
            return response()->json($request->user());
        });

        // ======================================================================
        // Dashboard
        // ======================================================================

        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/reports', [ReportsController::class, 'index']);

        // ======================================================================
        // Accounts
        // ======================================================================

        Route::prefix('accounts')->group(function () {
            Route::get('transfer/form', [AccountController::class, 'transferForm']);
            Route::post('transfer', [AccountController::class, 'transfer']);
            Route::get('{account}/topup', [AccountController::class, 'topUpForm']);
            Route::post('{account}/topup', [AccountController::class, 'topUp']);
        });

        Route::apiResource('accounts', AccountController::class);

        // ======================================================================
        // Transactions
        // ======================================================================

        Route::apiResource('transactions', TransactionController::class);

        Route::prefix('transactions')->group(function () {
            Route::post('{id}/restore', [TransactionController::class, 'restore']);
            Route::delete('{id}/force', [TransactionController::class, 'forceDestroy']);
        });

        // ======================================================================
        // Categories
        // ======================================================================

        Route::apiResource('categories', CategoryController::class);

        // ======================================================================
        // Budgets
        // ======================================================================

        Route::get('/budgets/{year?}', [BudgetController::class, 'index']);

        // ======================================================================
        // Loans
        // ======================================================================

        Route::apiResource('loans', LoanController::class)->except(['edit', 'update']);

        Route::prefix('loans')->group(function () {
            Route::get('{loan}/payment', [LoanController::class, 'paymentForm']);
            Route::post('{loan}/payment', [LoanController::class, 'recordPayment']);
        });

        // ======================================================================
        // Client Funds
        // ======================================================================

        Route::apiResource('client-funds', ClientFundController::class);

        Route::prefix('client-funds')->group(function () {
            Route::post('{clientFund}/expense', [ClientFundController::class, 'recordExpense']);
            Route::post('{clientFund}/profit', [ClientFundController::class, 'recordProfit']);
            Route::post('{clientFund}/complete', [ClientFundController::class, 'complete']);
            Route::delete('{clientFund}/expense/{transaction}', [ClientFundController::class, 'deleteExpense']);
            Route::delete('{clientFund}/profit/{transaction}', [ClientFundController::class, 'deleteProfit']);
        });

        // ======================================================================
        // Rolling Funds
        // ======================================================================

        Route::prefix('rolling-funds')->group(function () {
            Route::get('/', [RollingFundController::class, 'index']);
            Route::post('/', [RollingFundController::class, 'store']);
            Route::post('/limits', [RollingFundController::class, 'saveLimits']);
            Route::get('/{rollingFund}', [RollingFundController::class, 'show']);
            Route::post('/{rollingFund}/record-outcome', [RollingFundController::class, 'recordOutcome']);
            Route::delete('/{rollingFund}', [RollingFundController::class, 'destroy']);
        });

        // ======================================================================
        // Session Keep-Alive
        // ======================================================================

        Route::post('/ping', function () {
            return response()->json(['status' => 'ok']);
        });
    });
