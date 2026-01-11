<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    use AuthorizesRequests;

    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * M-Pesa transaction costs (Kenya) - for frontend display
     */
    private function getMpesaTransactionCosts()
    {
        return [
            'send_money' => [
                ['min' => 1, 'max' => 100, 'cost' => 0],
                ['min' => 101, 'max' => 500, 'cost' => 7],
                ['min' => 501, 'max' => 1000, 'cost' => 13],
                ['min' => 1001, 'max' => 1500, 'cost' => 23],
                ['min' => 1501, 'max' => 2500, 'cost' => 33],
                ['min' => 2501, 'max' => 3500, 'cost' => 53],
                ['min' => 3501, 'max' => 5000, 'cost' => 57],
                ['min' => 5001, 'max' => 7500, 'cost' => 78],
                ['min' => 7501, 'max' => 10000, 'cost' => 90],
                ['min' => 10001, 'max' => 15000, 'cost' => 100],
                ['min' => 15001, 'max' => 20000, 'cost' => 105],
                ['min' => 20001, 'max' => 35000, 'cost' => 108],
                ['min' => 35001, 'max' => 50000, 'cost' => 110],
                ['min' => 50001, 'max' => 150000, 'cost' => 112],
                ['min' => 150001, 'max' => 250000, 'cost' => 115],
                ['min' => 250001, 'max' => 500000, 'cost' => 117],
            ],
            'paybill' => [
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
                ['min' => 50001, 'max' => 70000, 'cost' => 108],
                ['min' => 70001, 'max' => 250000, 'cost' => 108],
            ],
            'buy_goods' => [
                ['min' => 1, 'max' => 500000, 'cost' => 0],
            ],
            'pochi_la_biashara' => [
                ['min' => 1, 'max' => 100, 'cost' => 0],
                ['min' => 101, 'max' => 500, 'cost' => 7],
                ['min' => 501, 'max' => 1000, 'cost' => 13],
                ['min' => 1001, 'max' => 1500, 'cost' => 23],
                ['min' => 1501, 'max' => 2500, 'cost' => 33],
                ['min' => 2501, 'max' => 3500, 'cost' => 53],
                ['min' => 3501, 'max' => 5000, 'cost' => 57],
                ['min' => 5001, 'max' => 7500, 'cost' => 78],
                ['min' => 7501, 'max' => 10000, 'cost' => 90],
                ['min' => 10001, 'max' => 15000, 'cost' => 100],
                ['min' => 15001, 'max' => 20000, 'cost' => 105],
                ['min' => 20001, 'max' => 35000, 'cost' => 108],
                ['min' => 35001, 'max' => 50000, 'cost' => 110],
                ['min' => 50001, 'max' => 150000, 'cost' => 112],
                ['min' => 150001, 'max' => 250000, 'cost' => 115],
                ['min' => 250001, 'max' => 500000, 'cost' => 117],
            ],
        ];
    }

    /**
     * Airtel Money transaction costs (Kenya) - for frontend display
     */
    private function getAirtelMoneyTransactionCosts()
    {
        return [
            'send_money' => [
                ['min' => 10, 'max' => 100, 'cost' => 0],
                ['min' => 101, 'max' => 500, 'cost' => 7],
                ['min' => 501, 'max' => 1000, 'cost' => 15],
                ['min' => 1001, 'max' => 1500, 'cost' => 25],
                ['min' => 1501, 'max' => 2500, 'cost' => 35],
                ['min' => 2501, 'max' => 3500, 'cost' => 55],
                ['min' => 3501, 'max' => 5000, 'cost' => 65],
                ['min' => 5001, 'max' => 7500, 'cost' => 80],
                ['min' => 7501, 'max' => 10000, 'cost' => 95],
                ['min' => 10001, 'max' => 15000, 'cost' => 105],
                ['min' => 15001, 'max' => 20000, 'cost' => 110],
                ['min' => 20001, 'max' => 35000, 'cost' => 115],
                ['min' => 35001, 'max' => 50000, 'cost' => 120],
                ['min' => 50001, 'max' => 70000, 'cost' => 125],
                ['min' => 70001, 'max' => 150000, 'cost' => 130],
            ],
            'paybill' => [
                ['min' => 1, 'max' => 150000, 'cost' => 0],
            ],
            'buy_goods' => [
                ['min' => 1, 'max' => 150000, 'cost' => 0],
            ],
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $search = $request->get('search');
        $categoryId = $request->get('category_id');
        $accountId = $request->get('account_id');
        $showFees = $request->get('show_fees', false);

        // Calculate dynamic year/month ranges
        $minYear = Transaction::where('user_id', Auth::id())->min(DB::raw('YEAR(date)'));
        $minYear = $minYear ?? date('Y');
        $maxYear = date('Y');

        $query = Transaction::with(['category', 'account', 'feeTransaction'])
            ->where('user_id', Auth::id());

        // By default, exclude transaction fees from the main listing
        if (!$showFees) {
            $query->where('is_transaction_fee', false);
        }

        // Apply search filter
        if ($search) {
            $query->where('description', 'like', '%' . $search . '%');
        }

        // Apply category filter
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Apply account filter
        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        // Apply date filters
        if ($filter === 'custom' && $startDate && $endDate) {
            $query->whereBetween('date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        } else {
            switch ($filter) {
                case 'today':
                    $query->whereDate('date', today());
                    break;
                case 'yesterday':
                    $query->whereDate('date', today()->subDay());
                    break;
                case 'this_week':
                    $query->whereBetween('date', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ]);
                    break;
                case 'last_week':
                    $query->whereBetween('date', [
                        now()->subWeek()->startOfWeek(),
                        now()->subWeek()->endOfWeek()
                    ]);
                    break;
                case 'this_month':
                    $query->whereMonth('date', now()->month)
                        ->whereYear('date', now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('date', now()->subMonth()->month)
                        ->whereYear('date', now()->subMonth()->year);
                    break;
                case 'this_year':
                    $query->whereYear('date', now()->year);
                    break;
                case 'all':
                default:
                    break;
            }
        }

        $transactions = $query->latest('date')->latest('id')->paginate(50)->withQueryString();

        // Calculate totals (excluding fees by default)
        $userTransactions = Transaction::where('user_id', Auth::id())->where('is_transaction_fee', false);

        $totalToday = (clone $userTransactions)->whereDate('date', today())->sum('amount');
        $totalYesterday = (clone $userTransactions)->whereDate('date', today()->subDay())->sum('amount');
        $totalThisWeek = (clone $userTransactions)->whereBetween('date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->sum('amount');
        $totalLastWeek = (clone $userTransactions)->whereBetween('date', [
            now()->subWeek()->startOfWeek(),
            now()->subWeek()->endOfWeek()
        ])->sum('amount');
        $totalThisMonth = (clone $userTransactions)->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');
        $totalLastMonth = (clone $userTransactions)->whereMonth('date', now()->subMonth()->month)
            ->whereYear('date', now()->subMonth()->year)
            ->sum('amount');
        $totalAll = Transaction::where('user_id', Auth::id())->where('is_transaction_fee', false)->sum('amount');

        // Calculate total transaction fees
        $totalFeesToday = Transaction::where('user_id', Auth::id())
            ->where('is_transaction_fee', true)
            ->whereDate('date', today())
            ->sum('amount');
        $totalFeesThisMonth = Transaction::where('user_id', Auth::id())
            ->where('is_transaction_fee', true)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');
        $totalFeesAll = Transaction::where('user_id', Auth::id())
            ->where('is_transaction_fee', true)
            ->sum('amount');

        // Get categories and accounts for filters
        $categories = Category::where('user_id', Auth::id())
            ->orderBy('type')
            ->orderBy('name')
            ->get();
        $accounts = \App\Models\Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('transactions.index', compact(
            'transactions',
            'filter',
            'totalToday',
            'totalYesterday',
            'totalThisWeek',
            'totalLastWeek',
            'totalThisMonth',
            'totalLastMonth',
            'totalAll',
            'totalFeesToday',
            'totalFeesThisMonth',
            'totalFeesAll',
            'minYear',
            'maxYear',
            'categories',
            'accounts',
            'search',
            'categoryId',
            'accountId',
            'startDate',
            'endDate',
            'showFees'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Special system categories to exclude from manual transaction creation
        $excludedCategories = [
            'Income',
            'Loans',
            'Loan Receipt',
            'Loan Repayment',
            'Excise Duty',
            'Loan Fees Refund',
            'Facility Fee Refund',
            'Transaction Fees'
        ];

        // Get parent categories with their children (hierarchical structure)
        $categoryGroups = Category::where('user_id', Auth::id())
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereNotIn('name', $excludedCategories)
            ->with(['children' => function ($query) use ($excludedCategories) {
                $query->where('is_active', true)
                    ->whereNotIn('name', $excludedCategories)
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get()
            ->filter(function ($category) {
                return $category->children->isNotEmpty() || !$category->parent_id;
            })
            ->values();

        // Fallback: flat list of all leaf categories
        $categories = Category::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereNotIn('name', $excludedCategories)
            ->orderBy('name')
            ->get();

        // Get frequently used categories
        $frequentCategories = Category::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereNotIn('name', $excludedCategories)
            ->where('usage_count', '>', 0)
            ->orderBy('usage_count', 'desc')
            ->limit(5)
            ->get();

        $accounts = \App\Models\Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $mpesaCosts = $this->getMpesaTransactionCosts();
        $airtelCosts = $this->getAirtelMoneyTransactionCosts();

        return view('transactions.create', compact(
            'categoryGroups',
            'categories',
            'frequentCategories',
            'accounts',
            'mpesaCosts',
            'airtelCosts'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Transaction::class);

        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'account_id' => 'required|exists:accounts,id',
            'mobile_money_type' => 'nullable|in:send_money,paybill,buy_goods,pochi_la_biashara',
        ]);

        try {
            // Store old balance for display
            $account = \App\Models\Account::findOrFail($validated['account_id']);
            $oldBalance = $account->current_balance;

            // Create transaction using service
            $transaction = $this->transactionService->createTransaction($validated);

            // Refresh account to get updated balance
            $account->refresh();

            // Calculate total amount including fees
            $totalAmount = $transaction->amount;
            if ($transaction->feeTransaction) {
                $totalAmount += $transaction->feeTransaction->amount;
            }

            $successMessage = 'Transaction recorded successfully';
            if ($transaction->feeTransaction) {
                $successMessage .= ' (including KSh ' . number_format($transaction->feeTransaction->amount, 2) . ' transaction fee)';
            }

            return redirect()->route('transactions.index')
                ->with('success', $successMessage)
                ->with('show_balance_modal', true)
                ->with('account_name', $account->name)
                ->with('old_balance', number_format($oldBalance, 2))
                ->with('new_balance', number_format($account->current_balance, 2))
                ->with('transaction_amount', number_format($totalAmount, 2))
                ->with('transaction_type', $transaction->category->type);

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource (read-only view).
     */
    public function show(Transaction $transaction)
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'category', 'feeTransaction', 'mainTransaction']);
        return view('transactions.show', compact('transaction'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        // Don't allow editing system-generated transactions
        if ($transaction->is_transaction_fee) {
            return redirect()->back()
                ->with('error', 'System-generated transaction fees cannot be edited.');
        }

        // Special system categories to exclude
        $excludedCategories = [
            'Income',
            'Loans',
            'Loan Receipt',
            'Loan Repayment',
            'Excise Duty',
            'Loan Fees Refund',
            'Facility Fee Refund',
            'Transaction Fees',
            'Balance Adjustment'
        ];

        $categoryGroups = Category::where('user_id', Auth::id())
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereNotIn('name', $excludedCategories)
            ->with(['children' => function ($query) use ($excludedCategories) {
                $query->where('is_active', true)
                    ->whereNotIn('name', $excludedCategories)
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get()
            ->filter(function ($category) {
                return $category->children->isNotEmpty() || !$category->parent_id;
            })
            ->values();

        $categories = Category::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereNotIn('name', $excludedCategories)
            ->orderBy('name')
            ->get();

        $accounts = \App\Models\Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $mpesaCosts = $this->getMpesaTransactionCosts();
        $airtelCosts = $this->getAirtelMoneyTransactionCosts();

        return view('transactions.edit', compact(
            'transaction',
            'categoryGroups',
            'categories',
            'accounts',
            'mpesaCosts',
            'airtelCosts'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'account_id' => 'required|exists:accounts,id',
            'mobile_money_type' => 'nullable|in:send_money,paybill,buy_goods,pochi_la_biashara',
        ]);

        try {
            // Update transaction using service
            $updatedTransaction = $this->transactionService->updateTransaction($transaction, $validated);

            return redirect()->route('transactions.show', $updatedTransaction)
                ->with('success', 'Transaction updated successfully!');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction)
    {
        $this->authorize('delete', $transaction);

        try {
            $this->transactionService->deleteTransaction($transaction);

            return redirect()->route('transactions.index')
                ->with('success', 'Transaction deleted successfully!');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
