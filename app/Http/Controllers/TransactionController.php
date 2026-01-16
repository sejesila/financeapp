<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\MobileMoneyTypeUsage;
use App\Models\Transaction;
use App\Services\TransactionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Log;

class TransactionController extends Controller
{
    use AuthorizesRequests;

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
        'Rolling Funds'
    ];

    // Transaction type options for each account type
    private const MPESA_TRANSACTION_TYPES = [
        'send_money' => 'Send Money',
        'paybill' => 'PayBill',
        'buy_goods' => 'Buy Goods/Till Number',
        'pochi_la_biashara' => 'Pochi La Biashara',
    ];

    private const AIRTEL_TRANSACTION_TYPES = [
        'send_money' => 'Send Money',
        'paybill' => 'PayBill',
        'buy_goods' => 'Buy Goods/Till Number',
    ];

    protected TransactionService $transactionService;

    /**
     * Get transaction types sorted by usage count for a specific account type
     */
    private function getSortedTransactionTypes(string $accountType): array
    {
        $baseTypes = $accountType === 'mpesa' ? self::MPESA_TRANSACTION_TYPES : self::AIRTEL_TRANSACTION_TYPES;

        // Get usage stats for the user's account type
        $usageStats = MobileMoneyTypeUsage::where('user_id', Auth::id())
            ->where('account_type', $accountType)
            ->orderByDesc('usage_count')
            ->pluck('usage_count', 'transaction_type')
            ->toArray();

        // Sort types by usage count (higher first), then by original order
        $sortedTypes = [];
        foreach ($baseTypes as $key => $label) {
            $sortedTypes[] = [
                'key' => $key,
                'label' => $label,
                'usageCount' => $usageStats[$key] ?? 0,
            ];
        }

        usort($sortedTypes, function ($a, $b) {
            return $b['usageCount'] <=> $a['usageCount'];
        });

        return $sortedTypes;
    }

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
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

        $minYear = Transaction::where('user_id', Auth::id())->min(DB::raw('YEAR(date)')) ?? date('Y');
        $maxYear = date('Y');

        $query = Transaction::with(['category', 'account', 'feeTransaction'])
            ->where('user_id', Auth::id());

        if (!$showFees) {
            $query->where('is_transaction_fee', false);
        }

        if ($search) {
            $query->where('description', 'like', '%' . $search . '%');
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $query = $this->applyDateFilter($query, $filter, $startDate, $endDate);

        $transactions = $query->latest('date')->latest('id')->paginate(25)->withQueryString();

        $totals = $this->calculateTransactionTotals();
        $feeTotals = $this->calculateFeeTotals();

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
     * Get active accounts for the current user (excluding savings accounts and accounts with balance < 5)
     */
    private function getActiveAccounts()
    {
        return Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('type', '!=', 'savings')
            ->where('current_balance', '>=', 1)
            ->orderBy('name')
            ->get();
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

        // Get sorted transaction types for both account types
        $mpesaTransactionTypes = $this->getSortedTransactionTypes('mpesa');
        $airtelTransactionTypes = $this->getSortedTransactionTypes('airtel_money');

        $defaultMpesaType = MobileMoneyTypeUsage::getMostUsedType(Auth::id(), 'mpesa') ?? 'send_money';
        $defaultAirtelType = MobileMoneyTypeUsage::getMostUsedType(Auth::id(), 'airtel_money') ?? 'send_money';

        $mpesaAccount = $accounts->where('type', 'mpesa')->first();

        return view('transactions.create', array_merge(
            $categoryData,
            compact(
                'accounts',
                'mpesaCosts',
                'airtelCosts',
                'mpesaTransactionTypes',
                'airtelTransactionTypes',
                'defaultMpesaType',
                'defaultAirtelType',
                'mpesaAccount'
            )
        ));
    }

    /**
     * Get categories for forms (excluding system categories and parent categories)
     * Returns only child categories sorted by usage frequency
     */
    private function getCategoriesForForm()
    {
        $allCategories = Category::where('user_id', Auth::id())
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->whereNotIn('name', self::EXCLUDED_CATEGORIES)
            ->with(['parent' => function ($query) {
                $query->whereNotIn('name', self::EXCLUDED_CATEGORIES);
            }])
            ->orderBy('usage_count', 'desc')
            ->orderBy('name')
            ->get();

        $allCategories = $allCategories->filter(function ($category) {
            return $category->parent && !in_array($category->parent->name, self::EXCLUDED_CATEGORIES);
        });

        $parentCategories = Category::where('user_id', Auth::id())
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereNotIn('name', self::EXCLUDED_CATEGORIES)
            ->get()
            ->keyBy('id');

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

        $allCategoriesArray = $allCategories->map(function ($category) {
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
     * M-Pesa transaction costs (Kenya)
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
     * Airtel Money transaction costs (Kenya)
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
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request)
    {
        $this->authorize('create', Transaction::class);

        $validated = $request->validated();

        try {
            $account = Account::findOrFail($validated['account_id']);
            $oldBalance = $account->current_balance;

            $transaction = $this->transactionService->createTransaction($validated);

            Category::find($validated['category_id'])?->increment('usage_count');

            if (isset($validated['mobile_money_type']) && in_array($account->type, ['mpesa', 'airtel_money'])) {
                MobileMoneyTypeUsage::incrementUsage(
                    Auth::id(),
                    $account->type,
                    $validated['mobile_money_type']
                );
            }

            $account->refresh();

            $totalAmount = $transaction->amount;
            if ($transaction->feeTransaction) {
                $totalAmount += $transaction->feeTransaction->amount;
            }

            $successMessage = $this->buildSuccessMessage($transaction);

            return redirect()->route('transactions.index')
                ->with('success', $successMessage)
                ->with('show_balance_modal', true)
                ->with('account_name', $account->name)
                ->with('old_balance', number_format($oldBalance, 2))
                ->with('new_balance', number_format($account->current_balance, 2))
                ->with('transaction_amount', number_format($totalAmount, 2))
                ->with('transaction_type', $transaction->category->type);

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Build the success message including fee information if applicable
     */
    private function buildSuccessMessage(Transaction $transaction): string
    {
        $message = 'Transaction recorded successfully';

        if ($transaction->feeTransaction) {
            $feeAmount = number_format($transaction->feeTransaction->amount, 2);
            $message .= " (including KSh {$feeAmount} transaction fee)";
        }

        return $message;
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

        if ($transaction->is_transaction_fee) {
            return redirect()->back()
                ->with('error', 'System-generated transaction fees cannot be edited.');
        }

        $categoryData = $this->getCategoriesForForm();
        $accounts = $this->getActiveAccounts();
        $mpesaCosts = $this->getMpesaTransactionCosts();
        $airtelCosts = $this->getAirtelMoneyTransactionCosts();

        $mpesaTransactionTypes = $this->getSortedTransactionTypes('mpesa');
        $airtelTransactionTypes = $this->getSortedTransactionTypes('airtel_money');

        $defaultMpesaType = MobileMoneyTypeUsage::getMostUsedType(Auth::id(), 'mpesa') ?? 'send_money';
        $defaultAirtelType = MobileMoneyTypeUsage::getMostUsedType(Auth::id(), 'airtel_money') ?? 'send_money';

        $mpesaAccount = $accounts->where('type', 'mpesa')->first();

        return view('transactions.edit', array_merge(
            $categoryData,
            compact(
                'transaction',
                'accounts',
                'mpesaCosts',
                'airtelCosts',
                'mpesaTransactionTypes',
                'airtelTransactionTypes',
                'defaultMpesaType',
                'defaultAirtelType',
                'mpesaAccount'
            )
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
            $updatedTransaction = $this->transactionService->updateTransaction($transaction, $validated);

            if ($transaction->category_id != $validated['category_id']) {
                $category = Category::find($validated['category_id']);
                if ($category) {
                    $category->increment('usage_count');
                }
            }

            return redirect()->route('transactions.show', $updatedTransaction)
                ->with('success', 'Transaction updated successfully!');

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Soft delete the specified transaction
     */
    public function destroy(Transaction $transaction)
    {
        $this->authorize('delete', $transaction);

        if ($transaction->is_transaction_fee) {
            return redirect()->back()
                ->with('error', 'System-generated transaction fees cannot be deleted directly. Delete the main transaction instead.');
        }

        DB::beginTransaction();

        try {
            $account = $transaction->account;

            if ($transaction->related_fee_transaction_id) {
                $feeTransaction = Transaction::find($transaction->related_fee_transaction_id);
                if ($feeTransaction) {
                    $feeTransaction->delete();
                }
            }

            $transaction->delete();
            $account->updateBalance();

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', 'Transaction deleted successfully. Account balance has been updated.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Transaction deletion failed: ' . $e->getMessage());

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
            $transaction->restore();

            if ($transaction->related_fee_transaction_id) {
                $feeTransaction = Transaction::onlyTrashed()->find($transaction->related_fee_transaction_id);
                if ($feeTransaction) {
                    $feeTransaction->restore();
                }
            }

            $transaction->account->updateBalance();

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', 'Transaction restored successfully.');

        } catch (Exception $e) {
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

            if ($transaction->related_fee_transaction_id) {
                $feeTransaction = Transaction::onlyTrashed()->find($transaction->related_fee_transaction_id);
                if ($feeTransaction) {
                    $feeTransaction->forceDelete();
                }
            }

            $transaction->forceDelete();

            if ($account) {
                $account->updateBalance();
            }

            DB::commit();

            return redirect()->route('transactions.trash')
                ->with('success', 'Transaction permanently deleted.');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to permanently delete transaction: ' . $e->getMessage());
        }
    }
}
