<?php

namespace App\Http\Controllers;

use App\Filters\TransactionFilter;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\MobileMoneyTypeUsage;
use App\Services\MobileMoneyRates;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Services\TransactionStatsService;
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
        'Income', 'Loans', 'Loan Receipt', 'Excise Duty',
        'Loan Fees Refund', 'Facility Fee Refund', 'Transaction Fees',
        'Balance Adjustment', 'Rolling Funds',
    ];

    public function __construct(
        protected TransactionService      $transactionService,
        protected TransactionStatsService $stats,
    ) {}

    public function index(Request $request)
    {
        $filter     = $request->get('filter', 'all');
        $search     = $request->get('search');
        $categoryId = $request->get('category_id');
        $accountId  = $request->get('account_id');
        $startDate  = $request->get('start_date');
        $endDate    = $request->get('end_date');
        $showFees   = $request->boolean('show_fees');

        $allowedSorts  = ['date', 'description', 'amount', 'account', 'category'];
        $sortColumn    = in_array($request->get('sort'), $allowedSorts) ? $request->get('sort') : 'date';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $minYear = Transaction::selectRaw('YEAR(MIN(date)) as min_year')->value('min_year') ?? date('Y');
        $maxYear = date('Y');

        $query = Transaction::with(['category', 'account', 'feeTransaction']);

        if (!$showFees) {
            $query->where('is_transaction_fee', false);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transactions.description', 'like', '%' . $search . '%')
                    ->orWhereHas('category', fn($c) => $c->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('account', fn($a) => $a->where('name', 'like', '%' . $search . '%'));
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        TransactionFilter::applyDateFilter($query, $filter, $startDate, $endDate);

        match ($sortColumn) {
            'account'  => $query->leftJoin('accounts', 'transactions.account_id', '=', 'accounts.id')
                ->orderBy('accounts.name', $sortDirection)
                ->select('transactions.*'),
            'category' => $query->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
                ->orderBy('categories.name', $sortDirection)
                ->select('transactions.*'),
            default    => tap($query->orderBy($sortColumn, $sortDirection), function ($q) use ($sortColumn, $sortDirection) {
                if ($sortColumn === 'date') {
                    $q->orderBy('id', $sortDirection);
                }
            }),
        };

        $transactions = $query->paginate(25)->withQueryString();
        $categories   = Category::where('user_id', Auth::id())->orderBy('type')->orderBy('name')->get();
        $accounts     = $this->getActiveAccounts();

        return view('transactions.index', array_merge(
            compact(
                'transactions', 'filter', 'minYear', 'maxYear',
                'categories', 'accounts', 'search', 'categoryId', 'accountId',
                'startDate', 'endDate', 'showFees', 'sortColumn', 'sortDirection'
            ),
            $this->stats->totals(),
            $this->stats->feeTotals(),
            ['summary' => $this->stats->summary(), 'periodStats' => $this->stats->periodStats()],
        ));
    }

    public function create(Request $request)
    {
        $data = $this->formData();

        $data['selectedAccount'] = $request->account_id
            ? $data['accounts']->find($request->account_id)
            : null;

        return view('transactions.create', $data);
    }

    public function store(StoreTransactionRequest $request)
    {
        $this->authorize('create', Transaction::class);

        $validated = $request->validated();

        try {
            $account    = Account::findOrFail($validated['account_id']);
            $oldBalance = $account->current_balance;

            $transaction = $this->transactionService->createTransaction($validated);

            Category::find($validated['category_id'])?->increment('usage_count');

            if (isset($validated['mobile_money_type']) && in_array($account->type, ['mpesa', 'airtel_money'])) {
                MobileMoneyTypeUsage::incrementUsage(Auth::id(), $account->type, $validated['mobile_money_type']);
            }

            $account->refresh();

            $totalAmount = $transaction->amount + ($transaction->feeTransaction?->amount ?? 0);

            return redirect()->route('transactions.index')
                ->with('success', $this->buildSuccessMessage($transaction))
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

    public function show(Transaction $transaction)
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'category', 'feeTransaction', 'mainTransaction']);

        return view('transactions.show', compact('transaction'));
    }

    public function edit(Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        if ($transaction->is_transaction_fee) {
            return redirect()->back()->with('error', 'System-generated transaction fees cannot be edited.');
        }

        // Get form data first
        $formData = $this->formData();

        // Override the default types with the transaction's actual type for editing
        // But also keep the original most-used types for other purposes
        $formData['defaultMpesaType'] = MobileMoneyTypeUsage::getMostUsedType(Auth::id(), 'mpesa') ?? 'send_money';
        $formData['defaultAirtelType'] = MobileMoneyTypeUsage::getMostUsedType(Auth::id(), 'airtel_money') ?? 'send_money';

        // Add the transaction's current mobile money type separately
        $formData['transactionMobileMoneyType'] = $transaction->mobile_money_type;

        // Add transaction to the array
        $formData['transaction'] = $transaction;

        return view('transactions.edit', $formData);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        try {
            $validated          = $request->validated();
            $updatedTransaction = $this->transactionService->updateTransaction($transaction, $validated);

            if ($transaction->category_id != $validated['category_id']) {
                Category::find($validated['category_id'])?->increment('usage_count');
            }

            return redirect()->route('transactions.index', $updatedTransaction)
                ->with('success', 'Transaction updated successfully!');

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy(Transaction $transaction)
    {
        $this->authorize('delete', $transaction);

        if ($transaction->is_transaction_fee) {
            return redirect()->back()->with('error', 'System-generated transaction fees cannot be deleted directly. Delete the main transaction instead.');
        }

        DB::beginTransaction();

        try {
            Transaction::find($transaction->related_fee_transaction_id)?->delete();
            $transaction->delete();
            $transaction->account->updateBalance();

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', 'Transaction deleted successfully. Account balance has been updated.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Transaction deletion failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete transaction: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        $transaction = Transaction::onlyTrashed()->where('user_id', Auth::id())->findOrFail($id);

        DB::beginTransaction();

        try {
            $transaction->restore();
            Transaction::onlyTrashed()->find($transaction->related_fee_transaction_id)?->restore();
            $transaction->account->updateBalance();

            DB::commit();

            return redirect()->route('transactions.index')->with('success', 'Transaction restored successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to restore transaction: ' . $e->getMessage());
        }
    }

    public function forceDestroy($id)
    {
        $transaction = Transaction::onlyTrashed()->where('user_id', Auth::id())->findOrFail($id);

        $this->authorize('forceDelete', $transaction);

        DB::beginTransaction();

        try {
            Transaction::onlyTrashed()->find($transaction->related_fee_transaction_id)?->forceDelete();
            $transaction->forceDelete();
            $transaction->account?->updateBalance();

            DB::commit();

            return redirect()->route('transactions.trash')->with('success', 'Transaction permanently deleted.');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to permanently delete transaction: ' . $e->getMessage());
        }
    }

    private function formData(): array
    {
        $accounts = $this->getActiveAccounts();

        return array_merge(
            $this->getCategoriesForForm(),
            [
                'accounts'               => $accounts,
                'mpesaCosts'             => MobileMoneyRates::costs('mpesa'),
                'airtelCosts'            => MobileMoneyRates::costs('airtel_money'),
                'mpesaTransactionTypes'  => $this->getTransactionTypes('mpesa'),
                'airtelTransactionTypes' => $this->getTransactionTypes('airtel_money'),
                'defaultMpesaType'       => MobileMoneyTypeUsage::getMostUsedType(Auth::id(), 'mpesa') ?? 'send_money',
                'defaultAirtelType'      => MobileMoneyTypeUsage::getMostUsedType(Auth::id(), 'airtel_money') ?? 'send_money',
                'mpesaAccount'           => $accounts->where('type', 'mpesa')->first(),
            ]
        );
    }

    private function buildSuccessMessage(Transaction $transaction): string
    {
        $message = 'Transaction recorded successfully';

        if ($transaction->feeTransaction) {
            $feeAmount = number_format($transaction->feeTransaction->amount, 2);
            $message  .= " (including KSh {$feeAmount} transaction fee)";
        }

        return $message;
    }

    private function getCategoriesForForm(): array
    {
        $allowedChildren = ['Loan Repayment']; // exceptions that bypass parent exclusion

        $allCategories = Category::where('user_id', Auth::id())
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->whereNotIn('name', self::EXCLUDED_CATEGORIES)
            ->with(['parent' => fn($q) => $q->whereNotIn('name', self::EXCLUDED_CATEGORIES)])
            ->orderBy('usage_count', 'desc')
            ->orderBy('name')
            ->get()
            ->filter(fn($c) => $c->parent && (
                    !in_array($c->parent->name, self::EXCLUDED_CATEGORIES)
                    || in_array($c->name, $allowedChildren)
                ));

        $parentCategories = Category::where('user_id', Auth::id())
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereNotIn('name', self::EXCLUDED_CATEGORIES)
            ->get()
            ->keyBy('id');

        // For allowed children whose parent is excluded, inject a synthetic parent group
        $injectedParents = collect();
        foreach ($allCategories as $child) {
            if ($child->parent && in_array($child->parent->name, self::EXCLUDED_CATEGORIES)) {
                $injectedParents->put($child->parent_id, $child->parent);
            }
        }

        $allParents = $parentCategories->union($injectedParents);

        $categoryGroups = $allParents->map(fn($parent) => [
            'id'       => $parent->id,
            'name'     => $parent->name,
            'icon'     => $parent->icon,
            'type'     => $parent->type,
            'children' => $allCategories->where('parent_id', $parent->id)->values(),
        ])->filter(fn($g) => $g['children']->isNotEmpty())->values();

        $allCategoriesArray = $allCategories->map(fn($c) => [
            'id'          => $c->id,
            'name'        => $c->name,
            'icon'        => $c->icon,
            'parent_id'   => $c->parent_id,
            'usage_count' => $c->usage_count,
        ])->values()->toArray();

        return compact('categoryGroups', 'allCategoriesArray');
    }

    private function getActiveAccounts()
    {
        return Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('type', '!=', 'savings')
            ->where('current_balance', '>=', 1)
            ->orderBy('name')
            ->get();
    }

    private function getTransactionTypes(string $accountType): array
    {
        $baseTypes = MobileMoneyRates::types($accountType);

        return array_map(
            fn($key, $label) => ['key' => $key, 'label' => $label],
            array_keys($baseTypes),
            $baseTypes,
        );
    }
}
