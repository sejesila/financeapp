<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return view('accounts.show', compact('account'));
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

        // Calculate withdrawal fee if transferring from M-Pesa or Airtel Money to Cash/Bank
        $withdrawalFee = 0;
        $isWithdrawal = in_array($fromAccount->type, ['mpesa', 'airtel_money']) &&
            in_array($toAccount->type, ['cash', 'bank']);

        if ($isWithdrawal) {
            $withdrawalFee = $this->calculateWithdrawalFee(
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

        $totalDeduction = $request->amount + $withdrawalFee;

        // Check if source account has sufficient balance (including fee)
        if ($fromAccount->current_balance < $totalDeduction) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['amount' => "Insufficient balance in {$fromAccount->name}. Current balance: "
                    . number_format($fromAccount->current_balance, 0, '.', ',')
                    . ", Required: " . number_format($totalDeduction, 0, '.', ',')
                    . " (Transfer: " . number_format($request->amount, 0, '.', ',')
                    . " + Fee: " . number_format($withdrawalFee, 0, '.', ',') . ")"]);
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

        // Create withdrawal fee transaction if applicable
        if ($withdrawalFee > 0) {
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

            Transaction::create([
                'user_id' => Auth::id(),
                'date' => $request->date,
                'description' => "{$fromAccount->name} withdrawal fee: " . ($request->description ?? "Transfer to {$toAccount->name}"),
                'amount' => $withdrawalFee,
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

        $successMessage = 'Transfer completed successfully!';
        if ($withdrawalFee > 0) {
            $successMessage .= ' (Withdrawal fee: KES ' . number_format($withdrawalFee, 0, '.', ',') . ')';
        }

        return redirect()
            ->route('accounts.index')
            ->with('success', $successMessage);
    }

    /**
     * Calculate withdrawal fee based on amount and account type
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
            // Airtel Money withdrawal fees (you can add these if they differ)
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

        // Find the appropriate tier
        foreach ($tiers as $tier) {
            if ($amount >= $tier['min'] && $amount <= $tier['max']) {
                return $tier['cost'];
            }
        }

        // If amount exceeds all tiers, return the highest tier cost
        return end($tiers)['cost'] ?? 0;
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

        $actionWord = $account->type === 'savings' ? 'deposited to' : 'topped up';
        return redirect()
            ->route('accounts.show', $account)
            ->with('success', "Account {$actionWord} successfully with KES " . number_format($request->amount, 0, '.', ','));
    }
}
