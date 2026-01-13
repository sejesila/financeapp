<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AccountController extends Controller
{
    public function index()
    {
        // Regular accounts (excluding savings)
        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereIn('type', ['cash', 'mpesa', 'airtel_money', 'bank'])
            ->get();

        // Savings accounts
        $savingsAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('type', 'savings')
            ->get();

        // Calculate total net worth (all accounts)
        $totalBalance = $accounts->sum('current_balance') + $savingsAccounts->sum('current_balance');

        // Calculate savings total separately
        $totalSavings = $savingsAccounts->sum('current_balance');

        // Recent transfers (only user's own)
        $recentTransfers = Transfer::with(['fromAccount', 'toAccount'])
            ->whereHas('fromAccount', function($query) {
                $query->where('user_id', Auth::id());
            })
            ->latest()
            ->limit(10)
            ->get();

        // Get all accounts for the FAB component
        $allAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('accounts.index', compact('accounts', 'savingsAccounts', 'totalBalance', 'totalSavings', 'recentTransfers', 'allAccounts'));
    }

    public function create()
    {
        return view('accounts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'initial_balance' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        $account = Account::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'type' => $request->type,
            'initial_balance' => $request->initial_balance,
            'current_balance' => $request->initial_balance,
            'notes' => $request->notes,
        ]);

        return redirect()->route('accounts.index')->with('success', 'Account created successfully!');
    }

    public function show(Account $account)
    {
        // Verify ownership
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        // Get paginated transactions with eager loading (optimized query)
        $transactions = $account->transactions()
            ->with(['category.parent', 'feeTransaction'])
            ->select(['id', 'date', 'description', 'amount', 'category_id', 'account_id', 'is_transaction_fee', 'related_fee_transaction_id'])
            ->latest('date')
            ->latest('id')
            ->paginate(20)
            ->appends(request()->query());

        // Calculate statistics efficiently using single query
        $stats = $account->transactions()
            ->selectRaw('
            COUNT(*) as total_transactions,
            SUM(CASE WHEN categories.type = "income" THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN categories.type = "expense" THEN amount ELSE 0 END) as total_expenses,
            SUM(CASE
                WHEN MONTH(date) = ? AND YEAR(date) = ?
                THEN amount
                ELSE 0
            END) as this_month_total
        ', [now()->month, now()->year])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->first();

        $totalTransactions = $stats->total_transactions ?? 0;
        $totalIncome = $stats->total_income ?? 0;
        $totalExpenses = $stats->total_expenses ?? 0;
        $thisMonthTotal = $stats->this_month_total ?? 0;

        return view('accounts.show', compact(
            'account',
            'transactions',
            'totalTransactions',
            'totalIncome',
            'totalExpenses',
            'thisMonthTotal'
        ));
    }

    public function edit(Account $account)
    {
        // Verify ownership
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        return view('accounts.show', compact('account'));
    }

    public function update(Request $request, Account $account)
    {
        // Verify ownership
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $account->update($request->only(['name', 'type', 'notes']));

        return redirect()->route('accounts.index')->with('success', 'Account updated successfully!');
    }

    public function destroy(Account $account)
    {
        // Verify ownership
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        if ($account->transactions()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete account with existing transactions.');
        }

        $account->delete();
        return redirect()->route('accounts.index')->with('success', 'Account deleted successfully!');
    }

    public function adjustBalance(Request $request, Account $account)
    {
        // Verify ownership
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        $request->validate([
            'initial_balance' => 'required|numeric',
        ]);

        $currentBalance = $account->current_balance;
        $targetBalance  = $request->initial_balance;
        $difference     = $targetBalance - $currentBalance;

        if ($difference == 0) {
            return back()->with('success', 'Balance already matches the entered value.');
        }

        $category = Category::firstOrCreate(
            [
                'name' => 'Balance Adjustment',
                'user_id' => Auth::id()
            ],
            [
                'type' => $difference > 0 ? 'income' : 'expense'
            ]
        );

        Transaction::create([
            'user_id' => Auth::id(),
            'date' => now()->toDateString(),
            'period_date' => now()->toDateString(),
            'description' => "Balance adjustment for {$account->name}",
            'amount' => abs($difference),
            'category_id' => $category->id,
            'account_id' => $account->id,
            'payment_method' => match ($account->type) {
                'cash' => 'Cash',
                'mpesa' => 'Mpesa',
                'airtel_money' => 'Airtel Money',
                'bank' => 'Bank Transfer',
                'savings' => 'Savings',
                default => 'Cash'
            }
        ]);

        $account->updateBalance();

// Clear cache after balance adjustment
        $this->clearAccountCache($account->id);

        return redirect()
            ->route('accounts.edit', $account)
            ->with('success', 'Balance adjusted successfully.');
    }

    public function transferForm()
    {
        if (auth()->user()->accounts()->count() < 2) {
            return redirect()
                ->route('accounts.index')
                ->with('error', 'You need at least two accounts to transfer money.');
        }

        // Accounts that CAN be a source (balance >= 1)
        $sourceAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('current_balance', '>=', 1)
            ->get();

        // All active accounts can be destinations
        $destinationAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->get();

        return view('accounts.transfer', compact('sourceAccounts', 'destinationAccounts'));
    }



    /**
     * Calculate withdrawal fee based on amount and account type
     */
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

        // Fetch accounts
        $fromAccount = Account::findOrFail($request->from_account_id);
        $toAccount   = Account::findOrFail($request->to_account_id);

        // Ownership check
        if ($fromAccount->user_id !== Auth::id() || $toAccount->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to one or both accounts.');
        }

        // Calculate transaction fee based on transfer type
        $transactionFee = 0;
        $feeType = null;
        $isMobileMoneyTransfer = in_array($fromAccount->type, ['mpesa', 'airtel_money']);

        if ($isMobileMoneyTransfer) {
            // M-Pesa/Airtel Money to Cash = Withdrawal (Agent withdrawal)
            if ($toAccount->type === 'cash') {
                $feeType = 'withdrawal';
                $transactionFee = $this->calculateWithdrawalFee(
                    $request->amount,
                    $fromAccount->type
                );

                // Validate minimum withdrawal amount for M-Pesa
                if ($fromAccount->type === 'mpesa' && $request->amount < 50) {
                    return redirect()->back()
                        ->withInput()
                        ->withErrors(['amount' => 'Minimum M-Pesa withdrawal amount is KES 50.']);
                }
            }
            // M-Pesa/Airtel Money to Bank = PayBill
            elseif ($toAccount->type === 'bank') {
                $feeType = 'paybill';
                $transactionFee = $this->calculatePayBillFee(
                    $request->amount,
                    $fromAccount->type
                );
            }
            // M-Pesa/Airtel Money to Savings = No fee (internal transfer)
            // M-Pesa to Airtel or vice versa = No fee for now (can be added if needed)
        }

        $totalDeduction = $request->amount + $transactionFee;

        // Check if source account has sufficient balance (including fee)
        if ($fromAccount->current_balance < $totalDeduction) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['amount' => "Insufficient balance in {$fromAccount->name}. Current balance: "
                    . number_format($fromAccount->current_balance, 0, '.', ',')
                    . ", Required: " . number_format($totalDeduction, 0, '.', ',')
                    . " (Transfer: " . number_format($request->amount, 0, '.', ',')
                    . " + Fee: " . number_format($transactionFee, 0, '.', ',') . ")"]);
        }

        // Create transfer
        $transfer = Transfer::create([
            'from_account_id' => $fromAccount->id,
            'to_account_id'   => $toAccount->id,
            'amount'          => $request->amount,
            'date'            => $request->date,
            'description'     => $request->description,
            'user_id'         => auth()->id(),
        ]);

        // Create transaction fee if applicable
        if ($transactionFee > 0) {
            // Get or create Transaction Fees category
            $feeCategory = Category::firstOrCreate(
                [
                    'user_id' => Auth::id(),
                    'name' => 'Transaction Fees'
                ],
                [
                    'type' => 'expense',
                    'icon' => '💸',
                    'is_active' => true
                ]
            );

            $feeDescription = $this->getFeeDescription(
                $fromAccount,
                $toAccount,
                $feeType,
                $request->description
            );

            Transaction::create([
                'user_id' => Auth::id(),
                'date' => $request->date,
                'description' => $feeDescription,
                'amount' => $transactionFee,
                'category_id' => $feeCategory->id,
                'account_id' => $fromAccount->id,
                'payment_method' => match($fromAccount->type) {
                    'mpesa' => 'Mpesa',
                    'airtel_money' => 'Airtel Money',
                    default => 'Cash'
                },
                'is_transaction_fee' => true,
            ]);
        }

        // Update balances
        $fromAccount->updateBalance();
        $toAccount->updateBalance();
        // Clear cache for both accounts
        $this->clearAccountCache($fromAccount->id);
        $this->clearAccountCache($toAccount->id);

        $successMessage = 'Transfer completed successfully!';
        if ($transactionFee > 0) {
            $feeTypeName = $feeType === 'withdrawal' ? 'Withdrawal' : 'PayBill';
            $successMessage .= " ({$feeTypeName} fee: KES " . number_format($transactionFee, 0, '.', ',') . ')';
        }

        return redirect()
            ->route('accounts.index')
            ->with('success', $successMessage);
    }

    /**
     * Calculate withdrawal fee (for M-Pesa/Airtel to Cash)
     */
    private function calculateWithdrawalFee(float $amount, string $accountType): float
    {
        if ($accountType === 'mpesa') {
            $tiers = [
                ['min' => 50, 'max' => 100, 'cost' => 11],
                ['min' => 101, 'max' => 500, 'cost' => 29],
                ['min' => 501, 'max' => 1000, 'cost' => 29],
                ['min' => 1001, 'max' => 1500, 'cost' => 29],
                ['min' => 1501, 'max' => 2500, 'cost' => 29],
                ['min' => 2501, 'max' => 3500, 'cost' => 52],
                ['min' => 3501, 'max' => 5000, 'cost' => 69],
                ['min' => 5001, 'max' => 7500, 'cost' => 87],
                ['min' => 7501, 'max' => 10000, 'cost' => 115],
                ['min' => 10001, 'max' => 15000, 'cost' => 167],
                ['min' => 15001, 'max' => 20000, 'cost' => 185],
                ['min' => 20001, 'max' => 35000, 'cost' => 197],
                ['min' => 35001, 'max' => 50000, 'cost' => 278],
                ['min' => 50001, 'max' => 250000, 'cost' => 309],
            ];
        } elseif ($accountType === 'airtel_money') {
            $tiers = [
                ['min' => 50, 'max' => 100, 'cost' => 11],
                ['min' => 101, 'max' => 500, 'cost' => 29],
                ['min' => 501, 'max' => 1000, 'cost' => 29],
                ['min' => 1001, 'max' => 1500, 'cost' => 29],
                ['min' => 1501, 'max' => 2500, 'cost' => 29],
                ['min' => 2501, 'max' => 3500, 'cost' => 52],
                ['min' => 3501, 'max' => 5000, 'cost' => 69],
                ['min' => 5001, 'max' => 7500, 'cost' => 87],
                ['min' => 7501, 'max' => 10000, 'cost' => 115],
                ['min' => 10001, 'max' => 15000, 'cost' => 167],
                ['min' => 15001, 'max' => 20000, 'cost' => 185],
                ['min' => 20001, 'max' => 35000, 'cost' => 197],
                ['min' => 35001, 'max' => 50000, 'cost' => 278],
                ['min' => 50001, 'max' => 250000, 'cost' => 309],
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
     * Calculate PayBill fee (for M-Pesa/Airtel to Bank)
     */
    private function calculatePayBillFee(float $amount, string $accountType): float
    {
        if ($accountType === 'mpesa') {
            $tiers = [
                ['min' => 1, 'max' => 49, 'cost' => 0],
                ['min' => 50, 'max' => 100, 'cost' => 0],
                ['min' => 101, 'max' => 500, 'cost' => 5],
                ['min' => 501, 'max' => 1000, 'cost' => 10],
                ['min' => 1001, 'max' => 1500, 'cost' => 15],
                ['min' => 1501, 'max' => 2500, 'cost' => 20],
                ['min' => 2501, 'max' => 3500, 'cost' => 25],
                ['min' => 3501, 'max' => 5000, 'cost' => 34],
                ['min' => 5001, 'max' => 7500, 'cost' => 42],
                ['min' => 7501, 'max' => 10000, 'cost' => 48],
                ['min' => 10001, 'max' => 15000, 'cost' => 57],
                ['min' => 15001, 'max' => 20000, 'cost' => 62],
                ['min' => 20001, 'max' => 25000, 'cost' => 67],
                ['min' => 25001, 'max' => 30000, 'cost' => 72],
                ['min' => 30001, 'max' => 35000, 'cost' => 83],
                ['min' => 35001, 'max' => 40000, 'cost' => 99],
                ['min' => 40001, 'max' => 45000, 'cost' => 103],
                ['min' => 45001, 'max' => 50000, 'cost' => 108],
                ['min' => 50001, 'max' => 250000, 'cost' => 108],
            ];
        } elseif ($accountType === 'airtel_money') {
            // Airtel Money PayBill fees (free in Kenya)
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
     * Get descriptive fee message
     */
    private function getFeeDescription(
        Account $fromAccount,
        Account $toAccount,
        ?string $feeType,
        ?string $userDescription
    ): string {
        $accountName = $fromAccount->type === 'mpesa' ? 'M-Pesa' : 'Airtel Money';
        $feeTypeName = $feeType === 'withdrawal' ? 'withdrawal' : 'PayBill';

        $baseDescription = "{$accountName} {$feeTypeName} fee";

        if ($userDescription) {
            return "{$baseDescription}: {$userDescription}";
        }

        return "{$baseDescription}: Transfer to {$toAccount->name}";
    }

    public function topUpForm(Account $account)
    {
        // Verify ownership
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        $categories = $this->getTopUpCategories($account->type);
        return view('accounts.topup', compact('account', 'categories'));
    }

    private function getTopUpCategories($accountType)
    {
        // System categories that shouldn't appear in manual top-up
        $excludedCategories = [
            'Loan Receipt',
            'Loan Repayment',
            'Excise Duty',
            'Loan Fees Refund',
            'Facility Fee Refund',
            'Balance Adjustment',
        ];

        // Excluded parent categories (we only want their children)
        $excludedParents = [
            'Income',
            'Loans',
        ];

        $query = Category::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereNotIn('name', $excludedCategories)
            ->whereNotIn('name', $excludedParents)
            ->whereNotNull('parent_id'); // Only get child categories

        if ($accountType === 'bank') {
            // Banks: Only salary income
            return $query->where('type', 'income')
                ->where('name', 'Salary')
                ->orderBy('name')
                ->get();
        }
        elseif ($accountType === 'savings') {
            // Savings: Allow all income categories (for deposits)
            return $query->where('type', 'income')
                ->orderBy('name')
                ->get();
        }
        elseif ($accountType === 'airtel_money') {
            // Airtel Money: Income except salary
            return $query->where('type', 'income')
                ->where('name', '!=', 'Salary')
                ->orderBy('name')
                ->get();
        }
        elseif ($accountType === 'mpesa') {
            // M-Pesa: Most income + liability categories (for loans)
            return $query->where(function($q) {
                $q->where(function($subQ) {
                    $subQ->where('type', 'income')
                        ->where('name', '!=', 'Salary');
                })
                    ->orWhere('type', 'liability');
            })
                ->orderBy('name')
                ->get();
        }
        else {
            // Cash and other accounts: All income and liability
            return $query->whereIn('type', ['income', 'liability'])
                ->orderBy('name')
                ->get();
        }
    }

    public function topUp(Request $request, Account $account)
    {
        // Verify ownership
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'date' => 'required|date',
            'period_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $category = Category::where('id', $request->category_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$category) {
            return redirect()->back()->with('error', 'Please select a valid category.');
        }

        // Prevent using system categories
        $systemCategories = [
            'Loan Receipt',
            'Loan Repayment',
            'Excise Duty',
            'Loan Fees Refund',
            'Facility Fee Refund',
            'Balance Adjustment',
        ];

        if (in_array($category->name, $systemCategories)) {
            return redirect()->back()->with('error', 'This category is reserved for system use only.');
        }

        if ($account->type === 'bank' && $category->type === 'income' && $category->name !== 'Salary') {
            return redirect()->back()->with('error', 'Only Salary income is allowed for bank accounts.');
        }

        // Handle loans differently - redirect to loan creation
        if ($category->type === 'liability' && $category->parent && $category->parent->name === 'Loans') {
            return redirect()->route('loans.create', [
                'account_id' => $account->id,
                'amount' => $request->amount,
                'source' => $category->name,
                'date' => $request->date,
                'notes' => $request->description,
            ])->with('info', 'Loans require additional details. Please complete the loan form.');
        }

        // Use period_date if provided, otherwise use transaction date
        $periodDate = $request->period_date ?? $request->date;

        $transaction = $account->transactions()->create([
            'user_id'        => Auth::id(),
            'amount'         => $request->amount,
            'date'           => $request->date,
            'period_date'    => $periodDate,
            'description'    => $request->description ?: ($account->type === 'savings' ? "Deposit to {$account->name}" : "Top-up to {$account->name}"),
            'category_id'    => $category->id,
            'payment_method' => $category->name,
        ]);

        if (!$transaction) {
            return redirect()->back()->with('error', 'Failed to create transaction.');
        }

        $account->updateBalance();
        // Clear cache after top-up
        $this->clearAccountCache($account->id);

        $actionWord = $account->type === 'savings' ? 'deposited to' : 'topped up';
        return redirect()
            ->route('accounts.show', $account)
            ->with('success', "Account {$actionWord} successfully with KES " . number_format($request->amount, 0, '.', ','));
    }
    /**
     * Clear account statistics cache
     */
    private function clearAccountCache(int $accountId): void
    {
        Cache::forget("account.{$accountId}.stats");
    }
}
