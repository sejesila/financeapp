<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\ClientFund;
use App\Models\ClientFundTransaction;
use App\Models\Transaction;
use App\Services\KenyanBusinessDays;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientFundController extends Controller
{
    public function index(Request $request)
    {
        $clientFilter = $request->query('client');
        $showCompleted = $request->boolean('show_completed', false);

        $query = ClientFund::where('user_id', Auth::id())
            ->with('account')
            ->orderBy('status')
            ->orderBy('received_date', 'desc');

        if ($clientFilter) {
            $query->where('client_name', $clientFilter);
        }

        if (!$showCompleted) {
            $query->where('status', '!=', 'completed');
        }

        $clientFunds = $query->paginate(15)->withQueryString();

        // ── Summary cards (reflects filter if active) ─────────────────
        $summaryQuery = ClientFund::where('user_id', Auth::id());
        if ($clientFilter) {
            $summaryQuery->where('client_name', $clientFilter);
        }
        if (!$showCompleted) {
            $summaryQuery->where('status', '!=', 'completed');
        }
        $allClientFunds = $summaryQuery->get();

        $summary = [
            'total_received' => $allClientFunds->sum('amount_received'),
            'total_spent' => $allClientFunds->sum('amount_spent'),
            'total_profit' => $allClientFunds->sum('profit_amount'),
            'total_balance' => $allClientFunds->where('status', '!=', 'completed')->sum('balance'),
        ];

        // ── Per-client totals (always global, for the summary table) ──
        $clientTotals = ClientFund::where('user_id', Auth::id())
            ->when(!$showCompleted, fn($q) => $q->where('status', '!=', 'completed')) // add this
            ->selectRaw('
        client_name,
        COUNT(*) as total_entries,
        SUM(amount_received) as total_received,
        SUM(amount_spent) as total_spent,
        SUM(profit_amount) as total_profit,
        SUM(balance) as total_balance,
        SUM(CASE WHEN status != "completed" THEN balance ELSE 0 END) as pending_balance
    ')
            ->groupBy('client_name')
            ->orderByRaw('COUNT(*) DESC, SUM(amount_received) DESC')
            ->get();

        $allAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        // Diagnostic: per pooled account, how much borrowing is currently unrecorded
        $unrecordedShortfalls = $allAccounts
            ->mapWithKeys(fn($account) => [$account->id => $this->getUnrecordedBorrowShortfall($account)])
            ->filter(fn($amount) => $amount > 0);

        $userFundIds = ClientFund::where('user_id', Auth::id())->pluck('id');

        $totalBorrowedGross = ClientFundTransaction::whereIn('client_fund_id', $userFundIds)
            ->where('is_borrowed', true)->sum('amount');

        $totalReturned = ClientFundTransaction::whereIn('client_fund_id', $userFundIds)
            ->where('type', 'return')->sum('amount');

        // "Unreturned" = ever borrowed, minus whatever's already been paid back.
        $summary['total_borrowed'] = max(0, $totalBorrowedGross - $totalReturned);
        if ($clientFilter) {
            $clientFundIds = ClientFund::where('user_id', Auth::id())
                ->where('client_name', $clientFilter)
                ->pluck('id');

            $clientBorrowedGross = ClientFundTransaction::whereIn('client_fund_id', $clientFundIds)
                ->where('is_borrowed', true)->sum('amount');

            $clientReturned = ClientFundTransaction::whereIn('client_fund_id', $clientFundIds)
                ->where('type', 'return')->sum('amount');

            $summary['client_unreturned_borrowed'] = max(0, $clientBorrowedGross - $clientReturned);
        }


        return view('client-funds.index', compact(
            'clientFunds',
            'summary',
            'allAccounts',
            'clientTotals',
            'clientFilter',
            'showCompleted',
            'unrecordedShortfalls'
        ));
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

        // Verify the account belongs to the user and is the correct type
        $account = Account::where('id', $request->account_id)
            ->where('user_id', Auth::id())
            ->whereIn('type', ['mpesa', 'bank', 'savings'])
            ->first();

        if (!$account) {
            return back()
                ->withInput()
                ->withErrors(['account_id' => 'Client funds can only be received in M-Pesa, Bank, or Savings accounts.']);
        }

        DB::beginTransaction();
        try {
            // Get or create "Client Funds" liability category
            $liabilityCategory = Category::firstOrCreate(
                [
                    'name' => 'Client Funds',
                    'user_id' => Auth::id(),
                ],
                [
                    'type' => 'liability',
                    'parent_id' => null,
                ]
            );

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

            // For Etica savings accounts, the fund is only effective the next business day
            $isEtica = $account->type === 'savings' && strtolower($account->name) === 'etica';
            $valueDate = $isEtica
                ? KenyanBusinessDays::nextBusinessDay(Carbon::parse($request->received_date))->format('Y-m-d')
                : null;

            // Create liability transaction (this increases account balance)
            $liabilityTransaction = Transaction::create([
                'user_id' => Auth::id(),
                'account_id' => $request->account_id,
                'category_id' => $liabilityCategory->id,
                'amount' => $request->amount_received,
                'date' => $request->received_date,
                'value_date' => $valueDate,
                'period_date' => $request->received_date,
                'description' => "Client fund received from {$request->client_name} for {$request->purpose}",
                'payment_method' => 'Client Fund',
            ]);

            // Create receipt transaction in client fund tracking
            ClientFundTransaction::create([
                'client_fund_id' => $clientFund->id,
                'transaction_id' => $liabilityTransaction->id,
                'type' => 'receipt',
                'amount' => $request->amount_received,
                'date' => $request->received_date,
                'description' => "Received from {$request->client_name} for {$request->purpose}",
            ]);

            DB::commit();

            // Update account balance AFTER commit
            $account->updateBalance();

            return redirect()
                ->route('client-funds.show', $clientFund)
                ->with('success', 'Client fund recorded successfully! This is tracked as a liability.');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to record client fund: ' . $e->getMessage());
        }
    }

    public function create()
    {
        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereIn('type', ['mpesa', 'bank', 'savings'])
            ->get();

        return view('client-funds.create', compact('accounts'));
    }

    public function show(ClientFund $clientFund)
    {
        if ($clientFund->user_id !== Auth::id()) {
            abort(403);
        }

        $clientFund->load(['account', 'transactions']);

        $expenseAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereIn('type', ['mpesa', 'bank'])
            ->orderBy('name')
            ->get();

        $borrowedGross = $clientFund->transactions->where('is_borrowed', true)->sum('amount');
        $returnedTotal = $clientFund->transactions->where('type', 'return')->sum('amount');
        $borrowedTotal = max(0, $borrowedGross - $returnedTotal);

        return view('client-funds.show', compact('clientFund', 'expenseAccounts', 'borrowedTotal'));
    }

    public function recordExpense(Request $request, ClientFund $clientFund)
    {
        if ($clientFund->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01|max:' . $clientFund->balance,
            'description' => 'required|string',
            'date' => 'required|date',
            'category_id' => 'required|exists:categories,id',
        ]);

        $expenseAccount = Account::where('id', $request->account_id)
            ->where('user_id', Auth::id())
            ->whereIn('type', ['mpesa', 'bank'])
            ->first();

        if (!$expenseAccount) {
            return back()
                ->withInput()
                ->withErrors(['account_id' => 'Please select a valid M-Pesa or Bank account to pay from.']);
        }

        DB::beginTransaction();
        try {
            $expenseTransaction = Transaction::create([
                'user_id' => Auth::id(),
                'account_id' => $expenseAccount->id,
                'category_id' => $request->category_id,
                'amount' => $request->amount,
                'date' => $request->date,
                'period_date' => $request->date,
                'description' => "{$request->description} (Client: {$clientFund->client_name})",
                'payment_method' => 'Client Fund',
            ]);

            ClientFundTransaction::create([
                'client_fund_id' => $clientFund->id,
                'transaction_id' => $expenseTransaction->id,
                'type' => 'expense',
                'amount' => $request->amount,
                'date' => $request->date,
                'description' => $request->description,
            ]);

            $clientFund->amount_spent += $request->amount;
            $clientFund->updateBalance();

            DB::commit();

            $expenseAccount->updateBalance();

            return back()->with('success', 'Expense recorded successfully! Account balance updated.');

        } catch (Exception $e) {
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
            // Get the liability category
            $liabilityCategory = Category::where('name', 'Client Funds')
                ->where('user_id', Auth::id())
                ->first();

            // Get or create "Side Income" category for profit tracking
            $profitCategory = Category::firstOrCreate(
                [
                    'name' => 'Side Income',
                    'user_id' => Auth::id(),
                ],
                [
                    'type' => 'income',
                    'icon' => '💰',
                    'parent_id' => Category::where('user_id', Auth::id())
                            ->where('name', 'Income')
                            ->first()->id ?? null,
                ]
            );

            // ✅ Step 1: Create INCOME transaction (tracks as your income for reports)
            $incomeTransaction = Transaction::create([
                'user_id' => Auth::id(),
                'account_id' => $clientFund->account_id,
                'category_id' => $profitCategory->id,
                'amount' => $request->amount,
                'date' => $request->date,
                'period_date' => $request->date,
                'description' => $request->description ?: "Profit from {$clientFund->client_name}: {$clientFund->purpose}",
                'payment_method' => 'Client Commission',
            ]);

            // ✅ Step 2: Create LIABILITY REDUCTION (reduces what you owe client)
            // This offsets the income so net effect on account balance is ZERO
            $liabilityReduction = Transaction::create([
                'user_id' => Auth::id(),
                'account_id' => $clientFund->account_id,
                'category_id' => $liabilityCategory->id,
                'amount' => -$request->amount, // Negative to reduce liability
                'date' => $request->date,
                'period_date' => $request->date,
                'description' => "Profit taken: " . ($request->description ?: "from {$clientFund->client_name}: {$clientFund->purpose}"),
                'payment_method' => 'Client Commission',
            ]);

            // Record profit in client fund tracking
            ClientFundTransaction::create([
                'client_fund_id' => $clientFund->id,
                'transaction_id' => $incomeTransaction->id,
                'type' => 'profit',
                'amount' => $request->amount,
                'date' => $request->date,
                'description' => $request->description ?: "Profit from {$clientFund->purpose}",
            ]);

            // Update client fund
            $clientFund->profit_amount += $request->amount;
            $clientFund->updateBalance();

            // Update account balance
            $clientFund->account->updateBalance();

            DB::commit();

            return back()->with('success', 'Profit recorded successfully! This is now tracked as your income.');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to record profit: ' . $e->getMessage());
        }
    }


    /**
     * Show the form for editing basic client fund details
     */
    public function edit(ClientFund $clientFund)
    {
        if ($clientFund->user_id !== Auth::id()) {
            abort(403);
        }

        // Only M-Pesa and Bank accounts can be used for client funds
        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereIn('type', ['mpesa', 'bank'])
            ->get();

        return view('client-funds.edit', compact('clientFund', 'accounts'));
    }

    /**
     * Update basic client fund details (not amounts)
     */
    public function update(Request $request, ClientFund $clientFund)
    {
        if ($clientFund->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'client_name' => 'required|string|max:255',
            'purpose' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        try {
            $clientFund->update([
                'client_name' => $request->client_name,
                'purpose' => $request->purpose,
                'notes' => $request->notes,
            ]);

            return redirect()
                ->route('client-funds.show', $clientFund)
                ->with('success', 'Client fund updated successfully!');

        } catch (Exception $e) {
            return back()->withInput()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    /**
     * Delete a client fund (only if no transactions recorded)
     */
    public function destroy(ClientFund $clientFund)
    {
        if ($clientFund->user_id !== Auth::id()) {
            abort(403);
        }

        // Check if any expenses or profits have been recorded
        $hasTransactions = ClientFundTransaction::where('client_fund_id', $clientFund->id)
            ->whereIn('type', ['expense', 'profit'])
            ->exists();

        if ($hasTransactions) {
            return back()->with('error', 'Cannot delete client fund with recorded expenses or profits.');
        }

        DB::beginTransaction();
        try {
            // Delete the liability transaction
            Transaction::where('user_id', Auth::id())
                ->where('account_id', $clientFund->account_id)
                ->where('amount', $clientFund->amount_received)
                ->where('date', $clientFund->received_date)
                ->where('description', 'like', "%{$clientFund->client_name}%")
                ->delete();

            // Delete client fund transactions
            ClientFundTransaction::where('client_fund_id', $clientFund->id)->delete();

            // Delete the client fund
            $clientFund->delete();

            // Recalculate account balance
            $account = Account::find($clientFund->account_id);
            $account->updateBalance();

            DB::commit();

            return redirect()
                ->route('client-funds.index')
                ->with('success', 'Client fund deleted successfully!');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Delete an individual expense transaction
     */
    public function deleteExpense(ClientFund $clientFund, ClientFundTransaction $transaction)
    {
        if ($clientFund->user_id !== Auth::id() || $transaction->client_fund_id !== $clientFund->id) {
            abort(403);
        }

        if ($transaction->type !== 'expense') {
            return back()->with('error', 'Invalid transaction type');
        }

        DB::beginTransaction();
        try {
            $expenseTransaction = Transaction::find($transaction->transaction_id);
            $account = $expenseTransaction
                ? Account::find($expenseTransaction->account_id)
                : $clientFund->account;

            if ($expenseTransaction) {
                $expenseTransaction->delete();
            }

            $clientFund->amount_spent -= $transaction->amount;
            $clientFund->updateBalance();

            $transaction->delete();

            DB::commit();

            if ($account) {
                $account->updateBalance();
            }

            return back()->with('success', 'Expense deleted successfully!');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete expense: ' . $e->getMessage());
        }
    }

    /**
     * Delete an individual profit transaction
     */
    public function deleteProfit(ClientFund $clientFund, ClientFundTransaction $transaction)
    {
        if ($clientFund->user_id !== Auth::id() || $transaction->client_fund_id !== $clientFund->id) {
            abort(403);
        }

        if ($transaction->type !== 'profit') {
            return back()->with('error', 'Invalid transaction type');
        }

        DB::beginTransaction();
        try {
            // Find and delete the liability reduction transaction
            $liabilityReduction = Transaction::find($transaction->transaction_id);

            if ($liabilityReduction) {
                $liabilityReduction->delete();
            }

            // Update client fund
            $clientFund->profit_amount -= $transaction->amount;
            $clientFund->updateBalance();

            // Update account balance
            $clientFund->account->updateBalance();

            // Delete the profit transaction record
            $transaction->delete();

            DB::commit();

            return back()->with('success', 'Profit deleted successfully!');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete profit: ' . $e->getMessage());
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

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to complete: ' . $e->getMessage());
        }
    }

    // ── record a borrowed amount against ONE client fund ───────────────────────

    public function recordBorrowed(Request $request, ClientFund $clientFund)
    {
        if ($clientFund->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $clientFund->balance,
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            $clientFund->amount_spent += $request->amount;
            $clientFund->updateBalance();

            ClientFundTransaction::create([
                'client_fund_id' => $clientFund->id,
                'transaction_id' => null,
                'transfer_id' => null,
                'type' => 'expense',
                'is_borrowed' => true,
                'amount' => $request->amount,
                'date' => $request->date,
                'description' => $request->description
                    ?: 'Manually recorded — money borrowed for personal use (reconciliation)',
            ]);

            DB::commit();

            return back()->with('success',
                'Borrowed amount of KES ' . number_format($request->amount, 0) . ' recorded against this client fund.'
            );
        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to record borrowed amount: ' . $e->getMessage());
        }
    }

    /**
     * Mirrors TransferService::recordBorrowedFromClientFunds() — same FIFO
     * logic, but for backfilling historical borrowing rather than a live
     * transfer. Doesn't touch account balances or create a Transaction: the
     * money already left the account at some point in the past without being
     * tracked; this only corrects the ClientFund-side bookkeeping so balances
     * reflect reality.
     */
    public function reconcileBorrowed(Request $request)
    {
        $request->validate([
            'client_name' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        $outstandingFunds = ClientFund::where('user_id', Auth::id())
            ->where('client_name', $request->client_name)
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('received_date')
            ->get();

        $available = $outstandingFunds->sum('balance');

        if ($request->amount > $available) {
            return back()->with('error',
                "Amount exceeds {$request->client_name}'s outstanding balance of KES " . number_format($available, 0) . '.'
            );
        }

        DB::beginTransaction();
        try {
            $remaining = (float)$request->amount;

            foreach ($outstandingFunds as $fund) {
                if ($remaining <= 0) break;

                $portion = min($remaining, (float)$fund->balance);
                if ($portion <= 0) continue;

                $fund->amount_spent += $portion;
                $fund->updateBalance();

                ClientFundTransaction::create([
                    'client_fund_id' => $fund->id,
                    'transaction_id' => null,
                    'transfer_id' => null,
                    'type' => 'expense',
                    'is_borrowed' => true,
                    'amount' => $portion,
                    'date' => $request->date,
                    'description' => $request->description
                        ?: 'Reconciliation — borrowed against pooled client funds',
                ]);

                $remaining -= $portion;
            }

            DB::commit();

            return back()->with('success',
                'KES ' . number_format($request->amount, 0) . " recorded as borrowed across {$request->client_name}'s outstanding funds (oldest first)."
            );
        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Reconciliation failed: ' . $e->getMessage());
        }
    }

    // ── return (repay) previously borrowed money ────────────────────────────────

    /**
     * Unlike recordBorrowed()/reconcileBorrowed() (bookkeeping-only corrections),
     * this represents real money physically going back into an account — so it
     * creates an actual Transaction (same liability mechanism as the original
     * client-fund receipt, since it's restoring money meant to be held for the
     * client) AND reverses the earlier borrow bookkeeping by reducing
     * amount_spent back down, oldest-borrowed-fund first.
     */
    public function returnBorrowed(Request $request)
    {
        $request->validate([
            'client_name' => 'required|string',
            'account_id'  => 'required|exists:accounts,id',
            'amount'      => 'required|numeric|min:0.01',
            'date'        => 'required|date',
            'description' => 'nullable|string',
        ]);

        $account = Account::where('id', $request->account_id)
            ->where('user_id', Auth::id())
            ->whereIn('type', ['mpesa', 'bank', 'savings'])
            ->first();

        if (!$account) {
            return back()
                ->withInput()
                ->withErrors(['account_id' => 'Please select a valid M-Pesa, Bank, or Savings account to return the money into.']);
        }

        // This client's funds, each annotated with how much borrowed-but-not-yet-
        // returned they're currently carrying, oldest fund first (FIFO).
        $fundsWithUnreturned = ClientFund::where('user_id', Auth::id())
            ->where('client_name', $request->client_name)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('received_date')
            ->get()
            ->map(function ($fund) {
                $borrowed = $fund->transactions()->where('is_borrowed', true)->sum('amount');
                $returned = $fund->transactions()->where('type', 'return')->sum('amount');
                $fund->unreturned_borrowed = max(0, $borrowed - $returned);
                return $fund;
            })
            ->filter(fn($f) => $f->unreturned_borrowed > 0)
            ->values();

        $available = $fundsWithUnreturned->sum('unreturned_borrowed');

        if ($available <= 0) {
            return back()->with('error', "{$request->client_name} has no unreturned borrowed balance to repay.");
        }

        if ($request->amount > $available) {
            return back()->with('error',
                "Amount exceeds {$request->client_name}'s unreturned borrowed total of KES " . number_format($available, 0) . '.'
            );
        }

        DB::beginTransaction();
        try {
            $liabilityCategory = Category::firstOrCreate(
                ['name' => 'Client Funds', 'user_id' => Auth::id()],
                ['type' => 'liability', 'parent_id' => null],
            );

            // Real deposit — increases the account's actual balance, same
            // mechanism as the original client fund receipt.
            $depositTransaction = Transaction::create([
                'user_id'        => Auth::id(),
                'account_id'     => $account->id,
                'category_id'    => $liabilityCategory->id,
                'amount'         => $request->amount,
                'date'           => $request->date,
                'period_date'    => $request->date,
                'description'    => "Returned borrowed funds — {$request->client_name}"
                    . ($request->description ? ": {$request->description}" : ''),
                'payment_method' => 'Client Fund',
            ]);

            $remaining = (float) $request->amount;

            foreach ($fundsWithUnreturned as $fund) {
                if ($remaining <= 0) {
                    break;
                }

                $portion = min($remaining, $fund->unreturned_borrowed);
                if ($portion <= 0) {
                    continue;
                }

                // Reverses the earlier borrow: less counts as "spent", so the
                // amount available for the client rises back up.
                $fund->amount_spent -= $portion;
                $fund->updateBalance();

                ClientFundTransaction::create([
                    'client_fund_id' => $fund->id,
                    'transaction_id' => $depositTransaction->id,
                    'transfer_id'    => null,
                    'type'           => 'return',
                    'is_borrowed'    => false,
                    'amount'         => $portion,
                    'date'           => $request->date,
                    'description'    => $request->description
                        ?: "Borrowed amount returned to {$fund->client_name}'s fund",
                ]);

                $remaining -= $portion;
            }

            DB::commit();
            $account->updateBalance();

            return back()->with('success',
                'KES ' . number_format($request->amount, 0)
                . " returned and applied against {$request->client_name}'s outstanding borrowed balance (oldest first)."
            );
        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to record return: ' . $e->getMessage());
        }
    }

    // ── diagnostic: how much is currently unrecorded as borrowed ───────────────

    /**
     * If the account holding pooled client money has a balance lower than the
     * sum of what ClientFund records say is still outstanding, the difference
     * is money that's been spent/withdrawn against client funds without ever
     * being logged as "borrowed" — either from before this feature existed,
     * or from a transfer that wasn't flagged as a client fund movement.
     */
    private function getUnrecordedBorrowShortfall(Account $account): float
    {
        $outstandingTotal = ClientFund::where('user_id', Auth::id())
            ->where('account_id', $account->id)
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['cancelled'])
            ->sum('balance');

        $shortfall = $outstandingTotal - (float)$account->current_balance;

        return max(0, round($shortfall, 2));
    }
}
