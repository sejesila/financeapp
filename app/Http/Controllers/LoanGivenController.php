<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\LoanGiven;
use App\Models\LoanGivenPayment;
use App\Models\Referrer;
use App\Models\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Support\Facades\Cache;

class LoanGivenController extends Controller implements HasMiddleware
{
    use AuthorizesRequests;

    public static function middleware(): array
    {
        return ['auth'];
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        try {
            $this->authorize('viewAny', LoanGiven::class);

            $filter    = $request->get('filter', 'active');
            $period    = $request->get('period');
            $startDate = $request->get('start_date');
            $endDate   = $request->get('end_date');

            $minYear = LoanGiven::where('user_id', Auth::id())->min(DB::raw('YEAR(disbursed_date)')) ?? date('Y');
            $maxYear = date('Y');

            $activeLoans = LoanGiven::with(['account', 'payments', 'referrer'])
                ->where('user_id', Auth::id())
                ->where('status', 'active')
                ->orderBy('disbursed_date', 'desc')
                ->get();

            $paidLoansQuery = LoanGiven::with(['account', 'payments', 'referrer'])
                ->where('user_id', Auth::id())
                ->where('status', 'paid');

            if ($period) {
                match ($period) {
                    'this_month' => $paidLoansQuery->whereMonth('repaid_date', now()->month)->whereYear('repaid_date', now()->year),
                    'last_month' => $paidLoansQuery->whereMonth('repaid_date', now()->subMonth()->month)->whereYear('repaid_date', now()->subMonth()->year),
                    'this_year'  => $paidLoansQuery->whereYear('repaid_date', now()->year),
                    'last_year'  => $paidLoansQuery->whereYear('repaid_date', now()->year - 1),
                    'custom'     => $startDate && $endDate
                        ? $paidLoansQuery->whereBetween('repaid_date', [$startDate, $endDate])
                        : null,
                    default      => null,
                };
            }

            $paidLoans = $paidLoansQuery->orderBy('repaid_date', 'desc')->orderBy('updated_at', 'desc')->paginate(15)->withQueryString();

            // Stats (computed off actual rows, not accessors that assume upfront interest)
            $allLoans              = LoanGiven::where('user_id', Auth::id())->get();
            $paidLoansCollection   = $allLoans->where('status', 'paid');

            $totalPrincipal = $allLoans->sum('principal_amount');
            $totalRepaid    = $paidLoansCollection->sum('amount_paid');
            $totalInterest  = $paidLoansCollection->sum('interest_amount');

            $loansWithInterest = $paidLoansCollection->filter(fn ($loan) => $loan->interest_amount > 0);
            $avgInterestRate   = $loansWithInterest->isNotEmpty()
                ? $loansWithInterest->avg('interest_rate')
                : 0;

            $repaymentRate = $allLoans->isNotEmpty()
                ? ($paidLoansCollection->count() / $allLoans->count()) * 100
                : 0;

            $accounts = Account::where('user_id', Auth::id())
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            return view('loans-given.index', compact(
                'activeLoans', 'paidLoans', 'filter', 'period',
                'startDate', 'endDate', 'minYear', 'maxYear', 'accounts',
                'totalPrincipal', 'totalRepaid', 'totalInterest', 'avgInterestRate', 'repaymentRate'
            ));

        } catch (ValidationException|AuthorizationException $e) {
            // Let Laravel's normal handling take over (redirect-with-errors / 403).
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@index failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Failed to load loans: ' . $e->getMessage());
        }
    }

    // ── create ────────────────────────────────────────────────────────────────

