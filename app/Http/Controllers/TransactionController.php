<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    use AuthorizesRequests;

    /**
     * M-Pesa transaction costs (Kenya)
     */
    /**
     * M-Pesa transaction costs (Kenya) - Send Money & Pochi La Biashara
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
                ['min' => 1, 'max' => 500000, 'cost' => 0], // Till Number - no charges
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
                ['min' => 1, 'max' => 150000, 'cost' => 0], // Assuming no charges for paybill
            ],
            'buy_goods' => [
                ['min' => 1, 'max' => 150000, 'cost' => 0], // Assuming no charges for till
            ],
        ];
    }

    /**
     * Calculate transaction cost based on amount, account type, and transaction type
     */
    private function calculateTransactionCost($amount, $accountType, $transactionType = 'send_money')
    {
        $costs = [];

        if ($accountType === 'mpesa') {
            $allCosts = $this->getMpesaTransactionCosts();
            $costs = $allCosts[$transactionType] ?? $allCosts['send_money'];
        } elseif ($accountType === 'airtel_money') {
            $allCosts = $this->getAirtelMoneyTransactionCosts();
            $costs = $allCosts[$transactionType] ?? $allCosts['send_money'];
        } else {
            return 0; // No fees for other account types
        }

        // Find the appropriate cost tier
        foreach ($costs as $tier) {
            if ($amount >= $tier['min'] && $amount <= $tier['max']) {
                return $tier['cost'];
            }
        }

        // If amount exceeds all tiers, return the highest tier cost
        return end($costs)['cost'] ?? 0;
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
        $showFees = $request->get('show_fees', false); // Option to show/hide transaction fees

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
                // Keep only categories that have children or are standalone
                return $category->children->isNotEmpty() || !$category->parent_id;
            })
            ->values(); // Reset array keys

        // Fallback: flat list of all leaf categories (excluding system categories)
        $categories = Category::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereNotIn('name', $excludedCategories)
            ->orderBy('name')
            ->get();

        // Get frequently used categories (top 5, excluding system categories)
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

        // Pass all transaction costs with their types
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
            'transaction_cost' => 'nullable|numeric|min:0'
        ]);

        // Verify account ownership
        $account = \App\Models\Account::findOrFail($validated['account_id']);
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        // Verify category ownership
        $category = Category::findOrFail($validated['category_id']);
        if ($category->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this category.');
        }

        // Get transaction type, default to send_money
        $transactionType = $validated['mobile_money_type'] ?? 'send_money';

        // Calculate transaction cost based on account type and transaction type
        $transactionCost = $this->calculateTransactionCost(
            $validated['amount'],
            $account->type,
            $transactionType
        );

        // If frontend sent a different value, use server calculation for security
        if (isset($validated['transaction_cost']) && $validated['transaction_cost'] != $transactionCost) {
            \Log::warning('Transaction cost mismatch', [
                'frontend' => $validated['transaction_cost'],
                'backend' => $transactionCost,
                'amount' => $validated['amount'],
                'account_type' => $account->type,
                'transaction_type' => $transactionType
            ]);
        }

        $totalAmount = $validated['amount'] + $transactionCost;

        // Check if it's an expense and if account has sufficient balance
        if ($category->type === 'expense' && $account->current_balance < $totalAmount) {
            return redirect()->back()
                ->withInput()
                ->with('error', "Insufficient balance in {$account->name}. Current balance: "
                    . number_format($account->current_balance, 0, '.', ',')
                    . ", Required: " . number_format($totalAmount, 0, '.', ',')
                    . " (Amount: " . number_format($validated['amount'], 0, '.', ',')
                    . " + Cost: " . number_format($transactionCost, 0, '.', ',') . ")");
        }

        // Store old balance before transaction
        $oldBalance = $account->current_balance;

        // Auto-set payment method based on account type
        $paymentMethod = match($account->type) {
            'cash' => 'Cash',
            'mpesa' => 'Mpesa',
            'airtel_money' => 'Airtel Money',
            'bank' => 'Bank Transfer',
            default => 'Mpesa'
        };

        DB::beginTransaction();

        try {
            // Create main transaction
            $transaction = Transaction::create([
                'user_id'        => Auth::id(),
                'date'           => $validated['date'],
                'description'    => $validated['description'],
                'amount'         => $validated['amount'],
                'category_id'    => $validated['category_id'],
                'account_id'     => $validated['account_id'],
                'payment_method' => $paymentMethod,
                'is_transaction_fee' => false
            ]);

            // If there's a transaction cost, create a separate transaction for it
            if ($transactionCost > 0) {
                \Log::info('Creating transaction fee', [
                    'amount' => $transactionCost,
                    'account_type' => $account->type,
                    'transaction_type' => $transactionType,
                    'transaction_id' => $transaction->id
                ]);

                // Find or create "Transaction Fees" category
                $feesCategory = Category::withoutGlobalScope('ownedByUser')->firstOrCreate(
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

                // Format transaction type name for description
                $typeLabel = match($transactionType) {
                    'send_money' => 'Send Money',
                    'paybill' => 'PayBill',
                    'buy_goods' => 'Buy Goods/Till',
                    'pochi_la_biashara' => 'Pochi La Biashara',
                    default => 'Send Money'
                };

                $feeTransaction = Transaction::withoutGlobalScope('ownedByUser')->create([
                    'user_id'            => Auth::id(),
                    'date'               => $validated['date'],
                    'description'        => $paymentMethod . ' fee (' . $typeLabel . '): ' . $validated['description'],
                    'amount'             => $transactionCost,
                    'category_id'        => $feesCategory->id,
                    'account_id'         => $validated['account_id'],
                    'payment_method'     => $paymentMethod,
                    'is_transaction_fee' => true,
                    'fee_for_transaction_id' => $transaction->id
                ]);

                \Log::info('Fee transaction created', [
                    'fee_transaction_id' => $feeTransaction->id,
                    'fee_amount' => $feeTransaction->amount
                ]);

                // Link the fee to the main transaction
                $transaction->update([
                    'related_fee_transaction_id' => $feeTransaction->id
                ]);

                \Log::info('Main transaction updated with fee link', [
                    'transaction_id' => $transaction->id,
                    'related_fee_id' => $transaction->related_fee_transaction_id
                ]);
            }

            // Update account balance
            if ($transaction->account) {
                $transaction->account->updateBalance();
                // Refresh to get updated balance
                $transaction->account->refresh();
            }

            // Auto-create or update budget entry
            $this->updateBudgetFromTransaction($transaction);

            DB::commit();

            // Pass balance information to the view
            return redirect()->route('transactions.index')
                ->with('success', 'Transaction recorded successfully' . ($transactionCost > 0 ? ' (including KSh ' . number_format($transactionCost, 2) . ' transaction fee)' : ''))
                ->with('show_balance_modal', true)
                ->with('account_name', $account->name)
                ->with('old_balance', number_format($oldBalance, 2))
                ->with('new_balance', number_format($transaction->account->current_balance, 2))
                ->with('transaction_amount', number_format($totalAmount, 2))
                ->with('transaction_type', $category->type);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create transaction: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Automatically create or update budget based on transaction
     */
    private function updateBudgetFromTransaction(Transaction $transaction)
    {
        // Use period_date if available, otherwise use transaction date
        $date = $transaction->period_date ?? $transaction->date;
        $year = Carbon::parse($date)->year;
        $month = Carbon::parse($date)->month;

        // Find or create budget entry
        $budget = Budget::firstOrCreate(
            [
                'category_id' => $transaction->category_id,
                'year' => $year,
                'month' => $month,
                'user_id' => $transaction->user_id
            ],
            [
                'amount' => 0
            ]
        );

        // Update budget amount by adding this transaction
        // This creates a running total budget based on actual spending
        $budget->amount += $transaction->amount;
        $budget->save();
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
}
