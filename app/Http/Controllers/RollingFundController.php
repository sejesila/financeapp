<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\RollingFund;
use App\Models\Transaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RollingFundController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $rollingFund = RollingFund::where('user_id', auth()->id())
            ->with('account')
            ->orderBy('date', 'desc');

        // Apply filters
        switch ($filter) {
            case 'pending':
                $rollingFund->where('status', 'pending');
                break;
            case 'completed':
                $rollingFund->where('status', 'completed');
                break;
            case 'wins':
                $rollingFund->where('status', 'completed')
                    ->whereColumn('winnings', '>', 'stake_amount');
                break;
            case 'losses':
                $rollingFund->where('status', 'completed')
                    ->whereColumn('winnings', '<', 'stake_amount');
                break;
            case 'break_even':
                $rollingFund->where('status', 'completed')
                    ->whereColumn('winnings', '=', 'stake_amount');
                break;
            case 'high_wins':
                // High wins: profit of 50% or more
                $rollingFund->where('status', 'completed')
                    ->whereRaw('(winnings - stake_amount) >= (stake_amount * 0.5)');
                break;
            case 'significant_losses':
                // Significant losses: loss of 50% or more
                $rollingFund->where('status', 'completed')
                    ->whereRaw('(stake_amount - winnings) >= (stake_amount * 0.5)');
                break;
            case 'last_7_days':
                $rollingFund->where('date', '>=', now()->subDays(7));
                break;
            case 'last_30_days':
                $rollingFund->where('date', '>=', now()->subDays(30));
                break;
            case 'last_90_days':
                $rollingFund->where('date', '>=', now()->subDays(90));
                break;
            case 'this_month':
                $rollingFund->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
                break;
            case 'this_year':
                $rollingFund->whereYear('date', now()->year);
                break;
            case 'custom_range':
                if ($request->has('date_from') && $request->date_from) {
                    $rollingFund->where('date', '>=', $request->date_from);
                }
                if ($request->has('date_to') && $request->date_to) {
                    $rollingFund->where('date', '<=', $request->date_to);
                }
                break;
        }

        $wagers = $rollingFund->paginate(15)->appends($request->except('page'));

        // Calculate statistics (only for completed wagers)
        $completedSessions = RollingFund::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->get();

        // Fix: Use filter() with closures for proper comparison
        $wins = $completedSessions->filter(function ($wager) {
            return $wager->winnings > $wager->stake_amount;
        })->count();

        $losses = $completedSessions->filter(function ($wager) {
            return $wager->winnings < $wager->stake_amount;
        })->count();

        $breakEven = $completedSessions->filter(function ($wager) {
            return $wager->winnings == $wager->stake_amount;
        })->count();

        // Calculate biggest win and loss properly
        $biggestWin = 0;
        $biggestLoss = 0;

        if ($completedSessions->count() > 0) {
            $biggestWin = $completedSessions->map(function ($wager) {
                return $wager->winnings - $wager->stake_amount;
            })->max() ?? 0;

            $biggestLoss = $completedSessions->map(function ($wager) {
                return $wager->winnings - $wager->stake_amount;
            })->min() ?? 0;
        }

        $stats = [
            'total_staked' => RollingFund::where('user_id', auth()->id())->sum('stake_amount'),
            'total_winnings' => $completedSessions->sum('winnings'),
            'net_profit_loss' => $completedSessions->sum('winnings') - $completedSessions->sum('stake_amount'),
            'wins' => $wins,
            'losses' => $losses,
            'break_even' => $breakEven,
            'win_rate' => $completedSessions->count() > 0
                ? round(($wins / $completedSessions->count()) * 100, 1)
                : 0,
            'biggest_win' => $biggestWin,
            'biggest_loss' => $biggestLoss,
            'pending_count' => RollingFund::where('user_id', auth()->id())->where('status', 'pending')->count(),
            'pending_amount' => RollingFund::where('user_id', auth()->id())->where('status', 'pending')->sum('stake_amount'),
        ];

        return view('rolling-funds.index', compact('wagers', 'stats', 'filter'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'account_id' => 'required|exists:accounts,id',
            'stake_amount' => 'required|numeric|min:0.01',
        ]);

        $account = Account::findOrFail($validated['account_id']);

        // Check if account belongs to user
        if ($account->user_id !== auth()->id()) {
            return back()->withErrors(['account_id' => 'Invalid account selected.']);
        }

        // Check sufficient balance (no fee deduction)
        if ($account->current_balance < $validated['stake_amount']) {
            return back()->withErrors(['stake_amount' => 'Insufficient balance in account.']);
        }

        try {
            DB::beginTransaction();

            // Create rolling fund record with pending status
            $rollingFund = RollingFund::create([
                'user_id' => auth()->id(),
                'account_id' => $validated['account_id'],
                'date' => $validated['date'],
                'stake_amount' => $validated['stake_amount'],
                'winnings' => null,
                'status' => 'pending',
            ]);

            // Get or create expense category for Rolling Funds
            $category = $this->getOrCreateCategory('expense', 'Rolling Funds', 'Entertainment');

            // Create expense transaction for the stake (no fee)
            Transaction::create([
                'user_id' => auth()->id(),
                'account_id' => $validated['account_id'],
                'category_id' => $category->id,
                'date' => $validated['date'],
                'period_date' => $validated['date'],
                'amount' => $validated['stake_amount'],
                'description' => 'Rolling Funds Out',
                'payment_method' => 'Rolling Funds',
                'is_transaction_fee' => false,
            ]);

            DB::commit();

            // Update account balance AFTER commit
            $account->updateBalance();

            return redirect()->route('rolling-funds.show', $rollingFund)
                ->with('success', 'Investment recorded successfully! Update the outcome when ready.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Rolling Fund Store Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);
            return back()->withErrors(['error' => 'Failed to record investment: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function create()
    {
        $accounts = Account::where('user_id', auth()->id())
            ->where('is_active', true)
            ->get();

        return view('rolling-funds.create', compact('accounts'));
    }

    private function getOrCreateCategory($type, $name, $parentName)
    {
        $category = Category::where('user_id', auth()->id())
            ->where('type', $type)
            ->where('name', $name)
            ->first();

        if (!$category) {
            $parentCategory = Category::where('user_id', auth()->id())
                ->where('type', $type)
                ->whereNull('parent_id')
                ->where('name', $parentName)
                ->first();

            if (!$parentCategory) {
                $parentCategory = Category::create([
                    'user_id' => auth()->id(),
                    'name' => $parentName,
                    'type' => $type,
                    'is_active' => true,
                ]);
            }

            $category = Category::create([
                'user_id' => auth()->id(),
                'parent_id' => $parentCategory->id,
                'name' => $name,
                'type' => $type,
                'is_active' => true,
            ]);
        }

        return $category;
    }

    public function show(RollingFund $rollingFund)
    {
        // Check ownership
        if ($rollingFund->user_id !== auth()->id()) {
            abort(403);
        }

        $rollingFund->load('account');

        return view('rolling-funds.show', compact('rollingFund'));
    }

    public function recordOutcome(Request $request, RollingFund $rollingFund)
    {
        // Check ownership
        if ($rollingFund->user_id !== auth()->id()) {
            abort(403);
        }

        // Check if already completed
        if ($rollingFund->status === 'completed') {
            return back()->with('error', 'This wager outcome has already been recorded.');
        }

        $validated = $request->validate([
            'winnings' => 'required|numeric|min:0',
            'completed_date' => 'required|date|after_or_equal:' . $rollingFund->date->format('Y-m-d') . '|before_or_equal:today',
            'outcome_notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Update rolling fund with outcome
            $rollingFund->winnings = $validated['winnings'];
            $rollingFund->status = 'completed';
            $rollingFund->completed_date = $validated['completed_date'];

            // Append outcome notes to existing notes
            if (!empty($validated['outcome_notes'])) {
                $rollingFund->notes = $rollingFund->notes
                    ? $rollingFund->notes . "\n\nOutcome: " . $validated['outcome_notes']
                    : "Outcome: " . $validated['outcome_notes'];
            }

            $rollingFund->save();

            // If there were winnings, record income transaction
            if ($validated['winnings'] > 0) {
                $incomeCategory = $this->getOrCreateCategory('income', 'Rolling Funds', 'Other Income');

                Transaction::create([
                    'user_id' => auth()->id(),
                    'account_id' => $rollingFund->account_id,
                    'category_id' => $incomeCategory->id,
                    'date' => $validated['completed_date'],
                    'period_date' => $validated['completed_date'],
                    'amount' => $validated['winnings'],
                    'description' => 'Rolling Funds Returns - ' . ($rollingFund->platform ?? 'Session'),
                    'payment_method' => 'Rolling Funds',
                    'is_transaction_fee' => false,
                ]);
            }

            DB::commit();

            // Update account balance AFTER commit
            $account = Account::findOrFail($rollingFund->account_id);
            $account->updateBalance();

            $netResult = $validated['winnings'] - $rollingFund->stake_amount;
            $message = $netResult > 0
                ? "Positive outcome recorded! You gained KES " . number_format($netResult, 0)
                : ($netResult < 0
                    ? "Negative outcome recorded. Loss: KES " . number_format(abs($netResult), 0)
                    : "Break even outcome recorded.");

            return redirect()->route('rolling-funds.show', $rollingFund)
                ->with('success', $message);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Rolling Fund Record Outcome Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'rolling_fund_id' => $rollingFund->id,
            ]);
            return back()->with('error', 'Failed to record outcome: ' . $e->getMessage());
        }
    }

    public function destroy(RollingFund $rollingFund)
    {
        // Check ownership
        if ($rollingFund->user_id !== auth()->id()) {
            abort(403);
        }

        try {
            DB::beginTransaction();

            // Delete related transactions
            Transaction::where('user_id', auth()->id())
                ->where('account_id', $rollingFund->account_id)
                ->where(function ($query) use ($rollingFund) {
                    $query->where('date', $rollingFund->date);
                    if ($rollingFund->completed_date) {
                        $query->orWhere('date', $rollingFund->completed_date);
                    }
                })
                ->where(function ($query) {
                    $query->where('payment_method', 'Rolling Funds')
                        ->orWhere(function ($subQuery) {
                            $subQuery->where('is_transaction_fee', true)
                                ->where('description', 'like', '%Rolling Funds%');
                        });
                })
                ->delete();

            $rollingFund->delete();

            DB::commit();

            // Update account balance AFTER commit
            $account = Account::findOrFail($rollingFund->account_id);
            $account->updateBalance();

            return redirect()->route('rolling-funds.index')
                ->with('success', 'Session deleted successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Rolling Fund Destroy Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'rolling_fund_id' => $rollingFund->id,
            ]);
            return back()->with('error', 'Failed to delete wager: ' . $e->getMessage());
        }
    }
}