    public function create()
    {
        try {
            $this->authorize('create', LoanGiven::class);

            $accounts = Account::where('user_id', Auth::id())
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $referrers = Referrer::where('is_active', true)
                ->orderBy('name')
                ->get();

            return view('loans-given.create', compact('accounts', 'referrers'));

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@create failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('loans-given.index')->with('error', 'Could not open the new loan form: ' . $e->getMessage());
        }
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        try {
            $this->authorize('create', LoanGiven::class);

            $validated = $request->validate([
                'borrower_name'    => 'required|string|max:255',
                'borrower_contact' => 'nullable|string|max:255',
                'account_id'       => 'required|exists:accounts,id',
                'principal_amount' => 'required|numeric|min:1',
                'disbursed_date'   => 'required|date',
                'due_date'         => 'nullable|date|after:disbursed_date',
                'notes'            => 'nullable|string',
                'referrer_id'                => 'nullable|exists:referrers,id',
                'referrer_share_percentage'  => 'nullable|numeric|min:0|max:100',
            ]);

            $referrerSharePercentage = null;

            if (!empty($validated['referrer_id'])) {
                $referrer = Referrer::where('is_active', true)->findOrFail($validated['referrer_id']);
                $this->authorize('view', $referrer);
                $referrerSharePercentage = $validated['referrer_share_percentage'] ?? $referrer->default_share_percentage;
            }

            $account = Account::withoutGlobalScopes()->findOrFail($validated['account_id']);
            $this->authorize('view', $account);

            if ($account->current_balance < $validated['principal_amount']) {
                return back()->with('error',
                    "Insufficient balance in {$account->name}. Required: KES " . number_format($validated['principal_amount'], 0) .
                    ", Available: KES " . number_format($account->current_balance, 0)
                )->withInput();
            }

            DB::beginTransaction();

            try {
                $principalAmount = (float) $validated['principal_amount'];
                $disbursedDate   = Carbon::parse($validated['disbursed_date']);
                $dueDate         = ($validated['due_date'] ?? null)
                    ? Carbon::parse($validated['due_date'])
                    : $disbursedDate->copy()->addDays(30);

                $loan = LoanGiven::create([
                    'user_id'          => Auth::id(),
                    'account_id'       => $validated['account_id'],
                    'borrower_name'    => $validated['borrower_name'],
                    'borrower_contact' => $validated['borrower_contact'] ?? null,
                    'principal_amount' => $principalAmount,
                    'balance'          => $principalAmount,
                    'disbursed_date'   => $disbursedDate,
                    'due_date'         => $dueDate,
                    'status'           => 'active',
                    'notes'            => $validated['notes'] ?? null,
                    'referrer_id'               => $validated['referrer_id'] ?? null,
                    'referrer_share_percentage' => $referrerSharePercentage,
                ]);

                $loanCategory = $this->firstOrCreateCategory('Friend Loan Given', 'expense');

                $transaction = Transaction::create([
                    'user_id'     => Auth::id(),
                    'account_id'  => $validated['account_id'],
                    'category_id' => $loanCategory->id,
                    'type'        => 'expense',
                    'description' => "Loan disbursed to {$validated['borrower_name']}",
                    'amount'      => $principalAmount,
                    'date'        => $disbursedDate,
                ]);

                // Direct link so destroy() never has to guess which transaction to remove.
                $loan->disbursement_transaction_id = $transaction->id;
                $loan->save();

                DB::commit();
                $account->updateBalance();

                $message = "Loan of KES " . number_format($principalAmount, 0) . " to {$validated['borrower_name']} recorded. "
                    . "Interest isn't set upfront — when you record repayments and close the loan out, "
                    . "the rate is calculated automatically from what actually comes back. "
                    . "Due on " . $dueDate->format('M d, Y') . ".";

                if (!empty($validated['referrer_id']) && $referrerSharePercentage !== null) {
                    $message .= " Referrer share on eventual interest: " . number_format($referrerSharePercentage, 1) . "%.";
                }

                return redirect()->route('loans-given.show', $loan->id)->with('success', $message);

            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to record loan: ' . $e->getMessage())->withInput();
        }
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function show(LoanGiven $loanGiven)
    {
        try {
            $this->authorize('view', $loanGiven);

            $loanGiven->load(['account', 'payments', 'referrer']);

            $today = Carbon::today();

            // diffInDays' sign/rounding behavior varies by Carbon version (some return
            // floats, some flip sign depending on which side you call it from), so we
            // pin it down explicitly: always request the absolute (unsigned) day count,
            // cast to int to drop any fractional part, then apply sign ourselves based
            // on isPast()/isFuture() — which are unambiguous regardless of Carbon version.
            $daysElapsed = (int) $today->diffInDays($loanGiven->disbursed_date->copy()->startOfDay(), true);

            $daysRemaining = null;
            if ($loanGiven->due_date) {
                $dueDate = $loanGiven->due_date->copy()->startOfDay();
                $diff    = (int) $today->diffInDays($dueDate, true);
                $daysRemaining = $dueDate->isPast() ? -$diff : ($dueDate->isToday() ? 0 : $diff);
            }

            $isOverdue = $loanGiven->due_date && now()->isAfter($loanGiven->due_date) && $loanGiven->status === 'active';
            $lastPaymentForClose = $loanGiven->status === 'active'
                ? $loanGiven->payments()->with('account')->orderByDesc('payment_date')->orderByDesc('id')->first()
                : null;

            $closingLandsInFloat = $loanGiven->status === 'active'
                && $loanGiven->surplus_received > 0
                && $lastPaymentForClose && $lastPaymentForClose->account && $lastPaymentForClose->account->type === 'referrer_float';

            $interestDestinationAccounts = $closingLandsInFloat
                ? Account::where('user_id', Auth::id())->where('is_active', true)->whereIn('type', ['mpesa', 'bank', 'cash'])->orderBy('name')->get()
                : collect();

            // Referrer's cut is only meaningful once there's an actual interest
            // amount to split — before that (active loan) we just show the %.
            $referrerPayout = null;
            if ($loanGiven->referrer_id && $loanGiven->referrer_share_percentage !== null && $loanGiven->interest_amount > 0) {
                $referrerPayout = round($loanGiven->interest_amount * ($loanGiven->referrer_share_percentage / 100), 2);
            }

            return view('loans-given.show', compact('loanGiven', 'daysElapsed', 'daysRemaining', 'isOverdue', 'referrerPayout','closingLandsInFloat', 'interestDestinationAccounts'));

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@show failed', ['loan_given_id' => $loanGiven->id ?? null, 'error' => $e->getMessage()]);
            return redirect()->route('loans-given.index')->with('error', 'Could not open that loan: ' . $e->getMessage());
        }
    }

    // ── payment form ──────────────────────────────────────────────────────────

    public function paymentForm(LoanGiven $loanGiven)
    {
        try {
            $this->authorize('makePayment', $loanGiven);

            if ($loanGiven->status !== 'active') {
                return back()->with('error', 'Only active loans can receive repayments');
            }

            $accounts = Account::where('user_id', Auth::id())
                ->where('is_active', true)
                ->whereIn('type', ['mpesa', 'bank', 'cash', 'referrer_float'])
                ->orderBy('name')
                ->get();

            $loanGiven->loadMissing('referrer');

            return view('loans-given.payment', compact('loanGiven', 'accounts'));

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@paymentForm failed', ['loan_given_id' => $loanGiven->id ?? null, 'error' => $e->getMessage()]);
            return redirect()->route('loans-given.show', $loanGiven->id)->with('error', 'Could not open the payment form: ' . $e->getMessage());
        }
    }

    // ── record payment ────────────────────────────────────────────────────────

    public function recordPayment(Request $request, LoanGiven $loanGiven)
    {
        try {
            $this->authorize('makePayment', $loanGiven);

            if ($loanGiven->status !== 'active') {
                return back()->with('error', 'Only active loans can receive repayments');
            }

            $validated = $request->validate([
                'payment_account_id'  => 'required|exists:accounts,id',
                'payment_amount'      => 'required|numeric|min:0.01',
                'payment_date'        => 'required|date|before_or_equal:today',
                'notes'               => 'nullable|string',
                'close_loan'          => 'nullable|in:1',
                'interest_account_id' => 'nullable|exists:accounts,id',
            ]);

            DB::beginTransaction();

            try {
                $paymentAmount  = (float) $validated['payment_amount'];
                $paymentDate    = $validated['payment_date'];
                $paymentAccount = Account::findOrFail($validated['payment_account_id']);

                if ($paymentAccount->user_id !== Auth::id()) {
                    // Deliberately a plain Exception, not AuthorizationException — this
                    // is meant to fall through to the generic catch below and become a
                    // "Payment failed: ..." flash message, same as any other bad input
                    // here, rather than a hard 403.
                    throw new Exception("Unauthorized access to this account.");
                }

                // Reuses the existing 'Loan Recovery' category for the same reason.
                $repaymentCategory = $this->firstOrCreateCategory('Loan Recovery', 'income');

                // Money lands back in the account — income from the account's perspective.
                // (Excluded from the Budget dashboard's income totals — this is principal
                // returning, not new income. Only the interest portion, split out below
                // if the loan closes, is real profit.)
                $transaction = Transaction::create([
                    'user_id'     => Auth::id(),
                    'account_id'  => $paymentAccount->id,
                    'category_id' => $repaymentCategory->id,
                    'type'        => 'income',
                    'description' => "Loan repayment from {$loanGiven->borrower_name}",
                    'amount'      => $paymentAmount,
                    'date'        => $paymentDate,
                ]);

                LoanGivenPayment::create([
                    'user_id'        => Auth::id(),
                    'loan_given_id'  => $loanGiven->id,
                    'account_id'     => $paymentAccount->id,
                    'amount'         => $paymentAmount,
                    'payment_date'   => $paymentDate,
                    'transaction_id' => $transaction->id,
                    'notes'          => $validated['notes'] ?? null,
                ]);

                // Just accumulate — no principal/interest split, since the split isn't
                // knowable until the loan is closed out below.
                $loanGiven->amount_paid += $paymentAmount;
                $loanGiven->balance      = max(0, $loanGiven->principal_amount - $loanGiven->amount_paid);
                $loanGiven->save();

                $closedNow = false;
                $affectedAccountIds = [];

                if (($validated['close_loan'] ?? null) === '1') {
                    $interestAccount = null;

                    if ($loanGiven->surplus_received > 0 && $paymentAccount->type === 'referrer_float') {
                        if (empty($validated['interest_account_id'])) {
                            throw new Exception("This payment lands in the referrer float — select an account to receive the interest.");
                        }

                        $interestAccount = Account::findOrFail($validated['interest_account_id']);

                        if ($interestAccount->user_id !== Auth::id()) {
                            throw new Exception("Unauthorized access to this account.");
                        }

                        if ($interestAccount->type === 'referrer_float') {
                            throw new Exception("Interest can't be deposited into another referrer float account.");
                        }
                    }

                    $loanGiven->closeAsRepaid($paymentDate);
                    $affectedAccountIds = $this->splitInterestOutOfFinalPayment($loanGiven, $interestAccount);
                    $closedNow = true;
                }

                DB::commit();
                $paymentAccount->updateBalance();

                foreach ($affectedAccountIds as $accId) {
                    if ($accId !== $paymentAccount->id) {
                        Account::find($accId)?->updateBalance();
                    }
                }

                $successMessage = "Repayment of KES " . number_format($paymentAmount, 0) . " from {$loanGiven->borrower_name} recorded into {$paymentAccount->name}!";

                if ($closedNow) {
                    $successMessage .= " Loan closed as fully repaid.";
                    if ($loanGiven->interest_amount > 0) {
                        $successMessage .= " Interest earned: KES " . number_format($loanGiven->interest_amount, 0)
                            . " (" . number_format($loanGiven->interest_rate, 1) . "%).";

                        if ($loanGiven->referrer_id && $loanGiven->referrer_share_percentage !== null) {
                            $referrerPayout = round($loanGiven->interest_amount * ($loanGiven->referrer_share_percentage / 100), 2);
                            $successMessage .= " Referrer's cut (" . number_format($loanGiven->referrer_share_percentage, 1) . "%): KES " . number_format($referrerPayout, 0) . ".";
                        }
                    } else {
                        $successMessage .= " No interest was received above principal.";
                    }
                }

                return redirect()->route('loans-given.show', $loanGiven->id)->with('success', $successMessage);

            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@recordPayment failed', [
                'loan_given_id' => $loanGiven->id,
                'user_id'       => Auth::id(),
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Payment failed: ' . $e->getMessage())->withInput();
        }
    }

    // ── close as repaid (standalone action, e.g. from the loan page) ───────────

    public function close(Request $request, LoanGiven $loanGiven)
    {
        try {
            $this->authorize('makePayment', $loanGiven);

            if ($loanGiven->status !== 'active') {
                return back()->with('error', 'Only active loans can be closed as repaid');
            }

            $lastPayment = $loanGiven->payments()
                ->with('account')
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->first();

            $needsInterestAccount = $loanGiven->surplus_received > 0
                && $lastPayment && $lastPayment->account && $lastPayment->account->type === 'referrer_float';

            $validated = $request->validate([
                'interest_account_id' => $needsInterestAccount ? 'required|exists:accounts,id' : 'nullable|exists:accounts,id',
            ], [
                'interest_account_id.required' => 'The last payment landed in a referrer float account — select where the interest should go instead.',
            ]);

            $interestAccount = null;

            if ($needsInterestAccount) {
                $interestAccount = Account::findOrFail($validated['interest_account_id']);

                if ($interestAccount->user_id !== Auth::id()) {
                    return back()->with('error', 'Unauthorized access to this account.');
                }

                if ($interestAccount->type === 'referrer_float') {
                    return back()->with('error', "Interest can't be deposited into another referrer float account.");
                }
            }

            DB::beginTransaction();

            try {
                $loanGiven->closeAsRepaid();
                $affectedAccountIds = $this->splitInterestOutOfFinalPayment($loanGiven, $interestAccount);

                DB::commit();

                foreach ($affectedAccountIds as $accId) {
                    Account::find($accId)?->updateBalance();
                }

                $message = "Loan with {$loanGiven->borrower_name} closed as fully repaid.";
                if ($loanGiven->interest_amount > 0) {
                    $message .= " Interest earned: KES " . number_format($loanGiven->interest_amount, 0)
                        . " (" . number_format($loanGiven->interest_rate, 1) . "%).";

                    if ($loanGiven->referrer_id && $loanGiven->referrer_share_percentage !== null) {
                        $referrerPayout = round($loanGiven->interest_amount * ($loanGiven->referrer_share_percentage / 100), 2);
                        $message .= " Referrer's cut (" . number_format($loanGiven->referrer_share_percentage, 1) . "%): KES " . number_format($referrerPayout, 0) . ".";
                    }
                } else {
                    $message .= " No interest was received above principal.";
                }

                return redirect()->route('loans-given.show', $loanGiven->id)->with('success', $message);

            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@close failed', [
                'loan_given_id' => $loanGiven->id,
                'error'         => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to close loan: ' . $e->getMessage());
        }
    }

    // ── mark defaulted / written off ─────────────────────────────────────────

    public function markStatus(Request $request, LoanGiven $loanGiven)
    {
        try {
            $this->authorize('makePayment', $loanGiven);

            $validated = $request->validate([
                'status' => 'required|in:defaulted,written_off,active',
            ]);

            if ($loanGiven->status === 'paid') {
                return back()->with('error', 'Cannot change status of a fully paid loan');
            }

            $loanGiven->status = $validated['status'];
            $loanGiven->save();

            return redirect()->route('loans-given.show', $loanGiven->id)
                ->with('success', 'Loan status updated to ' . str_replace('_', ' ', $validated['status']) . '.');

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@markStatus failed', ['loan_given_id' => $loanGiven->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Failed to update loan status: ' . $e->getMessage());
        }
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function destroy(LoanGiven $loanGiven)
    {
        try {
            $this->authorize('delete', $loanGiven);

            if ($loanGiven->status !== 'active') {
                return back()->with('error', 'Cannot delete non-active loans');
            }

            if ($loanGiven->amount_paid > 0) {
                return back()->with('error', 'Cannot delete loans that have received partial or full repayment');
            }

            DB::beginTransaction();

            try {
                foreach ($loanGiven->payments as $payment) {
                    if ($payment->transaction_id) {
                        Transaction::where('id', $payment->transaction_id)->forceDelete();
                    }
                    $payment->delete();
                }

                if ($loanGiven->disbursement_transaction_id) {
                    Transaction::where('id', $loanGiven->disbursement_transaction_id)->forceDelete();
                }

                $loanGiven->delete();
                DB::commit();

                Account::find($loanGiven->account_id)?->updateBalance();

                return redirect()->route('loans-given.index')->with('success', 'Loan deleted successfully');

            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@destroy failed', ['loan_given_id' => $loanGiven->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Failed to delete loan: ' . $e->getMessage());
        }
    }

    // ── private helpers ───────────────────────────────────────────────────────

    private function firstOrCreateCategory(string $name, string $type): Category
    {
        $validTypes = ['income', 'expense', 'liability'];

        if (!in_array($type, $validTypes)) {
            $type = 'expense';
        }

        // Special handling for interest - try multiple variations
        if ($name === 'Loan Interest') {
            // Check for existing interest categories
            $existing = Category::where('user_id', Auth::id())
                ->whereIn('name', ['Interest', 'Loan Interest', 'Interest Income'])
                ->where('type', 'income')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Look up by name/user only (not parent_id)
        $existing = Category::where('user_id', Auth::id())
            ->where('name', $name)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Category::create([
            'user_id'   => Auth::id(),
            'parent_id' => null,
            'name'      => $name,
            'type'      => $type,
            'is_active' => true,
        ]);
    }

    private function splitInterestOutOfFinalPayment(LoanGiven $loanGiven, ?Account $interestAccount = null): array
    {
        $interestAmount = (float) $loanGiven->interest_amount;

        if ($interestAmount <= 0) {
            return [];
        }

        $lastPayment = $loanGiven->payments()
            ->with('account')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->first();

        if (!$lastPayment || !$lastPayment->transaction_id) {
            return [];
        }

        $transaction = Transaction::find($lastPayment->transaction_id);
        if (!$transaction) {
            return [];
        }

        $affectedAccountIds = [$lastPayment->account_id];

        $interestAmount = min($interestAmount, (float) $transaction->amount);
        $remainder      = round($transaction->amount - $interestAmount, 2);

        if ($remainder <= 0) {
            $transaction->delete();
        } else {
            $transaction->amount = $remainder;
            $transaction->save();
        }

        // Get the interest category - this will now use the existing "Interest" category if available
        $interestCategory = $this->firstOrCreateCategory('Loan Interest', 'income');
        $destinationAccountId = $interestAccount->id ?? $lastPayment->account_id;

        Transaction::create([
            'user_id'     => Auth::id(),
            'account_id'  => $destinationAccountId,
            'category_id' => $interestCategory->id,
            'type'        => 'income',
            'description' => "Interest earned from {$loanGiven->borrower_name}'s loan"
                . ($interestAccount ? " (routed out of {$lastPayment->account->name})" : ''),
            'amount'      => $interestAmount,
            'date'        => $lastPayment->payment_date,
        ]);

        $affectedAccountIds[] = $destinationAccountId;

        return array_unique($affectedAccountIds);
    }
    // ── reverse interest transaction ──────────────────────────────────────────────


// ── reverse interest transaction ──────────────────────────────────────────────
    public function reverseInterest(Transaction $transaction)
    {
        try {
            if (!in_array($transaction->category->name, ['Interest', 'Loan Interest', 'Interest Income'])) {
                return back()->with('error', 'This transaction is not an interest transaction.');
            }

            $loanGiven = null;

            if ($transaction->reference_id) {
                $payment = LoanGivenPayment::where('id', $transaction->reference_id)->first();
                if ($payment) {
                    $loanGiven = $payment->loanGiven;
                }
            }

            if (!$loanGiven) {
                $payment = LoanGivenPayment::where('transaction_id', $transaction->id)->first();
                if ($payment) {
                    $loanGiven = $payment->loanGiven;
                }
            }

            if (!$loanGiven) {
                $possibleLoans = LoanGiven::where('user_id', Auth::id())
                    ->where('status', 'paid')
                    ->where('interest_amount', '>', 0)
                    ->whereHas('payments', function ($q) use ($transaction) {
                        $q->whereDate('payment_date', '>=', $transaction->date->subDays(30))
                            ->whereDate('payment_date', '<=', $transaction->date->addDays(30));
                    })
                    ->get();

                foreach ($possibleLoans as $loan) {
                    if (abs($loan->interest_amount - $transaction->amount) < 0.01) {
                        $loanGiven = $loan;
                        break;
                    }
                }
            }

            if (!$loanGiven) {
                return back()->with('error', 'Could not find the loan associated with this interest transaction.');
            }

            // Once this loan's interest has been included in a referrer payout,
            // interest_amount is no longer just "this loan's own" figure to freely
            // recompute — a payout record downstream depends on it being final.
            // Block the reversal outright rather than let it silently desync from
            // what was actually paid out.
            if ($loanGiven->referrer_payout_id) {
                return back()->with('error',
                    'This loan\'s interest has already been included in a referrer payout '
                    . '(payout #' . $loanGiven->referrer_payout_id . '). It can no longer be reversed. '
                    . 'If the payout itself needs correcting, that has to be handled first.'
                );
            }

            $this->authorize('view', $loanGiven);

            DB::beginTransaction();

            try {
                $payment = $loanGiven->payments()
                    ->where('transaction_id', $transaction->id)
                    ->orWhere('id', $transaction->reference_id)
                    ->first();

                if (!$payment) {
                    $payment = $loanGiven->payments()
                        ->orderByDesc('payment_date')
                        ->orderByDesc('id')
                        ->first();

                    if (!$payment) {
                        throw new Exception('Could not find the payment that generated this interest.');
                    }
                }

                // withTrashed(): the original transaction may have been soft-deleted
                // by splitInterestOutOfFinalPayment() if the entire final payment
                // was pure interest.
                $originalTransaction = Transaction::withTrashed()->find($payment->transaction_id);

                if (!$originalTransaction) {
                    throw new Exception('Original payment transaction not found.');
                }

                $interestAmount = (float) $transaction->amount;

                if ($originalTransaction->trashed()) {
                    // The whole payment was interest — restore it at its full amount.
                    $originalTransaction->restore();
                    $originalTransaction->amount = $payment->amount;
                    $originalTransaction->save();
                } elseif ($originalTransaction->amount < (float) $payment->amount) {
                    // Only part of the payment was split off as interest — add it back.
                    $originalTransaction->amount += $interestAmount;
                    $originalTransaction->save();
                }

                $interestAccountId = $transaction->account_id;
                $transaction->delete();

                // The interest being reversed is no longer "paid" against the loan —
                // it reverts to being an unallocated part of amount_paid, so pull it
                // back out and recompute everything from that.
                $loanGiven->amount_paid = max(0, $loanGiven->amount_paid - $interestAmount);
                $loanGiven->balance     = max(0, $loanGiven->principal_amount - $loanGiven->amount_paid);
                $loanGiven->interest_amount = max(0, $loanGiven->amount_paid - $loanGiven->principal_amount);

                $loanGiven->interest_rate = ($loanGiven->principal_amount > 0 && $loanGiven->interest_amount > 0)
                    ? round(($loanGiven->interest_amount / $loanGiven->principal_amount) * 100, 2)
                    : 0;

                // Interest only ever exists because closeAsRepaid() generated it. Reversing
                // it is undoing that closure, not a question of whether principal still
                // nets to zero — so a 'paid' loan always reopens here.
                if ($loanGiven->status === 'paid') {
                    $loanGiven->status = 'active';
                    $loanGiven->repaid_date = null;
                }

                $loanGiven->save();

                $interestAccount = Account::find($interestAccountId);
                $interestAccount?->updateBalance();

                if ($originalTransaction->account_id !== $interestAccountId) {
                    Account::find($originalTransaction->account_id)?->updateBalance();
                }

                if ($interestAccount) {
                    Cache::forget("account.{$interestAccount->id}.stats");
                }
                Cache::forget("account.{$originalTransaction->account_id}.stats");

                DB::commit();

                return redirect()->route('loans-given.show', $loanGiven->id)
                    ->with('success', 'Interest transaction of KES ' . number_format($interestAmount, 0) . ' has been reversed. The loan has been recalculated.');

            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@reverseInterest failed', [
                'transaction_id' => $transaction->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to reverse interest: ' . $e->getMessage());
        }
    }
}
