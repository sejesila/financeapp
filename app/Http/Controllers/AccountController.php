<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\InterestService;
use App\Services\TopUpService;
use App\Services\TransferFeeCalculator;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function __construct(
        private readonly TransferService       $transferService,
        private readonly TransferFeeCalculator $feeCalculator,
        private readonly TopUpService          $topUpService,
        private readonly InterestService       $interestService,
    ) {}

    // ── index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereIn('type', ['cash', 'mpesa', 'airtel_money', 'bank'])
            ->get();

        $walletAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('type', 'wallet')
            ->get();

        $savingsAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('type', 'savings')
            ->get();

        $totalBalance = number_format(
            $accounts->sum('current_balance') + $walletAccounts->sum('current_balance'),
            2, '.', ''
        );
        $totalSavings = number_format($savingsAccounts->sum('current_balance'), 2, '.', '');

        $transferSearch = $request->input('transfer_search');

        $recentTransfers = Transfer::with(['fromAccount', 'toAccount'])
            ->whereHas('fromAccount', fn($q) => $q->where('user_id', Auth::id()))
            ->when($transferSearch, function ($query) use ($transferSearch) {
                $query->where(function ($q) use ($transferSearch) {
                    $q->whereHas('fromAccount', fn($s) => $s->where('name', 'like', "%{$transferSearch}%"))
                        ->orWhereHas('toAccount', fn($s) => $s->where('name', 'like', "%{$transferSearch}%"))
                        ->orWhere('description', 'like', "%{$transferSearch}%")
                        ->orWhereRaw('CAST(amount AS CHAR) LIKE ?', ["%{$transferSearch}%"]);
                });
            })
            ->latest()
            ->paginate(15, ['*'], 'transfer_page')
            ->appends($request->query());

        $allAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('accounts.index', compact(
            'accounts', 'walletAccounts', 'savingsAccounts',
            'totalBalance', 'totalSavings',
            'recentTransfers', 'allAccounts', 'transferSearch',
        ));
    }

    // ── create / store ────────────────────────────────────────────────────────

    public function create()
    {
        return view('accounts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'type'            => 'required|string',
            'initial_balance' => 'required|numeric|min:0',
            'notes'           => 'nullable|string',
            'logo'            => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
        ]);

        $logoPath = $request->hasFile('logo')
            ? $request->file('logo')->store('account-logos', 'public')
            : null;

        Account::create([
            'user_id'         => Auth::id(),
            'name'            => $request->name,
            'type'            => $request->type,
            'initial_balance' => $request->initial_balance,
            'current_balance' => $request->initial_balance,
            'notes'           => $request->notes,
            'logo_path'       => $logoPath,
        ]);

        return redirect()->route('accounts.index')->with('success', 'Account created successfully!');
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function show(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $search    = $request->input('search');
        $activeTab = $request->input('tab', 'transactions');

        $allowedSorts = ['date', 'description', 'amount'];

        $txSort       = in_array($request->input('tx_sort'), $allowedSorts) ? $request->input('tx_sort') : 'date';
        $txDirection  = $request->input('tx_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $topSort      = in_array($request->input('top_sort'), $allowedSorts) ? $request->input('top_sort') : 'date';
        $topDirection = $request->input('top_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $transferSort      = in_array($request->input('tr_sort'), $allowedSorts) ? $request->input('tr_sort') : 'date';
        $transferDirection = $request->input('tr_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        // ── Transactions query ────────────────────────────────────────────────
        $transactions = $account->transactions()
            ->with(['category.parent', 'feeTransaction'])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.type', 'expense')
            ->whereNull('transactions.deleted_at')
            ->select('transactions.*')
            ->when($search && $activeTab === 'transactions', fn($q) => $q->where(function ($q) use ($search) {
                $q->where('transactions.description', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(transactions.amount AS CHAR) LIKE ?', ["%{$search}%"]);
            }))
            ->orderBy("transactions.{$txSort}", $txDirection)
            ->when($txSort === 'date', fn($q) => $q->orderBy('transactions.id', $txDirection))
            ->paginate(20, ['*'], 'tx_page')
            ->appends($request->query());

        // ── Top Ups query ─────────────────────────────────────────────────────
        $topUps = $account->transactions()
            ->with(['category'])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereIn('categories.type', ['income', 'liability'])
            ->whereNull('transactions.deleted_at')
            ->select('transactions.*')
            ->when($search && $activeTab === 'topups', fn($q) => $q->where(function ($q) use ($search) {
                $q->where('transactions.description', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(transactions.amount AS CHAR) LIKE ?', ["%{$search}%"]);
            }))
            ->orderBy("transactions.{$topSort}", $topDirection)
            ->when($topSort === 'date', fn($q) => $q->orderBy('transactions.id', $topDirection))
            ->paginate(20, ['*'], 'top_page')
            ->appends($request->query());

        // ── Transfers query ───────────────────────────────────────────────────
        $transfers = Transfer::with(['fromAccount', 'toAccount'])
            ->where(function ($q) use ($account) {
                $q->where('from_account_id', $account->id)
                    ->orWhere('to_account_id', $account->id);
            })
            ->when($search && $activeTab === 'transfers', fn($q) => $q->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(amount AS CHAR) LIKE ?', ["%{$search}%"]);
            }))
            ->orderBy($transferSort, $transferDirection)
            ->when($transferSort === 'date', fn($q) => $q->orderBy('id', $transferDirection))
            ->paginate(20, ['*'], 'tr_page')
            ->appends($request->query());

        // ── Base stats ────────────────────────────────────────────────────────
        $stats = $account->transactions()
            ->whereNull('transactions.deleted_at')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(CASE WHEN categories.type = "income"  THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN categories.type = "expense" THEN amount ELSE 0 END) as total_expenses,
                SUM(CASE WHEN MONTH(date) = ? AND YEAR(date) = ? THEN amount ELSE 0 END) as this_month_total
            ', [now()->month, now()->year])
            ->first();

        // ── Savings-specific stats ────────────────────────────────────────────
        $savingsStats    = null;
        $savingsNetAllTime = 0;
        $interestByPeriod  = collect();
        $expensesByPeriod  = collect();
        $availableYears    = collect();
        $selectedYear      = now()->year;
        $selectedPeriod    = 'monthly';

        if ($account->type === 'savings') {
            $selectedYear   = (int) $request->input('year', now()->year);
            $selectedPeriod = $request->input('period', 'monthly'); // weekly | monthly | yearly

            // All-time totals
            $savingsStats = $account->transactions()
                ->whereNull('transactions.deleted_at')
                ->join('categories', 'transactions.category_id', '=', 'categories.id')
                ->selectRaw('
                    SUM(CASE WHEN categories.name = "Interest" THEN transactions.amount ELSE 0 END) as total_interest,
                    SUM(CASE WHEN categories.type = "expense"  THEN transactions.amount ELSE 0 END) as total_expenses
                ')
                ->first();

            $savingsNetAllTime = ($savingsStats->total_interest ?? 0) - ($savingsStats->total_expenses ?? 0);

            // Available years for year selector
            $availableYears = $account->transactions()
                ->whereNull('deleted_at')
                ->selectRaw('YEAR(date) as year')
                ->groupBy('year')
                ->orderByDesc('year')
                ->pluck('year');

            // Period breakdown
            $periodBase = $account->transactions()
                ->whereNull('deleted_at')
                ->join('categories', 'transactions.category_id', '=', 'categories.id');

            if ($selectedPeriod === 'yearly') {
                // ── Yearly ────────────────────────────────────────────────────
                // No year filter — show all years.
                $interestByPeriod = (clone $periodBase)
                    ->where('categories.name', 'Interest')
                    ->selectRaw('
                        YEAR(transactions.date)              as period_label,
                        SUM(transactions.amount)             as total
                    ')
                    ->groupByRaw('YEAR(transactions.date)')
                    ->orderByRaw('YEAR(transactions.date)')
                    ->get();

                $expensesByPeriod = (clone $periodBase)
                    ->where('categories.type', 'expense')
                    ->selectRaw('
                        YEAR(transactions.date) as period_label,
                        SUM(transactions.amount) as total
                    ')
                    ->groupByRaw('YEAR(transactions.date)')
                    ->orderByRaw('YEAR(transactions.date)')
                    ->get();

            } elseif ($selectedPeriod === 'weekly') {
                // ── Weekly ────────────────────────────────────────────────────
                $interestByPeriod = (clone $periodBase)
                    ->where('categories.name', 'Interest')
                    ->whereYear('transactions.date', $selectedYear)
                    ->selectRaw('
                        WEEK(transactions.date, 1)           as week_num,
                        SUM(transactions.amount)             as total
                    ')
                    ->groupByRaw('WEEK(transactions.date, 1)')
                    ->orderByRaw('WEEK(transactions.date, 1)')
                    ->get()
                    ->map(fn($r) => tap($r, fn($r) => $r->period_label = 'Week ' . $r->week_num));

                $expensesByPeriod = (clone $periodBase)
                    ->where('categories.type', 'expense')
                    ->whereYear('transactions.date', $selectedYear)
                    ->selectRaw('
                        WEEK(transactions.date, 1) as week_num,
                        SUM(transactions.amount)   as total
                    ')
                    ->groupByRaw('WEEK(transactions.date, 1)')
                    ->orderByRaw('WEEK(transactions.date, 1)')
                    ->get()
                    ->map(fn($r) => tap($r, fn($r) => $r->period_label = 'Week ' . $r->week_num));

            } else {
                // ── Monthly (default) ─────────────────────────────────────────
                $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

                $interestByPeriod = (clone $periodBase)
                    ->where('categories.name', 'Interest')
                    ->whereYear('transactions.date', $selectedYear)
                    ->selectRaw('
                        MONTH(transactions.date)             as month_num,
                        SUM(transactions.amount)             as total
                    ')
                    ->groupByRaw('MONTH(transactions.date)')
                    ->orderByRaw('MONTH(transactions.date)')
                    ->get()
                    ->map(fn($r) => tap($r, fn($r) => $r->period_label = $monthNames[$r->month_num - 1]));

                $expensesByPeriod = (clone $periodBase)
                    ->where('categories.type', 'expense')
                    ->whereYear('transactions.date', $selectedYear)
                    ->selectRaw('
                        MONTH(transactions.date) as month_num,
                        SUM(transactions.amount) as total
                    ')
                    ->groupByRaw('MONTH(transactions.date)')
                    ->orderByRaw('MONTH(transactions.date)')
                    ->get()
                    ->map(fn($r) => tap($r, fn($r) => $r->period_label = $monthNames[$r->month_num - 1]));
            }
        }

        return view('accounts.show', [
            'account'      => $account,
            'transactions' => $transactions,
            'topUps'       => $topUps,
            'transfers'    => $transfers,
            // base stats
            'totalTransactions' => $stats->total_transactions ?? 0,
            'totalIncome'       => $stats->total_income ?? 0,
            'totalExpenses'     => $stats->total_expenses ?? 0,
            'thisMonthTotal'    => $stats->this_month_total ?? 0,
            // ui state
            'search'            => $search,
            'activeTab'         => $activeTab,
            'txSort'            => $txSort,
            'txDirection'       => $txDirection,
            'topSort'           => $topSort,
            'topDirection'      => $topDirection,
            'transferSort'      => $transferSort,
            'transferDirection' => $transferDirection,
            // savings-only
            'savingsStats'      => $savingsStats,
            'savingsNetAllTime' => $savingsNetAllTime,
            'interestByPeriod'  => $interestByPeriod,
            'expensesByPeriod'  => $expensesByPeriod,
            'availableYears'    => $availableYears,
            'selectedYear'      => $selectedYear,
            'selectedPeriod'    => $selectedPeriod,
        ]);
    }

    // ── edit / update ─────────────────────────────────────────────────────────

    public function edit(Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        return view('accounts.edit', compact('account'));
    }

    public function update(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'name'        => 'required|string|max:255',
            'notes'       => 'nullable|string',
            'logo'        => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
            'remove_logo' => 'nullable',
        ]);

        $data = $request->only(['name', 'notes']);

        if ($request->hasFile('logo')) {
            if ($account->logo_path) {
                Storage::disk('public')->delete($account->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('account-logos', 'public');
        } elseif ($request->boolean('remove_logo') && $account->logo_path) {
            Storage::disk('public')->delete($account->logo_path);
            $data['logo_path'] = null;
        }

        $account->update($data);

        return redirect()->route('accounts.show', $account)->with('success', 'Account updated successfully!');
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function destroy(Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        if ($account->transactions()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete account with existing transactions.');
        }

        if ($account->logo_path) {
            Storage::disk('public')->delete($account->logo_path);
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('success', 'Account deleted successfully!');
    }

    // ── transfer form ─────────────────────────────────────────────────────────

    public function transferForm()
    {
        if (auth()->user()->accounts()->count() < 2) {
            return redirect()->route('accounts.index')
                ->with('error', 'You need at least two accounts to transfer money.');
        }

        $sourceAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('current_balance', '>=', 1)
            ->get();

        $destinationAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->get();

        return view('accounts.transfer', compact('sourceAccounts', 'destinationAccounts'));
    }

    // ── transfer post ─────────────────────────────────────────────────────────

    public function transfer(Request $request)
    {
        if (auth()->user()->accounts()->count() < 2) {
            return redirect()->route('accounts.index')
                ->with('error', 'You need at least two accounts to transfer money.');
        }

        $request->validate([
            'from_account_id' => 'required|exists:accounts,id|different:to_account_id',
            'to_account_id'   => 'required|exists:accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'date'            => 'required|date',
            'description'     => 'nullable|string',
            'transaction_fee' => 'nullable|numeric|min:0',
        ]);

        $from = Account::withoutGlobalScopes()->findOrFail($request->from_account_id);
        $to   = Account::withoutGlobalScopes()->findOrFail($request->to_account_id);

        if ($from->user_id !== Auth::id() || $to->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            $fee = $this->transferService->execute(
                $from,
                $to,
                (float) $request->amount,
                $request->date,
                $request->description,
                $request->filled('transaction_fee') ? (float) $request->transaction_fee : null,
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        $this->clearAccountCache($from->id);
        $this->clearAccountCache($to->id);

        return redirect()->route('accounts.index')
            ->with('success', 'Transfer completed successfully!' . $fee->successSuffix());
    }

    // ── top-up form ───────────────────────────────────────────────────────────

    public function topUpForm(Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $isEtica = $account->type === 'savings' && strtolower($account->name) === 'etica';
        if ($account->type === 'wallet' || ($account->type === 'savings' && !$isEtica)) {
            return redirect()->route('accounts.index')
                ->with('error', 'This account can only receive money via transfers.');
        }

        [$categories, $showSaccoDividends] = $this->topUpService->getCategories($account->type);
        $isSavings = $account->type === 'savings';

        return view('accounts.topup', compact('account', 'categories', 'showSaccoDividends', 'isSavings'));
    }

    // ── top-up store ──────────────────────────────────────────────────────────

    public function topUp(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        $isEtica = $account->type === 'savings' && strtolower($account->name) === 'etica';
        if ($account->type === 'wallet' || ($account->type === 'savings' && !$isEtica)) {
            return redirect()->route('accounts.index')
                ->with('error', 'This account can only receive money via transfers.');
        }

        $isClientFund = $request->boolean('is_client_fund');

        if ($isClientFund) {
            if ($account->type !== 'savings') {
                return redirect()->route('accounts.index')
                    ->with('error', 'Client fund recording is only allowed for savings accounts.');
            }

            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'date'   => 'required|date',
            ]);

            return redirect()->route('client-funds.create', [
                'account_id' => $account->id,
                'amount'     => $request->amount,
                'date'       => $request->date,
            ])->with('info', 'Complete the client fund details below.');
        }

        $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'date'        => 'required|date',
            'period_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $category = Category::where('id', $request->category_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$category) {
            return redirect()->back()->with('error', 'Please select a valid category.');
        }

        $error = $this->topUpService->validateCategory($account, $category);
        if ($error) {
            return redirect()->back()->with('error', $error);
        }

        if ($category->type === 'liability' && $category->parent?->name === 'Loans') {
            return redirect()->route('loans.create', [
                'account_id' => $account->id,
                'amount'     => $request->amount,
                'source'     => $category->name,
                'date'       => $request->date,
                'notes'      => $request->description,
            ])->with('info', 'Loans require additional details. Please complete the loan form.');
        }

        $account->transactions()->create([
            'user_id'        => Auth::id(),
            'amount'         => $request->amount,
            'date'           => $request->date,
            'period_date'    => $request->period_date ?? $request->date,
            'description'    => $request->description ?: (
            $account->type === 'savings'
                ? "Deposit to {$account->name}"
                : "Top-up to {$account->name}"
            ),
            'category_id'    => $category->id,
            'payment_method' => $category->name,
        ]);

        $account->updateBalance();
        $this->clearAccountCache($account->id);

        $verb = $account->type === 'savings' ? 'deposited to' : 'topped up';

        return redirect()->route('accounts.show', $account)
            ->with('success', "Account {$verb} successfully with KES " . number_format($request->amount, 0, '.', ','));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function clearAccountCache(int $accountId): void
    {
        Cache::forget("account.{$accountId}.stats");
    }

    // ── reverse top-up form ───────────────────────────────────────────────────

    public function reverseTopUpForm(Account $account, Transaction $transaction)
    {
        if ($account->user_id !== Auth::id()) abort(403);

        if ($transaction->created_at->diffInMinutes(now()) > 30) {
            return redirect()->route('accounts.show', ['account' => $account, 'tab' => 'topups'])
                ->with('error', 'Top-ups can only be reversed within 30 minutes of being recorded.');
        }

        if (!in_array($transaction->category->type, ['income', 'liability'])) {
            return redirect()->route('accounts.show', $account)
                ->with('error', 'Only top-up transactions can be reversed.');
        }

        return view('accounts.reverse-topup', compact('account', 'transaction'));
    }

    // ── reverse top-up post ───────────────────────────────────────────────────

    public function reverseTopUp(Request $request, Account $account, Transaction $transaction)
    {
        if ($account->user_id !== Auth::id()) abort(403);

        if ($transaction->created_at->diffInMinutes(now()) > 30) {
            return redirect()->route('accounts.show', ['account' => $account, 'tab' => 'topups'])
                ->with('error', 'Top-ups can only be reversed within 30 minutes of being recorded.');
        }

        if (!in_array($transaction->category->type, ['income', 'liability'])) {
            return redirect()->route('accounts.show', $account)
                ->with('error', 'Only top-up transactions can be reversed.');
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $transaction->delete();

        $account->updateBalance();
        $this->clearAccountCache($account->id);

        return redirect()->route('accounts.show', ['account' => $account, 'tab' => 'topups'])
            ->with('success', 'Top-up of KES ' . number_format($transaction->amount, 0, '.', ',') . ' has been reversed.');
    }

    // ── record interest form ──────────────────────────────────────────────────

    /**
     * Show the interest recording form for a savings account.
     * Passes skipped-day information so the view can prompt the user.
     */
    public function recordInterestForm(Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        if ($account->type !== 'savings') {
            return redirect()->route('accounts.show', $account)
                ->with('error', 'Interest can only be recorded for savings accounts.');
        }

        if (!$this->interestService->canRecordTodayKey($account)) {
            return redirect()->route('accounts.show', $account)
                ->with('info', 'Interest has already been recorded for today. Come back tomorrow!');
        }

        $skippedDaysCount = $this->interestService->getSkippedDaysCount($account);
        $skippedDateRange = $this->interestService->getSkippedDateRange($account);
        $lastInterestDate = $this->interestService->getLastInterestDate($account);

        return view('accounts.record-interest', compact(
            'account',
            'skippedDaysCount',
            'skippedDateRange',
            'lastInterestDate',
        ));
    }

    // ── record interest post ──────────────────────────────────────────────────

    /**
     * Store an interest transaction for a savings account.
     *
     * Validates the amount, builds the description (including multi-day period
     * label when days were skipped), persists the transaction, then computes
     * and stores the daily rate.
     */
    public function recordInterest(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) abort(403);

        if ($account->type !== 'savings') {
            return redirect()->route('accounts.show', $account)
                ->with('error', 'Interest can only be recorded for savings accounts.');
        }

        if (!$this->interestService->canRecordTodayKey($account)) {
            return redirect()->route('accounts.show', $account)
                ->with('error', 'Interest has already been recorded for today.');
        }

        $request->validate([
            'amount'              => 'required|numeric|min:0.01',
            'date'                => 'required|date',
            'description'         => 'nullable|string|max:255',
            'acknowledge_skipped' => 'nullable|boolean',
        ]);

        // Service-level validation (rate ceiling, positive balance, etc.)
        $validationErrors = $this->interestService->validateInterestAmount(
            (float) $request->amount
        );

        if (!empty($validationErrors)) {
            return redirect()->back()->withInput()
                ->withErrors(['amount' => $validationErrors]);
        }

        $skippedDaysCount = $this->interestService->getSkippedDaysCount($account);

        if ($skippedDaysCount !== null && $skippedDaysCount > 0 && !$request->boolean('acknowledge_skipped')) {
            return redirect()->back()->withInput()
                ->with('error', 'Please acknowledge that you understand the interest period being recorded.');
        }

        $interestCategory = Category::firstOrCreate(
            ['user_id' => Auth::id(), 'name' => 'Interest', 'parent_id' => null],
            ['type' => 'income', 'icon' => '📈', 'is_active' => true]
        );

        // Build description — user-supplied takes priority, then auto-generate.
        if ($request->filled('description')) {
            $description = $request->description;
        } elseif ($skippedDaysCount !== null && $skippedDaysCount > 0) {
            $periodDays  = $skippedDaysCount + 1;
            $description = "Interest earned ({$periodDays}-day period)";
        } else {
            $description = 'Interest earned';
        }

        $transaction = $account->transactions()->create([
            'user_id'        => Auth::id(),
            'amount'         => (float) $request->amount,
            'date'           => $request->date,
            'description'    => $description,
            'category_id'    => $interestCategory->id,
            'payment_method' => 'Interest',
        ]);

        $account->updateBalance();
        Cache::forget("account.{$account->id}.stats");

        $message = 'Interest of KES ' . number_format($request->amount, 0, '.', ',') . ' recorded successfully!';
        if ($skippedDaysCount !== null && $skippedDaysCount > 0) {
            $message .= ' (for ' . ($skippedDaysCount + 1) . ' days)';
        }

        return redirect()->route('accounts.show', $account)->with('success', $message);
    }
}
