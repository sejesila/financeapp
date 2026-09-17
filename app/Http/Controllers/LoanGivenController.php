<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\LoanGiven;
use App\Models\LoanGivenPayment;
use App\Models\Referrer;
use App\Models\Transaction;
use App\Services\TransactionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class LoanGivenController extends Controller implements HasMiddleware
{
    use AuthorizesRequests;

    public function __construct(protected TransactionService $transactionService)
    {
    }

    public static function middleware(): array
    {
        return ['auth'];
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        try {
            $this->authorize('viewAny', LoanGiven::class);

            $filter = $request->get('filter', 'active');
            $period = $request->get('period');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $minYear = LoanGiven::where('user_id', Auth::id())->min(DB::raw('YEAR(disbursed_date)')) ?? date('Y');
            $maxYear = date('Y');

            $sort = $request->get('sort', 'date_desc');
            $referrerId = $request->get('referrer_id');

            $activeLoansQuery = LoanGiven::with(['account', 'payments', 'referrer'])
                ->where('user_id', Auth::id())
                ->where('status', 'active');

            if ($referrerId) {
                $activeLoansQuery->where('referrer_id', $referrerId);
            }

            match ($sort) {
                'referrer' => $activeLoansQuery
                    ->leftJoin('referrers', 'loans_given.referrer_id', '=', 'referrers.id')
                    ->orderByRaw('referrers.name IS NULL')
                    ->orderBy('referrers.name')
                    ->orderBy('loans_given.disbursed_date', 'desc')
                    ->orderBy('loans_given.created_at', 'desc')
                    ->select('loans_given.*'),
                default => $activeLoansQuery
                    ->orderBy('disbursed_date', 'desc')
                    ->orderBy('created_at', 'desc'),
            };


            $activeLoans = $activeLoansQuery->get();
            // Catch up any loan that's now more than 5 days past due: capitalizes
// expected interest into principal, recomputes expected interest on the
// new principal, and pushes the due date forward. No-op for loans not
// overdue enough, or with no expected_interest_rate set.
            foreach ($activeLoans as $loan) {
                $loan->processOverdueRollover();
            }
            $referrers = Referrer::where('is_active', true)->orderBy('name')->get();
            $paidLoansQuery = LoanGiven::with(['account', 'payments', 'referrer'])
                ->where('user_id', Auth::id())
                ->where('status', 'paid');

            if ($period) {
                $lastMonth = now()->subMonthNoOverflow();

                match ($period) {
                    'this_month' => $paidLoansQuery->whereMonth('repaid_date', now()->month)->whereYear('repaid_date', now()->year),
                    'last_month' => $paidLoansQuery->whereMonth('repaid_date', $lastMonth->month)->whereYear('repaid_date', $lastMonth->year),
                    'this_year' => $paidLoansQuery->whereYear('repaid_date', now()->year),
                    'last_year' => $paidLoansQuery->whereYear('repaid_date', now()->year - 1),
                    'custom' => $startDate && $endDate
                        ? $paidLoansQuery->whereBetween('repaid_date', [$startDate, $endDate])
                        : null,
                    default => null,
                };
            }

            $paidLoans = $paidLoansQuery->orderBy('repaid_date', 'desc')->orderBy('updated_at', 'desc')->paginate(15)->withQueryString();

            // Stats (computed off actual rows, not accessors that assume upfront interest)
            $allLoans = LoanGiven::where('user_id', Auth::id())->get();
            $paidLoansCollection = $allLoans->where('status', 'paid');

            $totalPrincipal = $allLoans->sum('principal_amount');
            $totalRepaid = $paidLoansCollection->sum('amount_paid');
            // A closed loan's interest_amount already captures ALL interest it
            // ever earned (including any rollover payments before it closed),
            // since closeAsRepaid() derives it from the lifetime amount_paid.
            // But an active loan has no interest_amount yet — its recognized
            // interest lives only in its payments' interest_portion, and that's
            // real money already booked as income (see splitInterestFromRolloverPayment()),
            // so it has to be added here or the dashboard undercounts actual
            // interest income the moment a rollover payment happens on a loan
            // that hasn't closed yet.
            $totalInterest = $paidLoansCollection->sum('interest_amount')
                + $activeLoans->sum(fn ($loan) => $loan->payments->sum('interest_portion'));
            $totalOutstanding = $activeLoans->sum('balance');

            // Transaction costs paid out to disburse these loans (M-Pesa/bank/etc
            // fees), pulled from the linked fee transactions on each loan's
            // disbursement — same relationship the show page uses for
            // $disbursementFee. All-time, active + paid, same scope as
            // totalPrincipal, so "Net Interest" below is a fair like-for-like figure.
            $disbursementTransactionIds = $allLoans->pluck('disbursement_transaction_id')->filter()->values();

            $totalTransactionCosts = $disbursementTransactionIds->isNotEmpty()
                ? Transaction::where('is_transaction_fee', true)
                    ->whereIn('fee_for_transaction_id', $disbursementTransactionIds)
                    ->sum('amount')
                : 0;

            // What interest actually nets out to once the cost of disbursing the
            // loans is backed out. Uses totalInterest (includes active rollovers),
            // so this can move before a loan closes, same as Interest Earned does.
            $netInterest = $totalInterest - $totalTransactionCosts;

            // "Total Repaid" above only counts closed loans, so partial repayments
            // sitting on still-active loans (e.g. Enock, Emmanuel HR) never show up
            // anywhere in the summary — they just quietly reduce `balance`. This
            // figure is the actual all-time cash collected: partial + full.
            $totalReceivedAllTime = $activeLoans->sum('amount_paid') + $paidLoansCollection->sum('amount_paid');

            $loansWithInterest = $paidLoansCollection->filter(fn($loan) => $loan->interest_amount > 0);
            $avgInterestRate = $loansWithInterest->isNotEmpty()
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
                'activeLoans', 'paidLoans', 'filter', 'period','sort', 'referrerId', 'referrers',
                'startDate', 'endDate', 'minYear', 'maxYear', 'accounts',
                'totalPrincipal', 'totalRepaid', 'totalInterest', 'avgInterestRate', 'repaymentRate',
                'totalOutstanding', 'totalReceivedAllTime', 'totalTransactionCosts', 'netInterest'
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
                'borrower_name' => 'required|string|max:255',
                'borrower_contact' => 'nullable|string|max:255',
                'account_id' => 'required|exists:accounts,id',
                'principal_amount' => 'required|numeric|min:1',
                'transaction_cost' => 'nullable|numeric|min:0',
                'disbursed_date' => 'required|date',
                'due_date' => 'nullable|date|after:disbursed_date',
                'notes' => 'nullable|string',
                'referrer_id' => 'nullable|exists:referrers,id',
                'referrer_share_percentage' => 'nullable|numeric|min:0|max:100',
                'interest_rate' => 'nullable|numeric|min:0|max:1000',
            ]);

            $referrerSharePercentage = null;
            $expectedInterestRate = $validated['interest_rate'] ?? null;

            if (!empty($validated['referrer_id'])) {
                $referrer = Referrer::where('is_active', true)->findOrFail($validated['referrer_id']);
                $this->authorize('view', $referrer);
                $referrerSharePercentage = $validated['referrer_share_percentage'] ?? $referrer->default_share_percentage;

                // Only fall back to the referrer's default when the user didn't type
                // one in themselves — an explicit rate always wins.
                if ($expectedInterestRate === null) {
                    $expectedInterestRate = $referrer->default_interest_rate;
                }
            }

            $account = Account::withoutGlobalScopes()->findOrFail($validated['account_id']);
            $this->authorize('view', $account);

            $principalAmount = (float)$validated['principal_amount'];

            $expectedInterestAmount = $expectedInterestRate !== null
                ? round($principalAmount * ((float)$expectedInterestRate / 100), 2)
                : 0;

            DB::beginTransaction();

            try {
                $disbursedDate = Carbon::parse($validated['disbursed_date']);
                $dueDate = ($validated['due_date'] ?? null)
                    ? Carbon::parse($validated['due_date'])
                    : $disbursedDate->copy()->addDays(30);

                $loan = LoanGiven::create([
                    'user_id' => Auth::id(),
                    'account_id' => $validated['account_id'],
                    'borrower_name' => $validated['borrower_name'],
                    'borrower_contact' => $validated['borrower_contact'] ?? null,
                    'principal_amount' => $principalAmount,
                    'balance' => $principalAmount,
                    'disbursed_date' => $disbursedDate,
                    'due_date' => $dueDate,
                    'status' => 'active',
                    'notes' => $validated['notes'] ?? null,
                    'referrer_id' => $validated['referrer_id'] ?? null,
                    'referrer_share_percentage' => $referrerSharePercentage,
                    'expected_interest_rate' => $expectedInterestRate,
                    'expected_interest_amount' => $expectedInterestAmount,
                ]);

                $loanCategory = $this->firstOrCreateCategory('Friend Loan Given', 'expense');

                // Routed through TransactionService instead of built by hand — this
                // disbursement gets the exact same fee handling as every other
                // transaction: auto-calculated from the real M-Pesa/Airtel tier
                // tables when the account is mobile money, linked via
                // is_transaction_fee/fee_for_transaction_id, and included in
                // TransactionStatsService::feeTotals(). The balance check
                // (principal + fee must be available) also happens inside here,
                // same as it does for every other transaction.
                $transaction = $this->transactionService->createTransaction([
                    'account_id' => $validated['account_id'],
                    'category_id' => $loanCategory->id,
                    'amount' => $principalAmount,
                    'date' => $disbursedDate,
                    'description' => "Loan disbursed to {$validated['borrower_name']}",
                    // Lets the user type in the real fee (e.g. a bank transfer
                    // charge, or an M-Pesa charge that differs from the tier
                    // table) instead of relying purely on the auto-calculated
                    // one. Leave blank to just use the auto-calculated fee (or
                    // none, for cash/bank disbursements).
                    'manual_fee' => $validated['transaction_cost'] ?? null,
                ]);

                // Direct link so destroy() never has to guess which transaction to remove.
                $loan->disbursement_transaction_id = $transaction->id;
                $loan->save();

                DB::commit();
                $account->refresh();

                $message = "Loan of KES " . number_format($principalAmount, 0) . " to {$validated['borrower_name']} recorded. "
                    . "Interest isn't set upfront — when you record repayments and close the loan out, "
                    . "the rate is calculated automatically from what actually comes back. "
                    . "Due on " . $dueDate->format('M d, Y') . ".";

                if ($transaction->feeTransaction) {
                    $message .= " Transaction fee of KES " . number_format($transaction->feeTransaction->amount, 0)
                        . " recorded separately as an expense — it won't count toward what {$validated['borrower_name']} owes.";
                }

                if (!empty($validated['referrer_id']) && $referrerSharePercentage !== null) {
                    $message .= " Referrer share on eventual interest: " . number_format($referrerSharePercentage, 1) . "%.";
                }

                if ($expectedInterestRate !== null) {
                    $message .= " Expected interest at " . number_format($expectedInterestRate, 1) . "%: KES "
                        . number_format($expectedInterestAmount, 0) . " (total expected: KES "
                        . number_format($principalAmount + $expectedInterestAmount, 0) . ").";
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
            if ($loanGiven->processOverdueRollover()) {
                session()->flash('success',
                    "This loan was more than 5 days overdue and has been automatically rolled over. "
                    . "New principal: KES " . number_format($loanGiven->principal_amount, 0)
                    . ", new expected interest: KES " . number_format($loanGiven->expected_interest_amount, 0)
                    . ", new due date: " . $loanGiven->due_date->format('M d, Y') . "."
                );
            }

            // Pull the fee off the disbursement transaction (if it had one), so
            // the view can show what the disbursement actually cost. This lives
            // wherever every other transaction fee lives — no extra column
            // needed on loan_givens for it.
            $disbursementFee = null;
            if ($loanGiven->disbursement_transaction_id) {
                $disbursementTransaction = Transaction::with('feeTransaction')
                    ->find($loanGiven->disbursement_transaction_id);
                $disbursementFee = $disbursementTransaction?->feeTransaction;
            }

            $today = Carbon::today();

            // diffInDays' sign/rounding behavior varies by Carbon version (some return
            // floats, some flip sign depending on which side you call it from), so we
            // pin it down explicitly: always request the absolute (unsigned) day count,
            // cast to int to drop any fractional part, then apply sign ourselves based
            // on isPast()/isFuture() — which are unambiguous regardless of Carbon version.
            $daysElapsed = (int)$today->diffInDays($loanGiven->disbursed_date->copy()->startOfDay(), true);

            $daysRemaining = null;
            if ($loanGiven->due_date) {
                $dueDate = $loanGiven->due_date->copy()->startOfDay();
                $diff = (int)$today->diffInDays($dueDate, true);
                $daysRemaining = $dueDate->isPast() ? -$diff : ($dueDate->isToday() ? 0 : $diff);
            }

            $isOverdue = $loanGiven->due_date && now()->isAfter($loanGiven->due_date) && $loanGiven->status === 'active';
            $lastPaymentForClose = $loanGiven->status === 'active'
                ? $loanGiven->payments()->with('account')->orderByDesc('payment_date')->orderByDesc('id')->first()
                : null;

            // Purely used to decide whether the "route interest out now"
            // option is worth showing at all — it's optional either way,
            // not a requirement. Leaving it means the interest stays in the
            // float, trackable via the referrer's float reconciliation page.
            $closingLandsInFloat = $loanGiven->status === 'active'
                && $loanGiven->surplus_received > 0
                && $lastPaymentForClose && $lastPaymentForClose->account && $lastPaymentForClose->account->type === 'referrer_float';

            $interestDestinationAccounts = $closingLandsInFloat
                ? Account::where('user_id', Auth::id())->where('is_active', true)->whereIn('type', ['mpesa', 'bank', 'cash'])->orderBy('name')->get()
                : collect();

            // Referrer's cut is only meaningful once there's an actual interest
            // amount to split — before that (active loan) we just show the %.
            $referrerPayout = null;
            if ($loanGiven->referrer_id && $loanGiven->referrer_share_percentage !== null
                && $loanGiven->interest_amount > 0 && !$loanGiven->referrer_deducted_before_deposit) {
                $referrerPayout = round($loanGiven->interest_amount * ($loanGiven->referrer_share_percentage / 100), 2);
            }

            return view('loans-given.show', compact(
                'loanGiven', 'daysElapsed', 'daysRemaining', 'isOverdue', 'referrerPayout',
                'closingLandsInFloat', 'interestDestinationAccounts', 'disbursementFee'
            ));

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
                'payment_account_id' => 'required|exists:accounts,id',
                'payment_amount' => 'required|numeric|min:0.01',
                'interest_portion' => 'nullable|numeric|min:0|lte:payment_amount',
                'payment_date' => 'required|date|before_or_equal:today',
                'notes' => 'nullable|string',
                'close_loan' => 'nullable|in:1',
                'interest_account_id' => 'nullable|exists:accounts,id',
                'referrer_deducted_before_deposit' => 'nullable|in:1',
            ]);

            DB::beginTransaction();

            try {
                $paymentAmount = (float)$validated['payment_amount'];
                $paymentDate = $validated['payment_date'];
                $paymentAccount = Account::findOrFail($validated['payment_account_id']);
                $isClosing = ($validated['close_loan'] ?? null) === '1';

                // Interest is only recognized per-payment on a rollover (non-final)
                // payment. A final close derives total interest from the full
                // lifetime surplus instead (LoanGiven::closeAsRepaid()), which
                // already correctly folds in every rollover's interest via
                // amount_paid — so an interest_portion submitted alongside
                // close_loan is ignored here rather than risking double counting.
                $interestPortion = $isClosing ? 0.0 : (float)($validated['interest_portion'] ?? 0);

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
                // returning, not new income. Only the interest portion — split out below
                // for a rollover, or by splitInterestOutOfFinalPayment() on closure — is
                // real profit.)
                $transaction = Transaction::create([
                    'user_id' => Auth::id(),
                    'account_id' => $paymentAccount->id,
                    'category_id' => $repaymentCategory->id,
                    'type' => 'income',
                    'description' => "Loan repayment from {$loanGiven->borrower_name}",
                    'amount' => $paymentAmount,
                    'date' => $paymentDate,
                ]);

                $payment = LoanGivenPayment::create([
                    'user_id' => Auth::id(),
                    'loan_given_id' => $loanGiven->id,
                    'account_id' => $paymentAccount->id,
                    'amount' => $paymentAmount,
                    'interest_portion' => $interestPortion,
                    'payment_date' => $paymentDate,
                    'transaction_id' => $transaction->id,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $affectedAccountIds = [$paymentAccount->id];

                if ($interestPortion > 0) {
                    // Real-time recognition: this interest is booked as income right
                    // now, not deferred until the loan eventually closes. It's carved
                    // out of the same transaction that just landed, exactly like
                    // splitInterestOutOfFinalPayment() does at closure, just without
                    // touching the loan's own interest_amount/interest_rate — those
                    // stay reserved for the final, whole-loan figures computed once
                    // in closeAsRepaid().
                    //
                    // FIX: this call previously passed only 3 arguments, but
                    // splitInterestFromRolloverPayment() requires a 4th ($paymentId,
                    // no default) — that was a guaranteed ArgumentCountError on every
                    // partial payment submitted with an interest portion.
                    $affectedAccountIds = array_merge(
                        $affectedAccountIds,
                        $this->splitInterestFromRolloverPayment($transaction, $interestPortion, $loanGiven, $payment->id)
                    );
                }

                // Recompute amount_paid / principal_paid / balance straight from the
                // payments table (now includes this one, with its interest_portion),
                // rather than incrementing fields by hand.
                $loanGiven->updateBalance();

                // Any partial (non-closing) payment — whether or not it specifies an
                // interest portion — starts a fresh 30-day period on whatever
                // principal remains, dated from this payment's date.
                if (!$isClosing) {
                    $loanGiven->due_date = Carbon::parse($paymentDate)->addDays(30);
                    $loanGiven->save();
                }

                $closedNow = false;

                if ($isClosing) {
                    $interestAccount = null;

                    // Optional now: if the closing payment landed in a referrer float
                    // account, the user can still pick a real account here to route
                    // the interest straight out — but leaving it blank is fine too.
                    // It just means the interest stays in the float alongside the
                    // principal, exactly like a rollover payment would, and shows up
                    // as pending for this loan on the referrer's float reconciliation
                    // page (Referrer::pendingFloatInterestByLoan() /
                    // ReferrerFloatController) rather than needing to be resolved
                    // right now.
                    if (!empty($validated['interest_account_id'])) {
                        $interestAccount = Account::findOrFail($validated['interest_account_id']);

                        if ($interestAccount->user_id !== Auth::id()) {
                            throw new Exception("Unauthorized access to this account.");
                        }

                        if ($interestAccount->type === 'referrer_float') {
                            throw new Exception("Interest can't be deposited into another referrer float account.");
                        }
                    }

                    $loanGiven->closeAsRepaid($paymentDate);

                    // FIX: previously this always split out $interestPortion, which
                    // is forced to 0.0 whenever $isClosing is true — so the real
                    // interest closeAsRepaid() just computed (amount_paid minus
                    // principal_amount, across the loan's whole life) was NEVER
                    // actually carved into its own "Loan Interest" income
                    // transaction. Instead a pointless KES 0 transaction was
                    // created every time a loan was closed via this endpoint.
                    //
                    // The correct amount to split out of THIS payment's transaction
                    // is the loan's final interest_amount minus whatever interest was
                    // already recognized by earlier rollover payments (those already
                    // got their own "Loan Interest" transaction at the time they were
                    // recorded) — otherwise that portion would be double-counted.
                    $alreadyRecognizedInterest = (float) $loanGiven->payments()
                        ->where('id', '!=', $payment->id)
                        ->sum('interest_portion');

                    $newInterestToRecognize = max(0, (float) $loanGiven->interest_amount - $alreadyRecognizedInterest);

                    if ($newInterestToRecognize > 0) {
                        $affectedAccountIds = array_merge(
                            $affectedAccountIds,
                            $this->splitInterestFromRolloverPayment($transaction, $newInterestToRecognize, $loanGiven, $payment->id)
                        );
                    }

                    $this->applyReferrerDeduction($loanGiven, ($validated['referrer_deducted_before_deposit'] ?? null) === '1');
                    $closedNow = true;
                }

                DB::commit();
                $paymentAccount->updateBalance();

                foreach (array_unique($affectedAccountIds) as $accId) {
                    if ($accId !== $paymentAccount->id) {
                        Account::find($accId)?->updateBalance();
                    }
                }

                $successMessage = "Repayment of KES " . number_format($paymentAmount, 0) . " from {$loanGiven->borrower_name} recorded into {$paymentAccount->name}!";

                if (!$closedNow) {
                    if ($interestPortion > 0) {
                        $principalPortion = $paymentAmount - $interestPortion;
                        $successMessage .= " KES " . number_format($interestPortion, 0) . " recorded as interest now, "
                            . "KES " . number_format($principalPortion, 0) . " reduced the principal — "
                            . "KES " . number_format($loanGiven->balance, 0) . " remains outstanding.";
                    } else {
                        $successMessage .= " KES " . number_format($loanGiven->balance, 0) . " remains outstanding.";
                    }

                    $successMessage .= " Due date moved to " . $loanGiven->due_date->format('M d, Y') . ".";
                }

                if ($closedNow) {
                    $successMessage .= " Loan closed as fully repaid.";
                    if ($loanGiven->interest_amount > 0) {
                        $successMessage .= " Interest earned: KES " . number_format($loanGiven->interest_amount, 0)
                            . " (" . number_format($loanGiven->interest_rate, 1) . "%).";

                        if ($loanGiven->referrer_id && $loanGiven->referrer_share_percentage !== null) {
                            if ($loanGiven->referrer_deducted_before_deposit) {
                                $successMessage .= " Referrer already kept KES " . number_format($loanGiven->referrer_retained_amount, 0) . " before depositing — nothing further owed.";
                            } else {
                                $referrerPayout = round($loanGiven->interest_amount * ($loanGiven->referrer_share_percentage / 100), 2);
                                $successMessage .= " Referrer's cut (" . number_format($loanGiven->referrer_share_percentage, 1) . "%): KES " . number_format($referrerPayout, 0) . ".";
                            }
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
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Payment failed: ' . $e->getMessage())->withInput();
        }
    }

    // ── new private helper — add alongside splitInterestOutOfFinalPayment() ────

    /**
     * Carves the interest portion out of a rollover (non-final) payment's
     * transaction, into its own income transaction, the moment the payment is
     * recorded — rather than waiting for the loan to eventually close. Mirrors
     * splitInterestOutOfFinalPayment() but is independent of it: it does NOT
     * touch loan_given.interest_amount / interest_rate, since those remain the
     * final, whole-loan figures that closeAsRepaid() computes once, at the end,
     * from the lifetime total.
     */
    private function splitInterestFromRolloverPayment(Transaction $transaction, float $interestAmount, LoanGiven $loanGiven, int $paymentId): array
    {
        $interestAmount = min($interestAmount, (float)$transaction->amount);
        $remainder = round($transaction->amount - $interestAmount, 2);

        if ($remainder <= 0) {
            $transaction->delete();
        } else {
            $transaction->amount = $remainder;
            $transaction->save();
        }

        $interestCategory = $this->firstOrCreateCategory('Loan Interest', 'income');

        Transaction::create([
            'user_id' => Auth::id(),
            'account_id' => $transaction->account_id,
            'category_id' => $interestCategory->id,
            'type' => 'income',
            'description' => "Interest earned from {$loanGiven->borrower_name}'s loan (rollover payment)",
            'amount' => $interestAmount,
            'date' => $transaction->date,
            'reference_id' => $paymentId,
        ]);

        return [$transaction->account_id];
    }
    // ── report ────────────────────────────────────────────────────────────────

    public function report(Request $request)
    {
        try {
            $this->authorize('viewAny', LoanGiven::class);

            $status = $request->get('status', 'active'); // active | paid | all
            $referrerId = $request->get('referrer_id');

            $query = LoanGiven::with('referrer')
                ->where('user_id', Auth::id());

            if ($status === 'active') {
                $query->where('status', 'active');
            } elseif ($status === 'paid') {
                $query->where('status', 'paid');
            }
            // 'all' => no status filter, includes defaulted/written_off too

            if ($referrerId) {
                $query->where('referrer_id', $referrerId);
            }

            $loans = $query->orderBy('due_date')->get();
            foreach ($loans->where('status', 'active') as $loan) {
                $loan->processOverdueRollover();
            }

            $groupedLoans = $loans
                ->groupBy(fn($loan) => $loan->referrer?->name ?? 'No Referrer')
                ->sortKeys();

            $referrers = Referrer::where('is_active', true)->orderBy('name')->get();

            $grandTotalPrincipal = $loans->sum('principal_amount');
            $grandTotalOutstanding = $loans->sum('balance');

            return view('loans-given.report', compact(
                'groupedLoans', 'status', 'referrerId', 'referrers',
                'grandTotalPrincipal', 'grandTotalOutstanding'
            ));

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@report failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('loans-given.index')->with('error', 'Could not generate report: ' . $e->getMessage());
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

            // Always optional now — even when the last payment landed in a
            // referrer float account, leaving this blank just leaves the
            // interest in the float too, trackable via the referrer's float
            // reconciliation page rather than needing to be routed out
            // immediately at closing time.
            $validated = $request->validate([
                'interest_account_id' => 'nullable|exists:accounts,id',
            ]);

            $interestAccount = null;

            if (!empty($validated['interest_account_id'])) {
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
                $this->applyReferrerDeduction($loanGiven, $request->boolean('referrer_deducted_before_deposit'));

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
                'error' => $e->getMessage(),
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
    // ── update notes ─────────────────────────────────────────────────────────

    public function updateNotes(Request $request, LoanGiven $loanGiven)
    {
        try {
            $this->authorize('update', $loanGiven);

            $validated = $request->validate([
                'notes' => 'nullable|string',
            ]);

            $loanGiven->notes = $validated['notes'] ?? null;
            $loanGiven->save();

            return redirect()->route('loans-given.show', $loanGiven->id)
                ->with('success', 'Notes updated.');

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('LoanGivenController@updateNotes failed', ['loan_given_id' => $loanGiven->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Failed to update notes: ' . $e->getMessage());
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
                    $disbursementTransaction = Transaction::find($loanGiven->disbursement_transaction_id);

                    if ($disbursementTransaction) {
                        // The fee transaction (if any) is linked, not automatic —
                        // deleting the loan must clean it up too, or it's left
                        // behind as an orphaned expense with a stale account balance.
                        if ($disbursementTransaction->related_fee_transaction_id) {
                            Transaction::where('id', $disbursementTransaction->related_fee_transaction_id)->forceDelete();
                        }
                        $disbursementTransaction->forceDelete();
                    }
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
            'user_id' => Auth::id(),
            'parent_id' => null,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
        ]);
    }

    private function splitInterestOutOfFinalPayment(LoanGiven $loanGiven, ?Account $interestAccount = null): array
    {
        $interestAmount = (float)$loanGiven->interest_amount;

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

        $interestAmount = min($interestAmount, (float)$transaction->amount);
        $remainder = round($transaction->amount - $interestAmount, 2);

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
            'user_id' => Auth::id(),
            'account_id' => $destinationAccountId,
            'category_id' => $interestCategory->id,
            'type' => 'income',
            'description' => "Interest earned from {$loanGiven->borrower_name}'s loan"
                . ($interestAccount ? " (routed out of {$lastPayment->account->name})" : ''),
            'amount' => $interestAmount,
            'date' => $lastPayment->payment_date,
            'reference_id' => $lastPayment->id,
        ]);

        $affectedAccountIds[] = $destinationAccountId;

        return array_unique($affectedAccountIds);
    }

    private function applyReferrerDeduction(LoanGiven $loanGiven, bool $deductedBeforeDeposit): void
    {
        if (!$deductedBeforeDeposit || !$loanGiven->referrer_id || $loanGiven->referrer_share_percentage === null) {
            return;
        }

        $share = (float)$loanGiven->referrer_share_percentage;

        if ($share <= 0 || $share >= 100) {
            return;
        }

        $interest = (float)$loanGiven->interest_amount;

        if ($interest <= 0) {
            return;
        }

        $retained = round($interest * ($share / (100 - $share)), 2);

        $loanGiven->referrer_deducted_before_deposit = true;
        $loanGiven->referrer_retained_amount = $retained;
        $loanGiven->save();
    }

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
            if ($loanGiven->referrer_deducted_before_deposit) {
                return back()->with('error',
                    'The referrer already deducted and kept their share (KES '
                    . number_format($loanGiven->referrer_retained_amount, 0) . ') on this loan before depositing. '
                    . 'Reversing the interest here wouldn\'t get that money back — it can no longer be reversed.'
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

                $interestAmount = (float)$transaction->amount;

                if ($originalTransaction->trashed()) {
                    // The whole payment was interest — restore it at its full amount.
                    $originalTransaction->restore();
                    $originalTransaction->amount = $payment->amount;
                    $originalTransaction->save();
                } elseif ($originalTransaction->amount < (float)$payment->amount) {
                    // Only part of the payment was split off as interest — add it back.
                    $originalTransaction->amount += $interestAmount;
                    $originalTransaction->save();
                }

                $interestAccountId = $transaction->account_id;
                $transaction->delete();

                // This interest is no longer recognized against this specific
                // payment — whether it came from a rollover split or the final
                // closing split, the payment row is the source of truth
                // updateBalance() reads from. If it isn't corrected here,
                // principal_paid/balance will desync from reality the moment
                // updateBalance() runs below.
                $payment->interest_portion = max(0, (float)$payment->interest_portion - $interestAmount);
                $payment->save();

                // Interest can now come from either closeAsRepaid() (the final,
                // whole-loan figure) or a rollover payment's own interest_portion.
                // Only a 'paid' loan needs reopening — a rollover reversal on an
                // active loan never touched status in the first place.
                if ($loanGiven->status === 'paid') {
                    // interest_amount/interest_rate are closeAsRepaid()'s final
                    // figures — reversing any interest that fed into them means
                    // those numbers are stale until the loan is closed again, so
                    // clear them rather than leave a wrong "final" figure sitting
                    // on a loan that's now active again.
                    $loanGiven->status = 'active';
                    $loanGiven->repaid_date = null;
                    $loanGiven->interest_amount = 0;
                    $loanGiven->interest_rate = 0;
                    $loanGiven->save();
                }

                // Rebuild amount_paid / principal_paid / balance from the payments
                // table now that this payment's interest_portion changed — not
                // hand-adjusted, since those three are only ever authoritative
                // when derived straight from the payments themselves.
                $loanGiven->updateBalance();


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
