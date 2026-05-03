<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transfer;
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

        $txSort      = in_array($request->input('tx_sort'), $allowedSorts) ? $request->input('tx_sort') : 'date';
        $txDirection = $request->input('tx_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $topSort      = in_array($request->input('top_sort'), $allowedSorts) ? $request->input('top_sort') : 'date';
        $topDirection = $request->input('top_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $transactions = $account->transactions()
            ->with(['category.parent', 'feeTransaction'])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.type', 'expense')
            ->whereNull('transactions.deleted_at')
            ->select('transactions.*')
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('transactions.description', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(transactions.amount AS CHAR) LIKE ?', ["%{$search}%"]);
            }))
            ->orderBy("transactions.{$txSort}", $txDirection)
            ->when($txSort === 'date', fn($q) => $q->orderBy('transactions.id', $txDirection))
            ->paginate(20, ['*'], 'tx_page')
            ->appends($request->query());

        $topUps = $account->transactions()
            ->with(['category'])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereIn('categories.type', ['income', 'liability'])
            ->whereNull('transactions.deleted_at')
            ->select('transactions.*')
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('transactions.description', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(transactions.amount AS CHAR) LIKE ?', ["%{$search}%"]);
            }))
            ->orderBy("transactions.{$topSort}", $topDirection)
            ->when($topSort === 'date', fn($q) => $q->orderBy('transactions.id', $topDirection))
            ->paginate(20, ['*'], 'top_page')
            ->appends($request->query());

        $stats = $account->transactions()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(CASE WHEN categories.type = "income"  THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN categories.type = "expense" THEN amount ELSE 0 END) as total_expenses,
                SUM(CASE WHEN MONTH(date) = ? AND YEAR(date) = ? THEN amount ELSE 0 END) as this_month_total
            ', [now()->month, now()->year])
            ->first();

        return view('accounts.show', [
            'account'           => $account,
            'transactions'      => $transactions,
            'topUps'            => $topUps,
            'totalTransactions' => $stats->total_transactions ?? 0,
            'totalIncome'       => $stats->total_income       ?? 0,
            'totalExpenses'     => $stats->total_expenses     ?? 0,
            'thisMonthTotal'    => $stats->this_month_total   ?? 0,
            'search'            => $search,
            'activeTab'         => $activeTab,
            'txSort'            => $txSort,
            'txDirection'       => $txDirection,
            'topSort'           => $topSort,
            'topDirection'      => $topDirection,
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

        if (in_array($account->type, ['savings', 'wallet'])) {
            return redirect()->route('accounts.index')
                ->with('error', 'This account can only receive money via transfers.');
        }

        [$categories, $showSaccoDividends] = $this->topUpService->getCategories($account->type);

        return view('accounts.topup', compact('account', 'categories', 'showSaccoDividends'));
    }

    // ── top-up store ──────────────────────────────────────────────────────────

    public function topUp(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        if (in_array($account->type, ['savings', 'wallet'])) {
            return redirect()->route('accounts.index')
                ->with('error', 'This account can only receive money via transfers.');
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

        // Loan receipt: redirect to loan form with prefill
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
}
