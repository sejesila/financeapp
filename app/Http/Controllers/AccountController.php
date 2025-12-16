<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::where('is_active', true)->get();

        // Calculate total net worth
        $totalBalance = $accounts->sum('current_balance');

        // Recent transfers
        $recentTransfers = Transfer::with(['fromAccount', 'toAccount'])
            ->latest()
            ->limit(10)
            ->get();

        return view('accounts.index', compact('accounts', 'totalBalance', 'recentTransfers'));
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
            'user_id' => Auth::id(), // 👈 attach the logged-in user
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
        return view('accounts.show', compact('account'));
    }

    public function edit(Account $account)
    {
        return view('accounts.show', compact('account'));
    }

    public function update(Request $request, Account $account)
    {
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
        if ($account->transactions()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete account with existing transactions.');
        }

        $account->delete();
        return redirect()->route('accounts.index')->with('success', 'Account deleted successfully!');
    }

    public function adjustBalance(Request $request, Account $account)
    {
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
            ['name' => 'Balance Adjustment'],
            ['type' => $difference > 0 ? 'income' : 'expense']
        );

        Transaction::create([
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
        $accounts = Account::where('is_active', true)->get();
        return view('accounts.transfer', compact('accounts'));
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'from_account_id' => 'required|exists:accounts,id|different:to_account_id',
            'to_account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        // Check if source account has sufficient balance
        $fromAccount = Account::find($request->from_account_id);

        if ($fromAccount->current_balance < $request->amount) {
            return redirect()->back()
                ->withInput()
                ->with('error', "Insufficient balance in {$fromAccount->name}. Current balance: " . number_format($fromAccount->current_balance, 0, '.', ',') . ", Transfer amount: " . number_format($request->amount, 0, '.', ','));
        }

        // Create transfer record
        $transfer = Transfer::create($request->all());

        // Update account balances
        $toAccount = Account::find($request->to_account_id);

        $fromAccount->updateBalance();
        $toAccount->updateBalance();

        return redirect()->route('accounts.index')->with('success', 'Transfer completed successfully!');
    }

    public function topUpForm(Account $account)
    {
        $categories = $this->getTopUpCategories($account->type);
        return view('accounts.topup', compact('account', 'categories'));
    }

    private function getTopUpCategories($accountType)
    {
        if ($accountType === 'bank') {
            return Category::where('type', 'income')
                ->where('name', 'Salary')
                ->get();
        } elseif ($accountType === 'airtel_money') {
            return Category::where('type', 'income')
                ->where('name', '!=', 'Salary')
                ->where('name', '!=', 'Loan Disbursement')
                ->get();
        } elseif ($accountType === 'mpesa') {
            return Category::where(function($query) {
                $query->where(function($q) {
                    $q->where('type', 'income')
                        ->whereNotIn('name', ['Salary', 'Loan Disbursement']);
                })
                    ->orWhere('type', 'liability');
            })
                ->where('name', '!=', 'Loan Disbursement')
                ->get();
        } else {
            return Category::whereIn('type', ['income', 'liability'])
                ->where('name', '!=', 'Loan Disbursement')
                ->get();
        }
    }

    public function topUp(Request $request, Account $account)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'date' => 'required|date',
            'period_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $category = Category::find($request->category_id);

        if (!$category) {
            return redirect()->back()->with('error', 'Please select a valid category.');
        }

        if ($account->type === 'bank' && $category->type === 'income' && $category->name !== 'Salary') {
            return redirect()->back()->with('error', 'Only Salary income is allowed for bank accounts.');
        }

        // Use period_date if provided, otherwise use transaction date
        $periodDate = $request->period_date ?? $request->date;

        $transaction = $account->transactions()->create([
            'user_id'       => Auth::id(),
            'amount'         => $request->amount,
            'date'           => $request->date,
            'period_date'    => $periodDate,
            'description'    => $request->description ?: "Top-up to {$account->name}",
            'category_id'    => $category->id,
            'payment_method' => $category->name,
        ]);

        if (!$transaction) {
            return redirect()->back()->with('error', 'Failed to create transaction.');
        }

        $account->updateBalance();

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', "Account topped up successfully with KES " . number_format($request->amount, 0, '.', ','));
    }
}
