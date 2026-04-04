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

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter        = $request->get('filter', 'all');
        $startDate     = $request->get('start_date');
        $endDate       = $request->get('end_date');
        $search        = $request->get('search');
        $categoryId    = $request->get('category_id');
        $accountId     = $request->get('account_id');
        $showFees      = $request->boolean('show_fees');

        // Sorting
        $allowedSorts  = ['date', 'description', 'amount', 'account', 'category'];
        $sortColumn    = in_array($request->get('sort'), $allowedSorts)
            ? $request->get('sort')
            : 'date';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        // Bug 13 fix: use a properly scoped, index-friendly query
        $minYear = Transaction::selectRaw('YEAR(MIN(date)) as min_year')
            ->value('min_year') ?? date('Y');
        $maxYear = date('Y');

        // Bug 11 fix: drop the redundant user_id where — global scope handles it.
        // Bug 12 fix: eager-load splits and their fees.
        $query = Transaction::with([
            'category',
            'account',
            'feeTransaction',
            'splits.account',
            'splits.feeTransaction',    // Bug 12 fix
        ]);

        if (!$showFees) {
            $query->where('is_transaction_fee', false);
        }

        if ($search) {
            $query->where('description', 'like', '%' . $search . '%');
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Bug 15 fix: match split transactions whose splits include this account
        if ($accountId) {
            $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)
                    ->orWhereHas('splits', fn($s) => $s->where('account_id', $accountId));
            });
        }

        $query = $this->applyDateFilter($query, $filter, $startDate, $endDate);

        // Bug 16 fix: track which joins are already applied
        // Bug 11 fix: no table-prefix needed now that there are no ambiguous joins
        if ($sortColumn === 'account') {
            $query->leftJoin('accounts', 'transactions.account_id', '=', 'accounts.id')
                ->orderBy('accounts.name', $sortDirection)
                ->select('transactions.*');
        } elseif ($sortColumn === 'category') {
            $query->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
                ->orderBy('categories.name', $sortDirection)
                ->select('transactions.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
            if ($sortColumn === 'date') {
                // Secondary sort by id keeps pagination stable when dates collide
                $query->orderBy('id', $sortDirection);
            }
        }

        $transactions = $query->paginate(25)->withQueryString();
        $totals       = $this->calculateTransactionTotals();
        $feeTotals    = $this->calculateFeeTotals();

        $categories = Category::where('user_id', Auth::id())
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $accounts = $this->getActiveAccounts();
        $summary = $this->calculateSummary();
        $periodStats = $this->calculatePeriodStats();

        return view('transactions.index', array_merge(
            compact(
                'transactions',
                'summary',
                'periodStats',
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
                'showFees',
                'sortColumn',
                'sortDirection'
            ),
            $totals,
            $feeTotals
        ));
    }
    private function calculateSummary(): array
    {
        $excludedCategories = [
            'Loan Disbursement',
            'Loan Receipt',
            'Balance Adjustment',
            'Client Funds',
        ];

        $baseQuery = DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.user_id', Auth::id())
            ->whereNull('transactions.deleted_at')
            ->whereNotIn('categories.name', $excludedCategories)
            ->whereIn('categories.type', ['income', 'expense'])
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('transactions.payment_method', '!=', 'Client Fund')
                        ->where('transactions.payment_method', '!=', 'Client Commission')
                        ->orWhereNull('transactions.payment_method');
                })
                    ->orWhere(function ($q2) {
                        $q2->where('transactions.payment_method', 'Client Commission')
                            ->where('categories.type', 'income');
                    });
            });

        $rows = (clone $baseQuery)
            ->selectRaw("
            transactions.mobile_money_type,
            categories.type as category_type,
            SUM(transactions.amount) as total
        ")
            ->groupBy('transactions.mobile_money_type', 'categories.type')
            ->get();

        // Build unified totals
        $merged = [];

        foreach ($rows as $row) {
            $type = $row->mobile_money_type ?? 'other';
            $dir  = $row->category_type === 'income' ? 'in' : 'out';

            if (!isset($merged[$type])) {
                $merged[$type] = ['in' => 0, 'out' => 0];
            }

            $merged[$type][$dir] += (float) $row->total;
        }

        $typeLabels = [
            'send_money'        => 'Send Money',
            'paybill'           => 'Lipa Na M-Pesa (PayBill)',
            'buy_goods'         => 'Lipa Na M-Pesa (Buy Goods)',
            'pochi_la_biashara' => 'Pochi La Biashara',
            'other'             => 'Others',
        ];

        $summary = [];
        $knownKeys = array_keys($typeLabels);

        foreach ($typeLabels as $key => $label) {
            $summary[$key] = [
                'label'    => $label,
                'paid_in'  => $merged[$key]['in']  ?? 0,
                'paid_out' => $merged[$key]['out'] ?? 0,
            ];
        }

        foreach ($merged as $key => $dirs) {
            if (!in_array($key, $knownKeys)) {
                $summary['other']['paid_in']  += $dirs['in']  ?? 0;
                $summary['other']['paid_out'] += $dirs['out'] ?? 0;
            }
        }

        return array_values($summary);
    }
    private function calculatePeriodStats(): array
    {
        $excludedCategories = [
            'Loan Disbursement',
            'Loan Receipt',
            'Balance Adjustment',
            'Client Funds',
        ];

        $result = DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.user_id', Auth::id())
            ->whereNull('transactions.deleted_at')
            ->whereNotIn('categories.name', $excludedCategories)
            ->whereIn('categories.type', ['income', 'expense'])
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('transactions.payment_method', '!=', 'Client Fund')
                        ->where('transactions.payment_method', '!=', 'Client Commission')
                        ->orWhereNull('transactions.payment_method');
                })
                    ->orWhere(function ($q2) {
                        $q2->where('transactions.payment_method', 'Client Commission')
                            ->where('categories.type', 'income');
                    });
            })
            ->selectRaw("
            SUM(CASE WHEN categories.type = 'income'
                AND MONTH(COALESCE(transactions.period_date, transactions.date)) = ?
                AND YEAR(COALESCE(transactions.period_date, transactions.date)) = ?
                THEN transactions.amount ELSE 0 END) as month_in,
            SUM(CASE WHEN categories.type = 'expense'
                AND MONTH(COALESCE(transactions.period_date, transactions.date)) = ?
                AND YEAR(COALESCE(transactions.period_date, transactions.date)) = ?
                THEN transactions.amount ELSE 0 END) as month_out,

            SUM(CASE WHEN categories.type = 'income'
                AND MONTH(COALESCE(transactions.period_date, transactions.date)) = ?
                AND YEAR(COALESCE(transactions.period_date, transactions.date)) = ?
                THEN transactions.amount ELSE 0 END) as last_month_in,
            SUM(CASE WHEN categories.type = 'expense'
                AND MONTH(COALESCE(transactions.period_date, transactions.date)) = ?
                AND YEAR(COALESCE(transactions.period_date, transactions.date)) = ?
                THEN transactions.amount ELSE 0 END) as last_month_out,

            SUM(CASE WHEN categories.type = 'income'
                AND YEAR(COALESCE(transactions.period_date, transactions.date)) = ?
                THEN transactions.amount ELSE 0 END) as year_in,
            SUM(CASE WHEN categories.type = 'expense'
                AND YEAR(COALESCE(transactions.period_date, transactions.date)) = ?
                THEN transactions.amount ELSE 0 END) as year_out,

            SUM(CASE WHEN categories.type = 'income'
                AND YEAR(COALESCE(transactions.period_date, transactions.date)) = ?
                THEN transactions.amount ELSE 0 END) as last_year_in,
            SUM(CASE WHEN categories.type = 'expense'
                AND YEAR(COALESCE(transactions.period_date, transactions.date)) = ?
                THEN transactions.amount ELSE 0 END) as last_year_out,

            SUM(CASE WHEN categories.type = 'income'
                THEN transactions.amount ELSE 0 END) as all_in,
            SUM(CASE WHEN categories.type = 'expense'
                THEN transactions.amount ELSE 0 END) as all_out
        ", [
                now()->month,             now()->year,
                now()->month,             now()->year,
                now()->subMonth()->month, now()->subMonth()->year,
                now()->subMonth()->month, now()->subMonth()->year,
                now()->year,
                now()->year,
                now()->subYear()->year,
                now()->subYear()->year,
            ])
            ->first();

        $periods = [
            'This Month' => ['in' => $result->month_in,      'out' => $result->month_out],
            'Last Month' => ['in' => $result->last_month_in, 'out' => $result->last_month_out],
            'This Year'  => ['in' => $result->year_in,       'out' => $result->year_out],
            'Last Year'  => ['in' => $result->last_year_in,  'out' => $result->last_year_out],
            'All Time'   => ['in' => $result->all_in,        'out' => $result->all_out],
        ];

        foreach ($periods as &$data) {
            $data['net'] = (float)$data['in'] - (float)$data['out'];
        }
        unset($data);

        return $periods;
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
    // AFTER
    private function calculateTransactionTotals()
    {
        $baseQuery = Transaction::where('user_id', Auth::id())
            ->where('is_transaction_fee', false);

        return [
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
            'totalThisYear' => (clone $baseQuery)
                ->whereYear('date', now()->year)
                ->sum('amount'),
            'totalLastYear' => (clone $baseQuery)
                ->whereYear('date', now()->subYear()->year)
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
            'totalFeesThisMonth' => (clone $feeQuery)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('amount'),
            'totalFeesLastMonth' => (clone $feeQuery)
                ->whereMonth('date', now()->subMonth()->month)
                ->whereYear('date', now()->subMonth()->year)
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

        $transaction->load([
            'account',
            'category',
            'feeTransaction',
            'mainTransaction',
            'splits.account',          // ← add
            'splits.feeTransaction',   // ← add if you add feeTransaction relation to TransactionSplit
        ]);

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

        // Pre-compute split data to avoid Blade/PHP conflicts inside @json()
        $existingSplits = [];
        if ($transaction->is_split) {
            $transaction->load('splits.account');
            $existingSplits = $transaction->splits->map(function ($s) {
                return [
                    'id'                => $s->id,
                    'account_id'        => (string) $s->account_id,
                    'amount'            => $s->amount,
                    'mobile_money_type' => $s->mobile_money_type ?? 'send_money',
                    'showMobileType'    => in_array($s->account->type, ['mpesa', 'airtel_money']),
                    'typeOptions'       => [],
                ];
            })->values()->toArray();
        }

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
                'mpesaAccount',
                'existingSplits'
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
