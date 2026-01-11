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
        $clientFunds = ClientFund::where('user_id', Auth::id())
            ->with('account')
            ->orderBy('status')
            ->orderBy('received_date', 'desc')
            ->get();

        $summary = [
            'total_received' => $clientFunds->sum('amount_received'),
            'total_spent' => $clientFunds->sum('amount_spent'),
            'total_profit' => $clientFunds->sum('profit_amount'),
            'total_balance' => $clientFunds->where('status', '!=', 'completed')->sum('balance'),
        ];

        return view('client-funds.index', compact('clientFunds', 'summary'));
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
            // Create client fund record
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

            // Create receipt transaction
            ClientFundTransaction::create([
                'client_fund_id' => $clientFund->id,
                'type' => 'receipt',
                'amount' => $request->amount_received,
                'date' => $request->received_date,
                'description' => "Received from {$request->client_name} for {$request->purpose}",
            ]);

            // Get or create "Client Funds" income category
            $category = Category::firstOrCreate(
                [
                    'name' => 'Client Funds Receipt',
                    'user_id' => Auth::id(),
                ],
                [
                    'type' => 'income',
                    'parent_id' => null,
                ]
            );

            // Create income transaction in the account
            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'account_id' => $request->account_id,
                'category_id' => $category->id,
                'amount' => $request->amount_received,
                'date' => $request->received_date,
                'period_date' => $request->received_date,
                'description' => "Client fund received: {$request->client_name} - {$request->purpose}",
                'payment_method' => 'Client Transfer',
            ]);

            // Update account balance
            $account = Account::find($request->account_id);
            $account->updateBalance();

            DB::commit();

            return redirect()
                ->route('client-funds.show', $clientFund)
                ->with('success', 'Client fund recorded successfully!');

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
            // Record expense in client fund
            ClientFundTransaction::create([
                'client_fund_id' => $clientFund->id,
                'type' => 'expense',
                'amount' => $request->amount,
                'date' => $request->date,
                'description' => $request->description,
            ]);

            // Create expense transaction in account
            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'account_id' => $clientFund->account_id,
                'category_id' => $request->category_id,
                'amount' => $request->amount,
                'date' => $request->date,
                'period_date' => $request->date,
                'description' => "Client expense: {$clientFund->client_name} - {$request->description}",
                'payment_method' => 'Client Funds',
            ]);

            // Update client fund
            $clientFund->amount_spent += $request->amount;
            $clientFund->updateBalance();

            // Update account balance
            $clientFund->account->updateBalance();

            DB::commit();

            return back()->with('success', 'Expense recorded successfully!');

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
            // Record profit in client fund
            ClientFundTransaction::create([
                'client_fund_id' => $clientFund->id,
                'type' => 'profit',
                'amount' => $request->amount,
                'date' => $request->date,
                'description' => $request->description ?: "Profit from {$clientFund->purpose}",
            ]);

            // Update client fund
            $clientFund->profit_amount += $request->amount;
            $clientFund->updateBalance();

            DB::commit();

            return back()->with('success', 'Profit recorded successfully! This amount is now yours.');

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

        $clientFund->status = 'completed';
        $clientFund->completed_date = now();
        $clientFund->save();

        return back()->with('success', 'Client fund marked as completed!');
    }
}
