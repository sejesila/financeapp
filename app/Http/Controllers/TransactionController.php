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
        $minYear = Transaction::min(DB::raw('YEAR(date)'));
        $minYear = $minYear ?? date('Y');
        $maxYear = date('Y');

        $query = Transaction::with(['category', 'account']);

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

        // Calculate totals
        $totalToday = Transaction::whereDate('date', today())->sum('amount');
        $totalYesterday = Transaction::whereDate('date', today()->subDay())->sum('amount');
        $totalThisWeek = Transaction::whereBetween('date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->sum('amount');
        $totalLastWeek = Transaction::whereBetween('date', [
            now()->subWeek()->startOfWeek(),
            now()->subWeek()->endOfWeek()
        ])->sum('amount');
        $totalThisMonth = Transaction::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');
        $totalLastMonth = Transaction::whereMonth('date', now()->subMonth()->month)
            ->whereYear('date', now()->subMonth()->year)
            ->sum('amount');
        $totalAll = Transaction::sum('amount');

        // Get categories and accounts for filters
        $categories = Category::orderBy('type')->orderBy('name')->get();
        $accounts = \App\Models\Account::where('is_active', true)->orderBy('name')->get();

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
        $categories = Category::all();
        $accounts = \App\Models\Account::where('is_active', true)->get();
        return view('transactions.create', compact('categories', 'accounts'));
    }

    /**
     * Store a newly created resource in storage.
     */


    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'account_id' => 'required|exists:accounts,id'
        ]);

        // Get account and category
        $account = \App\Models\Account::find($request->account_id);
        $category = \App\Models\Category::find($request->category_id);

        // Check if it's an expense and if account has sufficient balance
        if ($category->type === 'expense' && $account->current_balance < $request->amount) {
            return redirect()->back()
                ->withInput()
                ->with('error', "Insufficient balance in {$account->name}. Current balance: "
                    . number_format($account->current_balance, 0, '.', ',')
                    . ", Required: " . number_format($request->amount, 0, '.', ','));
        }

        // Auto-set payment method based on account type
        $paymentMethod = match($account->type) {
            'cash' => 'Cash',
            'mpesa' => 'Mpesa',
            'airtel_money' => 'Airtel Money',
            'bank' => 'Bank Transfer',
            default => 'Mpesa'
        };

        // ✅ Attach user_id when creating the transaction
        $transaction = Transaction::create([
            'user_id'       => Auth::id(),   // 👈 required
            'date'          => $request->date,
            'description'   => $request->description,
            'amount'        => $request->amount,
            'category_id'   => $request->category_id,
            'account_id'    => $request->account_id,
            'payment_method'=> $paymentMethod
        ]);


        // Update account balance
        if ($transaction->account) {
            $transaction->account->updateBalance();
        }

        return redirect()->route('transactions.index')->with('success', 'Transaction recorded successfully');
    }


    /**
     * Display the specified resource (read-only view).
     */
    public function show(Transaction $transaction)
    {
        $transaction->load(['account', 'category']);
        return view('transactions.show', compact('transaction'));
    }
}
