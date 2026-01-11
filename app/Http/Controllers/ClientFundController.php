<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\ClientFund;
use App\Models\ClientFundTransaction;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientFundController extends Controller
{
    public function index()
    {
        // Get paginated client funds
        $clientFunds = ClientFund::where('user_id', Auth::id())
            ->with('account')
            ->orderBy('status')
            ->orderBy('received_date', 'desc')
            ->paginate(15);

        // Calculate summary from all records (not just paginated)
        $allClientFunds = ClientFund::where('user_id', Auth::id())->get();

        $summary = [
            'total_received' => $allClientFunds->sum('amount_received'),
            'total_spent' => $allClientFunds->sum('amount_spent'),
            'total_profit' => $allClientFunds->sum('profit_amount'),
            'total_balance' => $allClientFunds->where('status', '!=', 'completed')->sum('balance'),
        ];
        // Get all accounts for the FAB component
        $allAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('client-funds.index', compact('clientFunds', 'summary','allAccounts'));
    }

    public function create()
    {
        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->get();

        return view('client-funds.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_name' => 'required|string|max:255',
            'type' => 'required|in:commission,no_profit',
            'amount_received' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:accounts,id',
            'purpose' => 'required|string',
            'received_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Create client fund record (this is a liability - money you're holding for someone)
            $clientFund = ClientFund::create([
                'user_id' => Auth::id(),
                'client_name' => $request->client_name,
                'type' => $request->type,
                'amount_received' => $request->amount_received,
                'amount_spent' => 0,
                'profit_amount' => 0,
                'balance' => $request->amount_received,
                'status' => 'pending',
                'account_id' => $request->account_id,
                'purpose' => $request->purpose,
                'received_date' => $request->received_date,
                'notes' => $request->notes,
            ]);

            // Create receipt transaction in client fund tracking
            ClientFundTransaction::create([
                'client_fund_id' => $clientFund->id,
                'type' => 'receipt',
                'amount' => $request->amount_received,
                'date' => $request->received_date,
                'description' => "Received from {$request->client_name} for {$request->purpose}",
            ]);

            // ❌ DO NOT create income transaction - this is NOT your income yet!
            // The money just increases your account balance but it's a liability
            // Only profit/commission becomes your income

            // Just update account balance directly (money is in your account but not yours)
            $account = Account::find($request->account_id);
            $account->current_balance += $request->amount_received;
            $account->save();

            DB::commit();

            return redirect()
                ->route('client-funds.show', $clientFund)
                ->with('success', 'Client fund recorded successfully! Remember: This is not your income yet.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to record client fund: ' . $e->getMessage());
        }
    }

    public function show(ClientFund $clientFund)
    {
        if ($clientFund->user_id !== Auth::id()) {
            abort(403);
        }

        $clientFund->load(['account', 'transactions']);

        return view('client-funds.show', compact('clientFund'));
    }

    public function recordExpense(Request $request, ClientFund $clientFund)
    {
        if ($clientFund->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $clientFund->balance,
            'description' => 'required|string',
            'date' => 'required|date',
            'category_id' => 'required|exists:categories,id',
        ]);

        DB::beginTransaction();
        try {
            // Record expense in client fund tracking
            ClientFundTransaction::create([
                'client_fund_id' => $clientFund->id,
                'type' => 'expense',
                'amount' => $request->amount,
                'date' => $request->date,
                'description' => $request->description,
            ]);

            // ❌ DO NOT create expense transaction in your personal budget
            // This is the CLIENT'S expense, not yours
            // Just deduct from account balance directly

            $account = $clientFund->account;
            $account->current_balance -= $request->amount;
            $account->save();

            // Update client fund
            $clientFund->amount_spent += $request->amount;
            $clientFund->updateBalance();

            DB::commit();

            return back()->with('success', 'Expense recorded successfully! (This was the client\'s expense, not yours)');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to record expense: ' . $e->getMessage());
        }
    }

    public function recordProfit(Request $request, ClientFund $clientFund)
    {
        if ($clientFund->user_id !== Auth::id()) {
            abort(403);
        }

        if ($clientFund->type !== 'commission') {
            return back()->with('error', 'This client fund type does not allow profit.');
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $clientFund->balance,
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            // Record profit in client fund tracking
            ClientFundTransaction::create([
                'client_fund_id' => $clientFund->id,
                'type' => 'profit',
                'amount' => $request->amount,
                'date' => $request->date,
                'description' => $request->description ?: "Profit from {$clientFund->purpose}",
            ]);

            // ✅ NOW create income transaction - THIS IS your income!
            // Get or create "Side Income" category for profit
            $profitCategory = Category::firstOrCreate(
                [
                    'name' => 'Side Income',
                    'user_id' => Auth::id(),
                ],
                [
                    'type' => 'income',
                    'parent_id' => Category::where('user_id', Auth::id())
                            ->where('name', 'Income')
                            ->first()->id ?? null,
                ]
            );

            // Create income transaction for YOUR profit
            Transaction::create([
                'user_id' => Auth::id(),
                'account_id' => $clientFund->account_id,
                'category_id' => $profitCategory->id,
                'amount' => $request->amount,
                'date' => $request->date,
                'period_date' => $request->date,
                'description' => "Profit from {$clientFund->client_name}: {$clientFund->purpose}",
                'payment_method' => 'Client Commission',
            ]);

            // Update client fund
            $clientFund->profit_amount += $request->amount;
            $clientFund->updateBalance();

            // Update account balance using the proper method
            $clientFund->account->updateBalance();

            DB::commit();

            return back()->with('success', 'Profit recorded successfully! This is now YOUR income.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to record profit: ' . $e->getMessage());
        }
    }

    public function complete(ClientFund $clientFund)
    {
        if ($clientFund->user_id !== Auth::id()) {
            abort(403);
        }

        if ($clientFund->balance > 0) {
            return back()->with('error', 'Cannot complete with remaining balance. Please record all expenses and profit first.');
        }

        DB::beginTransaction();
        try {
            // When completing, if there's any remaining balance, it should be returned to client
            // But since balance is 0, we just mark as completed

            $clientFund->status = 'completed';
            $clientFund->completed_date = now();
            $clientFund->save();

            DB::commit();

            return back()->with('success', 'Client fund marked as completed!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to complete: ' . $e->getMessage());
        }
    }
}
