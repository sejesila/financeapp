<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereIn('type', ['cash', 'mpesa', 'airtel_money', 'bank'])
            ->get();

        $savingsAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('type', 'savings')
            ->get();


        $totalBalance = number_format($accounts->sum('current_balance'), 2, '.', '');
         $totalSavings = number_format($savingsAccounts->sum('current_balance'), 2, '.', '');

        $transferSearch = $request->input('transfer_search');

        $recentTransfers = Transfer::with(['fromAccount', 'toAccount'])
            ->whereHas('fromAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->when($transferSearch, function ($query) use ($transferSearch) {
                $query->where(function ($q) use ($transferSearch) {
                    $q->whereHas('fromAccount', fn($s) => $s->where('name', 'like', "%{$transferSearch}%"))
                        ->orWhereHas('toAccount', fn($s) => $s->where('name', 'like', "%{$transferSearch}%"))
                        ->orWhere('description', 'like', "%{$transferSearch}%")
                        ->orWhereRaw('CAST(amount AS CHAR) LIKE ?', ["%{$transferSearch}%"]);
                });
            })
            ->latest()
            ->paginate(15, ['*'], 'transfer_page')
            ->appends($request->query());

        $allAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('accounts.index', compact(
            'accounts',
            'savingsAccounts',
            'totalBalance',
            'totalSavings',
            'recentTransfers',
            'allAccounts',
            'transferSearch'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'type'            => 'required|string',
            'initial_balance' => 'required|numeric|min:0',
            'notes'           => 'nullable|string',
            'logo'            => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('account-logos', 'public');
        }

        Account::create([
            'user_id'         => Auth::id(),
            'name'            => $request->name,
            'type'            => $request->type,
            'initial_balance' => $request->initial_balance,
            'current_balance' => $request->initial_balance,
            'notes'           => $request->notes,
            'logo_path'       => $logoPath,
        ]);

        return redirect()->route('accounts.index')->with('success', 'Account created successfully!');
    }

    public function create()
    {
        return view('accounts.create');
    }

    public function show(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        $search    = $request->input('search');      // ← $request->input() not request()
        $activeTab = $request->input('tab', 'transactions');

        $txSort      = $request->input('tx_sort', 'date');
        $txDirection = $request->input('tx_dir', 'desc');
        $allowedTxSorts = ['date', 'description', 'amount'];
        if (!in_array($txSort, $allowedTxSorts)) {
            $txSort = 'date';
        }
        $txDirection = $txDirection === 'asc' ? 'asc' : 'desc';

        // ── Sorting for top-ups ───────────────────────────────────────
        $topSort         = request('top_sort', 'date');
        $topDirection    = request('top_dir', 'desc');
        $allowedTopSorts = ['date', 'description', 'amount'];
        if (!in_array($topSort, $allowedTopSorts)) {
            $topSort = 'date';
        }
        $topDirection = $topDirection === 'asc' ? 'asc' : 'desc';

        // ── Transactions query ────────────────────────────────────────
        $transactionQuery = $account->transactions()
            ->with(['category.parent', 'feeTransaction'])          // ← add back
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.type', 'expense')
            ->select('transactions.*');

        if ($search) {
            $transactionQuery->where(function ($q) use ($search) {
                $q->where('transactions.description', 'like', '%' . $search . '%')
                    ->orWhereRaw('CAST(transactions.amount AS CHAR) LIKE ?', ['%' . $search . '%']);
            });
        }

        $transactions = $transactionQuery
            ->orderBy('transactions.' . $txSort, $txDirection)
            ->when($txSort === 'date', fn($q) => $q->orderBy('transactions.id', $txDirection))
            ->paginate(20, ['*'], 'tx_page')
            ->appends($request->query());

// ── Top-ups query ─────────────────────────────────────────────
        $topUpQuery = $account->transactions()
            ->with(['category'])                                   // ← add back
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereIn('categories.type', ['income', 'liability'])
            ->select('transactions.*');

        if ($search) {
            $topUpQuery->where(function ($q) use ($search) {
                $q->where('transactions.description', 'like', '%' . $search . '%')
                    ->orWhereRaw('CAST(transactions.amount AS CHAR) LIKE ?', ['%' . $search . '%']);
            });
        }

        $topUps = $topUpQuery
            ->orderBy('transactions.' . $topSort, $topDirection)
            ->when($topSort === 'date', fn($q) => $q->orderBy('transactions.id', $topDirection))
            ->paginate(20, ['*'], 'top_page')
            ->appends($request->query());
        // ── Stats ─────────────────────────────────────────────────────
        $stats = $account->transactions()
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(CASE WHEN categories.type = "income" THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN categories.type = "expense" THEN amount ELSE 0 END) as total_expenses,
                SUM(CASE
                    WHEN MONTH(date) = ? AND YEAR(date) = ?
                    THEN amount ELSE 0
                END) as this_month_total
            ', [now()->month, now()->year])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->first();

        $totalTransactions = $stats->total_transactions ?? 0;
        $totalIncome       = $stats->total_income ?? 0;
        $totalExpenses     = $stats->total_expenses ?? 0;
        $thisMonthTotal    = $stats->this_month_total ?? 0;
        \Log::info('TX COUNT', [
            'search'          => $search,
            'paginator_total' => $transactions->total(),    // ← total matching rows
            'paginator_count' => $transactions->count(),    // ← rows on current page
            'paginator_items' => $transactions->pluck('description'), // ← actual descriptions
        ]);

        return view('accounts.show', compact(
            'account',
            'transactions',
            'topUps',
            'totalTransactions',
            'totalIncome',
            'totalExpenses',
            'thisMonthTotal',
            'search',
            'activeTab',
            'txSort',
            'txDirection',
            'topSort',
            'topDirection'
        ));
    }

    public function edit(Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        return view('accounts.edit', compact('account'));
    }

    public function update(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        $request->validate([
            'name'        => 'required|string|max:255',
            'notes'       => 'nullable|string',
            'logo'        => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
            'remove_logo' => 'nullable',
        ]);

        $updateData = $request->only(['name', 'notes']);

        if ($request->hasFile('logo')) {
            if ($account->logo_path) {
                Storage::disk('public')->delete($account->logo_path);
            }
            $updateData['logo_path'] = $request->file('logo')->store('account-logos', 'public');
        } elseif ($request->has('remove_logo') && $request->remove_logo) {
            if ($account->logo_path) {
                Storage::disk('public')->delete($account->logo_path);
            }
            $updateData['logo_path'] = null;
        }

        $account->update($updateData);

        return redirect()->route('accounts.show', $account)->with('success', 'Account updated successfully!');
    }

    public function destroy(Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        if ($account->transactions()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete account with existing transactions.');
        }

        if ($account->logo_path) {
            Storage::disk('public')->delete($account->logo_path);
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('success', 'Account deleted successfully!');
    }

    private function clearAccountCache(int $accountId): void
    {
        Cache::forget("account.{$accountId}.stats");
    }

    public function transferForm()
    {
        if (auth()->user()->accounts()->count() < 2) {
            return redirect()
                ->route('accounts.index')
                ->with('error', 'You need at least two accounts to transfer money.');
        }

        $sourceAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('current_balance', '>=', 1)
            ->get();

        $destinationAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->get();

        return view('accounts.transfer', compact('sourceAccounts', 'destinationAccounts'));
    }

    /**
     * Validate transfer rules between account types.
     *
     * Rules:
     * 1. Cannot transfer to the same account
     * 2. Cash cannot transfer directly to savings accounts (must go through mobile money first)
     * 3. Mobile money (mpesa, airtel_money) can transfer to any account type
     * 4. Bank can transfer to any account type
     * 5. Savings can only transfer to cash/mpesa/airtel_money (not to bank or other savings)
     *
     * @param Account $fromAccount
     * @param Account $toAccount
     * @return array|null Returns null if valid, otherwise returns [fieldName, errorMessage]
     */
    private function validateTransferRules(Account $fromAccount, Account $toAccount): ?array
    {
        // Rule 1: Cannot transfer to same account (handled by 'different' validator)
        if ($fromAccount->id === $toAccount->id) {
            return ['from_account_id', 'Cannot transfer to the same account.'];
        }

        // Rule 2: Cash cannot transfer directly to savings
        if ($fromAccount->type === 'cash' && $toAccount->type === 'savings') {
            return [
                'to_account_id',
                'Direct transfers from cash to savings accounts are not allowed. Please transfer to M-Pesa or Airtel Money first.'
            ];
        }

        // Rule 5: Savings can only transfer to cash/mobile money
        if ($fromAccount->type === 'savings') {
            $allowedTypes = ['cash', 'mpesa', 'airtel_money'];
            if (!in_array($toAccount->type, $allowedTypes)) {
                return [
                    'to_account_id',
                    'Savings accounts can only transfer to Cash, M-Pesa, or Airtel Money accounts.'
                ];
            }
        }

        return null; // Valid transfer
    }

    public function transfer(Request $request)
    {
        if (auth()->user()->accounts()->count() < 2) {
            return redirect()
                ->route('accounts.index')
                ->with('error', 'You need at least two accounts to transfer money.');
        }

        $request->validate([
            'from_account_id' => 'required|exists:accounts,id|different:to_account_id',
            'to_account_id'   => 'required|exists:accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'date'            => 'required|date',
            'description'     => 'nullable|string',
        ]);

        $fromAccount = Account::withoutGlobalScopes()->findOrFail($request->from_account_id);
        $toAccount   = Account::withoutGlobalScopes()->findOrFail($request->to_account_id);

        // ── Ownership check ───────────────────────────────────────────────────
        if ($fromAccount->user_id !== Auth::id() || $toAccount->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to one or both accounts.');
        }

        // ── Transfer rule validation ──────────────────────────────────────────
        $ruleViolation = $this->validateTransferRules($fromAccount, $toAccount);
        if ($ruleViolation) {
            return redirect()->back()
                ->withInput()
                ->withErrors([$ruleViolation[0] => $ruleViolation[1]]);
        }

        // ── Calculate fees ────────────────────────────────────────────────────
        $transactionFee        = 0;
        $feeType               = null;
        $isMobileMoneyTransfer = in_array($fromAccount->type, ['mpesa', 'airtel_money']);
        $isBankToCash          = $fromAccount->type === 'bank' && $toAccount->type === 'cash';

        if ($isMobileMoneyTransfer) {
            if ($toAccount->type === 'cash') {
                $feeType        = 'withdrawal';
                $transactionFee = $this->calculateWithdrawalFee($request->amount, $fromAccount->type);

                if ($fromAccount->type === 'mpesa' && $request->amount < 50) {
                    return redirect()->back()
                        ->withInput()
                        ->withErrors(['amount' => 'Minimum M-Pesa withdrawal amount is KES 50.']);
                }
            } elseif ($toAccount->type === 'bank') {
                $feeType        = 'paybill';
                $transactionFee = $this->calculatePayBillFee($request->amount, $fromAccount->type);
            }
        } elseif ($isBankToCash) {
            $feeType        = 'atm';
            $transactionFee = $this->calculateAtmFee();
        }

        $totalDeduction = $request->amount + $transactionFee;

        // ── Balance check ─────────────────────────────────────────────────────
        if ($fromAccount->current_balance < $totalDeduction) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['amount' => "Insufficient balance in {$fromAccount->name}. Current balance: "
                    . number_format($fromAccount->current_balance, 0, '.', ',')
                    . ", Required: " . number_format($totalDeduction, 2, '.', ',')
                    . " (Transfer: " . number_format($request->amount, 0, '.', ',')
                    . " + Fee: " . number_format($transactionFee, 2, '.', ',') . ")"]);
        }

        // ── Create transfer ───────────────────────────────────────────────────
        Transfer::create([
            'from_account_id' => $fromAccount->id,
            'to_account_id'   => $toAccount->id,
            'amount'          => $request->amount,
            'date'            => $request->date,
            'description'     => $request->description,
            'user_id'         => auth()->id(),
        ]);

        // ── Create fee transaction if applicable ───────────────────────────────
        if ($transactionFee > 0) {
            $feeCategory = Category::firstOrCreate(
                ['user_id' => Auth::id(), 'name' => 'Transaction Fees', 'parent_id' => null],
                ['type' => 'expense', 'icon' => '💸', 'is_active' => true]
            );

            Transaction::create([
                'user_id'            => Auth::id(),
                'date'               => $request->date,
                'description'        => $this->getFeeDescription($fromAccount, $toAccount, $feeType, $request->description),
                'amount'             => $transactionFee,
                'category_id'        => $feeCategory->id,
                'account_id'         => $fromAccount->id,
                'payment_method'     => match ($fromAccount->type) {
                    'mpesa'        => 'Mpesa',
                    'airtel_money' => 'Airtel Money',
                    'bank'         => 'Bank',
                    default        => 'Cash'
                },
                'is_transaction_fee' => true,
            ]);
        }

        // ── Update account balances ───────────────────────────────────────────
        $fromAccount->updateBalance();
        $toAccount->updateBalance();

        $this->clearAccountCache($fromAccount->id);
        $this->clearAccountCache($toAccount->id);

        // ── Success message ───────────────────────────────────────────────────
        $successMessage = 'Transfer completed successfully!';
        if ($transactionFee > 0) {
            $feeTypeName = match ($feeType) {
                'withdrawal' => 'Withdrawal',
                'paybill'    => 'PayBill',
                'atm'        => 'ATM Withdrawal',
                default      => 'Transaction',
            };
            $successMessage .= " ({$feeTypeName} fee: KES " . number_format($transactionFee, 2, '.', ',') . ')';
        }

        return redirect()->route('accounts.index')->with('success', $successMessage);
    }

    private function calculateWithdrawalFee(float $amount, string $accountType): float
    {
        $tiers = [
            ['min' => 50,    'max' => 100,    'cost' => 11],
            ['min' => 101,   'max' => 500,    'cost' => 29],
            ['min' => 501,   'max' => 1000,   'cost' => 29],
            ['min' => 1001,  'max' => 1500,   'cost' => 29],
            ['min' => 1501,  'max' => 2500,   'cost' => 29],
            ['min' => 2501,  'max' => 3500,   'cost' => 52],
            ['min' => 3501,  'max' => 5000,   'cost' => 69],
            ['min' => 5001,  'max' => 7500,   'cost' => 87],
            ['min' => 7501,  'max' => 10000,  'cost' => 115],
            ['min' => 10001, 'max' => 15000,  'cost' => 167],
            ['min' => 15001, 'max' => 20000,  'cost' => 185],
            ['min' => 20001, 'max' => 35000,  'cost' => 197],
            ['min' => 35001, 'max' => 50000,  'cost' => 278],
            ['min' => 50001, 'max' => 250000, 'cost' => 309],
        ];

        if (!in_array($accountType, ['mpesa', 'airtel_money'])) {
            return 0;
        }

        foreach ($tiers as $tier) {
            if ($amount >= $tier['min'] && $amount <= $tier['max']) {
                return $tier['cost'];
            }
        }

        return end($tiers)['cost'] ?? 0;
    }

    private function calculatePayBillFee(float $amount, string $accountType): float
    {
        if ($accountType === 'mpesa') {
            $tiers = [
                ['min' => 1,     'max' => 49,     'cost' => 0],
                ['min' => 50,    'max' => 100,    'cost' => 0],
                ['min' => 101,   'max' => 500,    'cost' => 5],
                ['min' => 501,   'max' => 1000,   'cost' => 10],
                ['min' => 1001,  'max' => 1500,   'cost' => 15],
                ['min' => 1501,  'max' => 2500,   'cost' => 20],
                ['min' => 2501,  'max' => 3500,   'cost' => 25],
                ['min' => 3501,  'max' => 5000,   'cost' => 34],
                ['min' => 5001,  'max' => 7500,   'cost' => 42],
                ['min' => 7501,  'max' => 10000,  'cost' => 48],
                ['min' => 10001, 'max' => 15000,  'cost' => 57],
                ['min' => 15001, 'max' => 20000,  'cost' => 62],
                ['min' => 20001, 'max' => 25000,  'cost' => 67],
                ['min' => 25001, 'max' => 30000,  'cost' => 72],
                ['min' => 30001, 'max' => 35000,  'cost' => 83],
                ['min' => 35001, 'max' => 40000,  'cost' => 99],
                ['min' => 40001, 'max' => 45000,  'cost' => 103],
                ['min' => 45001, 'max' => 50000,  'cost' => 108],
                ['min' => 50001, 'max' => 250000, 'cost' => 108],
            ];
        } elseif ($accountType === 'airtel_money') {
            $tiers = [
                ['min' => 1, 'max' => 150000, 'cost' => 0],
            ];
        } else {
            return 0;
        }

        foreach ($tiers as $tier) {
            if ($amount >= $tier['min'] && $amount <= $tier['max']) {
                return $tier['cost'];
            }
        }

        return end($tiers)['cost'] ?? 0;
    }

    /**
     * ATM withdrawal fee: flat KES 33 + 15% excise duty on that charge.
     * Total = 33 + (33 * 0.15) = 33 + 4.95 = KES 37.95
     */
    private function calculateAtmFee(): float
    {
        $baseFee    = 33.00;
        $exciseDuty = $baseFee * 0.15;

        return round($baseFee + $exciseDuty, 2); // 37.95
    }

    private function getFeeDescription(
        Account $fromAccount,
        Account $toAccount,
        ?string $feeType,
        ?string $userDescription
    ): string {
        $baseDescription = match ($feeType) {
            'withdrawal' => ($fromAccount->type === 'mpesa' ? 'M-Pesa' : 'Airtel Money') . ' withdrawal fee',
            'paybill'    => ($fromAccount->type === 'mpesa' ? 'M-Pesa' : 'Airtel Money') . ' PayBill fee',
            'atm'        => 'ATM withdrawal fee (KES 33 + 15% excise duty)',
            default      => 'Transaction fee',
        };

        return $userDescription
            ? "{$baseDescription}: {$userDescription}"
            : "{$baseDescription}: Transfer to {$toAccount->name}";
    }

    public function topUpForm(Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        [$categories, $showSaccoDividends] = $this->getTopUpCategories($account->type);

        return view('accounts.topup', compact('account', 'categories', 'showSaccoDividends'));
    }

    /**
     * Returns categories eligible for top-up, filtered by account type.
     *
     * Special rule for "Sacco Dividends":
     *   - Only shown between 10 April and 10 May (inclusive) of the current year.
     *   - Hidden permanently for the rest of the year once the user has already
     *     recorded a top-up using it (checked against the transactions table).
     *     "Already used" is scoped per calendar year so it reappears next April.
     *
     * FIX: $showSaccoDividends and $excludedCategories are fully resolved BEFORE
     * $categoryQuery is built, so the single whereNotIn clause always reflects
     * the correct exclusion list. The previous code built the query first and
     * tried to patch it afterward, causing 'Sacco Dividends' to leak through
     * because it was absent from the original whereNotIn array.
     */
    private function getTopUpCategories(string $accountType): array
    {
        $excludedCategories = [
            'Loan Receipt', 'Loan Repayment', 'Excise Duty',
            'Loan Fees Refund', 'Facility Fee Refund',
            'Balance Adjustment', 'Rolling Funds',
        ];

        $excludedParents = ['Income', 'Loans'];

        // ── Sacco Dividends visibility logic ─────────────────────────────────
        $today       = now();
        $windowStart = $today->copy()->setDate($today->year, 4, 10); // 10 April
        $windowEnd   = $today->copy()->setDate($today->year, 5, 10); // 10 May
        $inWindow    = $today->between($windowStart, $windowEnd);

        // Check if Sacco Dividends has been used THIS CALENDAR YEAR.
        // We collect ALL category IDs named 'Sacco Dividends' for this user
        // (there may be more than one if a seeder and the factory both created
        // one) and check if ANY transaction references any of them.
        // DB::table() bypasses ALL Eloquent model scopes so no scope bleeds
        // into or corrupts the $categoryQuery builder defined below.
        $saccoCategoryIds = Category::where('user_id', Auth::id())
            ->where('name', 'Sacco Dividends')
            ->pluck('id');

        $saccoAlreadyUsed = false;

        if ($saccoCategoryIds->isNotEmpty()) {
            $saccoAlreadyUsed = DB::table('transactions')
                ->where('user_id', Auth::id())
                ->whereIn('category_id', $saccoCategoryIds)
                ->whereYear('date', $today->year)
                ->whereNull('deleted_at')
                ->exists();
        }

        // Show ONLY if: (1) within the window AND (2) not yet used this year.
        $showSaccoDividends = $inWindow && !$saccoAlreadyUsed;

        // ── Mutate exclusion list BEFORE building the query ───────────────────
        if (!$showSaccoDividends) {
            $excludedCategories[] = 'Sacco Dividends';
        }

        $categoryQuery = Category::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereNotIn('name', $excludedCategories)
            ->whereNotIn('name', $excludedParents)
            ->whereNotNull('parent_id');

        $categories = match ($accountType) {
            'bank'         => $categoryQuery->where('type', 'income')->where('name', 'Salary')->orderBy('name')->get(),
            'savings'      => $categoryQuery->where('type', 'income')->orderBy('name')->get(),
            'airtel_money' => $categoryQuery->where('type', 'income')->where('name', '!=', 'Salary')->orderBy('name')->get(),
            'mpesa'        => $categoryQuery->where(function ($q) {
                $q->where(function ($s) {
                    $s->where('type', 'income')->where('name', '!=', 'Salary');
                })->orWhere('type', 'liability');
            })->orderBy('name')->get(),
            default        => $categoryQuery->whereIn('type', ['income', 'liability'])->orderBy('name')->get(),
        };

        return [$categories, $showSaccoDividends];
    }

    public function topUp(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'date'        => 'required|date',
            'period_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $category = Category::where('id', $request->category_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$category) {
            return redirect()->back()->with('error', 'Please select a valid category.');
        }

        $systemCategories = [
            'Loan Receipt', 'Loan Repayment', 'Excise Duty',
            'Loan Fees Refund', 'Facility Fee Refund',
            'Balance Adjustment', 'Rolling Funds',
        ];

        if (in_array($category->name, $systemCategories)) {
            return redirect()->back()->with('error', 'This category is reserved for system use only.');
        }

        // ── Server-side guard for Sacco Dividends ────────────────────────────
        if ($category->name === 'Sacco Dividends') {
            $today       = now();
            $windowStart = $today->copy()->setDate($today->year, 4, 10);
            $windowEnd   = $today->copy()->setDate($today->year, 5, 10);
            $inWindow    = $today->between($windowStart, $windowEnd);

            if (!$inWindow) {
                return redirect()->back()->with('error', 'Sacco Dividends can only be recorded between 10 April and 10 May.');
            }

            $saccoCategoryIds = Category::where('user_id', Auth::id())
                ->where('name', 'Sacco Dividends')
                ->pluck('id');

            $alreadyUsed = DB::table('transactions')
                ->where('user_id', Auth::id())
                ->whereIn('category_id', $saccoCategoryIds)
                ->whereYear('date', $today->year)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadyUsed) {
                return redirect()->back()->with('error', 'Sacco Dividends have already been recorded for this year.');
            }
        }

        if ($account->type === 'bank' && $category->type === 'income' && $category->name !== 'Salary') {
            return redirect()->back()->with('error', 'Only Salary income is allowed for bank accounts.');
        }

        if ($category->type === 'liability' && $category->parent && $category->parent->name === 'Loans') {
            return redirect()->route('loans.create', [
                'account_id' => $account->id,
                'amount'     => $request->amount,
                'source'     => $category->name,
                'date'       => $request->date,
                'notes'      => $request->description,
            ])->with('info', 'Loans require additional details. Please complete the loan form.');
        }

        $periodDate = $request->period_date ?? $request->date;

        $transaction = $account->transactions()->create([
            'user_id'        => Auth::id(),
            'amount'         => $request->amount,
            'date'           => $request->date,
            'period_date'    => $periodDate,
            'description'    => $request->description ?: ($account->type === 'savings'
                ? "Deposit to {$account->name}"
                : "Top-up to {$account->name}"),
            'category_id'    => $category->id,
            'payment_method' => $category->name,
        ]);

        if (!$transaction) {
            return redirect()->back()->with('error', 'Failed to create transaction.');
        }

        $account->updateBalance();
        $this->clearAccountCache($account->id);

        $actionWord = $account->type === 'savings' ? 'deposited to' : 'topped up';

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', "Account {$actionWord} successfully with KES " . number_format($request->amount, 0, '.', ','));
    }
}
