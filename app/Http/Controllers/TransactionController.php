<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionController extends Controller
{
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

        // Calculate dynamic year/month ranges
        $minYear = Transaction::where('user_id', Auth::id())->min(DB::raw('YEAR(date)'));
        $minYear = $minYear ?? date('Y');
        $maxYear = date('Y');

        $query = Transaction::with(['category', 'account'])
            ->where('user_id', Auth::id()); // Only show user's own transactions

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
                    // No filter, show all
                    break;
            }
        }

        $transactions = $query->latest('date')->latest('id')->paginate(50)->withQueryString();

        // Calculate totals (only for user's transactions)
        $userTransactions = Transaction::where('user_id', Auth::id());

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
        $totalAll = Transaction::where('user_id', Auth::id())->sum('amount');

        // Get categories and accounts for filters (only user's own)
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
            'minYear',
            'maxYear',
            'categories',
            'accounts',
            'search',
            'categoryId',
            'accountId',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Transaction::class);

        $categories = Category::where('user_id', Auth::id())->orderBy('name')->get();
        $accounts = \App\Models\Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('transactions.create', compact('categories', 'accounts'));
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
            'account_id' => 'required|exists:accounts,id'
        ]);

        // Verify account ownership FIRST
        $account = \App\Models\Account::findOrFail($validated['account_id']);
        if ($account->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this account.');
        }

        // Verify category ownership
        $category = Category::findOrFail($validated['category_id']);
        if ($category->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to this category.');
        }

        // Check if it's an expense and if account has sufficient balance
        if ($category->type === 'expense' && $account->current_balance < $validated['amount']) {
            return redirect()->back()
                ->withInput()
                ->with('error', "Insufficient balance in {$account->name}. Current balance: "
                    . number_format($account->current_balance, 0, '.', ',')
                    . ", Required: " . number_format($validated['amount'], 0, '.', ','));
        }

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
            // Create transaction with user_id
            $transaction = Transaction::create([
                'user_id'        => Auth::id(),
                'date'           => $validated['date'],
                'description'    => $validated['description'],
                'amount'         => $validated['amount'],
                'category_id'    => $validated['category_id'],
                'account_id'     => $validated['account_id'],
                'payment_method' => $paymentMethod
            ]);

            // Update account balance
            if ($transaction->account) {
                $transaction->account->updateBalance();
            }

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', 'Transaction recorded successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create transaction: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource (read-only view).
     */
    public function show(Transaction $transaction)
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'category']);
        return view('transactions.show', compact('transaction'));
    }
}
