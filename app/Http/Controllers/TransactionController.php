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

    // System categories that should be excluded from manual operations
    private const EXCLUDED_CATEGORIES = [
        'Income',
        'Loans',
        'Loan Receipt',
        'Loan Repayment',
        'Excise Duty',
        'Loan Fees Refund',
        'Facility Fee Refund',
        'Transaction Fees',
        'Balance Adjustment',
    ];

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
     * Get categories for forms (excluding system categories and parent categories)
     * Returns only child categories sorted by usage frequency
     */
    private function getCategoriesForForm()
    {
        // Get all child categories sorted by usage frequency (this will be used for the dropdown)
        $allCategories = Category::where('user_id', Auth::id())
            ->whereNotNull('parent_id') // Only child categories
            ->where('is_active', true)
            ->whereNotIn('name', self::EXCLUDED_CATEGORIES)
            ->with(['parent' => function($query) {
                // Also exclude parent categories that are in excluded list
                $query->whereNotIn('name', self::EXCLUDED_CATEGORIES);
            }])
            ->orderBy('usage_count', 'desc')
            ->orderBy('name')
            ->get();

        // Filter out any categories whose parent is in the excluded list
        $allCategories = $allCategories->filter(function($category) {
            return $category->parent && !in_array($category->parent->name, self::EXCLUDED_CATEGORIES);
        });

        // Get parent categories for grouping (UI purposes)
        $parentCategories = Category::where('user_id', Auth::id())
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereNotIn('name', self::EXCLUDED_CATEGORIES)
            ->get()
            ->keyBy('id');

        // Group categories by parent for structured display
        $categoryGroups = $parentCategories->map(function ($parent) use ($allCategories) {
            return [
                'id' => $parent->id,
                'name' => $parent->name,
                'icon' => $parent->icon,
                'type' => $parent->type,
                'children' => $allCategories->where('parent_id', $parent->id)->values()
            ];
        })->filter(function ($group) {
            return $group['children']->isNotEmpty();
        })->values();

        // Convert allCategories to array for JSON serialization
        // Only include the fields we need
        $allCategoriesArray = $allCategories->map(function($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'parent_id' => $category->parent_id,
                'usage_count' => $category->usage_count,
            ];
        })->values()->toArray();

        return compact('categoryGroups', 'allCategoriesArray');
    }
    /**
     * Get active accounts for the current user
     */
    private function getActiveAccounts()
    {
        return \App\Models\Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
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
     * Calculate transaction totals for a given query
     */
    private function calculateTransactionTotals()
    {
        $baseQuery = Transaction::where('user_id', Auth::id())
            ->where('is_transaction_fee', false);

        return [
            'totalToday' => (clone $baseQuery)->whereDate('date', today())->sum('amount'),
            'totalYesterday' => (clone $baseQuery)->whereDate('date', today()->subDay())->sum('amount'),
            'totalThisWeek' => (clone $baseQuery)->whereBetween('date', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->sum('amount'),
            'totalLastWeek' => (clone $baseQuery)->whereBetween('date', [
                now()->subWeek()->startOfWeek(),
                now()->subWeek()->endOfWeek()
            ])->sum('amount'),
            'totalThisMonth' => (clone $baseQuery)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('amount'),
            'totalLastMonth' => (clone $baseQuery)
                ->whereMonth('date', now()->subMonth()->month)
                ->whereYear('date', now()->subMonth()->year)
                ->sum('amount'),
            'totalAll' => (clone $baseQuery)->sum('amount'),
        ];
    }

    /**
     * Calculate transaction fee totals
     */
    private function calculateFeeTotals()
    {
        $feeQuery = Transaction::where('user_id', Auth::id())
            ->where('is_transaction_fee', true);

        return [
            'totalFeesToday' => (clone $feeQuery)->whereDate('date', today())->sum('amount'),
            'totalFeesThisMonth' => (clone $feeQuery)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('amount'),
            'totalFeesAll' => (clone $feeQuery)->sum('amount'),
        ];
    }

    /**
     * Apply date filters to a query based on filter type
     */
    private function applyDateFilter($query, $filter, $startDate = null, $endDate = null)
    {
        if ($filter === 'custom' && $startDate && $endDate) {
            return $query->whereBetween('date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        switch ($filter) {
            case 'today':
                return $query->whereDate('date', today());
            case 'yesterday':
                return $query->whereDate('date', today()->subDay());
            case 'this_week':
                return $query->whereBetween('date', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]);
            case 'last_week':
                return $query->whereBetween('date', [
                    now()->subWeek()->startOfWeek(),
                    now()->subWeek()->endOfWeek()
                ]);
            case 'this_month':
                return $query->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
            case 'last_month':
                return $query->whereMonth('date', now()->subMonth()->month)
                    ->whereYear('date', now()->subMonth()->year);
            case 'this_year':
                return $query->whereYear('date', now()->year);
            default:
                return $query;
        }
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
        $minYear = Transaction::where('user_id', Auth::id())->min(DB::raw('YEAR(date)')) ?? date('Y');
        $maxYear = date('Y');

        $query = Transaction::with(['category', 'account', 'feeTransaction'])
            ->where('user_id', Auth::id());

        // By default, exclude transaction fees from the main listing
        if (!$showFees) {
            $query->where('is_transaction_fee', false);
        }

        // Apply filters
        if ($search) {
            $query->where('description', 'like', '%' . $search . '%');
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        // Apply date filters
        $query = $this->applyDateFilter($query, $filter, $startDate, $endDate);

        $transactions = $query->latest('date')->latest('id')->paginate(50)->withQueryString();

        // Calculate totals
        $totals = $this->calculateTransactionTotals();
        $feeTotals = $this->calculateFeeTotals();

        // Get categories and accounts for filters
        $categories = Category::where('user_id', Auth::id())
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $accounts = $this->getActiveAccounts();

        return view('transactions.index', array_merge(
            compact(
                'transactions',
                'filter',
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
            ),
            $totals,
            $feeTotals
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categoryData = $this->getCategoriesForForm();

        $accounts = $this->getActiveAccounts();
        $mpesaCosts = $this->getMpesaTransactionCosts();
        $airtelCosts = $this->getAirtelMoneyTransactionCosts();

        return view('transactions.create', array_merge(
            $categoryData,
            compact('accounts', 'mpesaCosts', 'airtelCosts')
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
            $account = \App\Models\Account::findOrFail($validated['account_id']);
            $oldBalance = $account->current_balance;

            $transaction = $this->transactionService->createTransaction($validated);

            // Increment category usage count
            $category = Category::find($validated['category_id']);
            if ($category) {
                $category->increment('usage_count');
            }

            $account->refresh();

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
            return back()->with('error', $e->getMessage())->withInput();
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

        $categoryData = $this->getCategoriesForForm();
        $accounts = $this->getActiveAccounts();
        $mpesaCosts = $this->getMpesaTransactionCosts();
        $airtelCosts = $this->getAirtelMoneyTransactionCosts();

        return view('transactions.edit', array_merge(
            $categoryData,
            compact('transaction', 'accounts', 'mpesaCosts', 'airtelCosts')
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
            'mobile_money_type' => 'nullable|in:send_money,paybill,buy_goods,pochi_la_biashara,withdraw',
        ]);

        try {
            // Update transaction using service
            $updatedTransaction = $this->transactionService->updateTransaction($transaction, $validated);

            return redirect()->route('transactions.show', $updatedTransaction)
                ->with('success', 'Transaction updated successfully!');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Soft delete the specified transaction
     */
    public function destroy(Transaction $transaction)
    {
        $this->authorize('delete', $transaction);

        // Don't allow deleting system-generated fees directly
        if ($transaction->is_transaction_fee) {
            return redirect()->back()
                ->with('error', 'System-generated transaction fees cannot be deleted directly. Delete the main transaction instead.');
        }

        DB::beginTransaction();

        try {
            // Store account for balance update
            $account = $transaction->account;

            // If this transaction has a related fee, delete it too
            if ($transaction->related_fee_transaction_id) {
                $feeTransaction = Transaction::find($transaction->related_fee_transaction_id);
                if ($feeTransaction) {
                    $feeTransaction->delete(); // Soft delete
                }
            }

            // Soft delete the main transaction
            $transaction->delete();

            // Recalculate account balance
            $account->updateBalance();

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', 'Transaction deleted successfully. Account balance has been updated.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Transaction deletion failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to delete transaction: ' . $e->getMessage());
        }
    }

    /**
     * Restore a soft-deleted transaction
     */
    public function restore($id)
    {
        $transaction = Transaction::onlyTrashed()
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        DB::beginTransaction();

        try {
            // Restore the transaction
            $transaction->restore();

            // Restore related fee if exists
            if ($transaction->related_fee_transaction_id) {
                $feeTransaction = Transaction::onlyTrashed()->find($transaction->related_fee_transaction_id);
                if ($feeTransaction) {
                    $feeTransaction->restore();
                }
            }

            // Recalculate account balance
            $transaction->account->updateBalance();

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', 'Transaction restored successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to restore transaction: ' . $e->getMessage());
        }
    }

    /**
     * Permanently delete a transaction
     */
    public function forceDestroy($id)
    {
        $transaction = Transaction::onlyTrashed()
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $this->authorize('forceDelete', $transaction);

        DB::beginTransaction();

        try {
            $account = $transaction->account;

            // Force delete related fee
            if ($transaction->related_fee_transaction_id) {
                $feeTransaction = Transaction::onlyTrashed()->find($transaction->related_fee_transaction_id);
                if ($feeTransaction) {
                    $feeTransaction->forceDelete();
                }
            }

            // Permanently delete
            $transaction->forceDelete();

            // Recalculate balance
            if ($account) {
                $account->updateBalance();
            }

            DB::commit();

            return redirect()->route('transactions.trash')
                ->with('success', 'Transaction permanently deleted.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to permanently delete transaction: ' . $e->getMessage());
        }
    }
}



