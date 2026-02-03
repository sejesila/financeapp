<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller implements HasMiddleware
{
    use AuthorizesRequests;

    public static function middleware(): array
    {
        return ['auth'];
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Loan::class);

        $filter = $request->get('filter', 'active');
        $period = $request->get('period');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $minYear = Loan::where('user_id', Auth::id())->min(DB::raw('YEAR(disbursed_date)'));
        $minYear = $minYear ?? date('Y');
        $maxYear = date('Y');

        // Get active loans (no pagination for active loans - usually small number)
        $activeLoansQuery = Loan::with(['account', 'payments'])
            ->where('user_id', Auth::id())
            ->where('status', 'active');

        $activeLoans = $activeLoansQuery->orderBy('disbursed_date', 'desc')->get();

        // Get paid loans with pagination
        $paidLoansQuery = Loan::with(['account', 'payments'])
            ->where('user_id', Auth::id())
            ->where('status', 'paid');

        // Apply period filtering to paid loans
        if ($period) {
            switch ($period) {
                case 'this_month':
                    $paidLoansQuery->whereMonth('repaid_date', now()->month)
                        ->whereYear('repaid_date', now()->year);
                    break;
                case 'last_month':
                    $lastMonth = now()->subMonth();
                    $paidLoansQuery->whereMonth('repaid_date', $lastMonth->month)
                        ->whereYear('repaid_date', $lastMonth->year);
                    break;
                case 'this_year':
                    $paidLoansQuery->whereYear('repaid_date', now()->year);
                    break;
                case 'last_year':
                    $paidLoansQuery->whereYear('repaid_date', now()->year - 1);
                    break;
                case 'custom':
                    if ($startDate && $endDate) {
                        $paidLoansQuery->whereBetween('repaid_date', [$startDate, $endDate]);
                    }
                    break;
            }
        }

        $paidLoansQuery->orderBy('repaid_date', 'desc')->orderBy('updated_at', 'desc');

        // Paginate paid loans - show 15 per page
        $paidLoans = $paidLoansQuery->paginate(15)->withQueryString();

        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('loans.index', compact(
            'activeLoans',
            'paidLoans',
            'filter',
            'period',
            'startDate',
            'endDate',
            'minYear',
            'maxYear',
            'accounts'
        ));
    }


    public function store(Request $request)
    {
        $this->authorize('create', Loan::class);

        $fromTopup = $request->input('from_topup') === '1';

        $rules = [
            'source' => 'required|string|max:255',
            'account_id' => 'required|exists:accounts,id',
            'principal_amount' => 'required|numeric|min:1',
            'disbursed_date' => 'required|date',
            'due_date' => 'nullable|date|after:disbursed_date',
            'notes' => 'nullable|string',
            'loan_type' => 'required|in:mshwari,kcb_mpesa,other',
            'custom_interest_amount' => 'nullable|numeric|min:0',
        ];

        if ($fromTopup) {
            $rules['from_topup'] = 'required|in:1';
            $rules['original_source'] = 'required|string';
            $rules['original_account_id'] = 'required|exists:accounts,id';
            $rules['original_amount'] = 'required|numeric|min:1';
        }

        $validated = $request->validate($rules);

        $account = Account::findOrFail($validated['account_id']);
        $this->authorize('view', $account);

        if ($fromTopup) {
            $request->validate([
                'source' => 'required|in:' . $request->input('original_source'),
                'account_id' => 'required|in:' . $request->input('original_account_id'),
                'principal_amount' => 'required|in:' . $request->input('original_amount'),
            ], [
                'source.in' => 'Loan source cannot be modified when redirected from top-up.',
                'account_id.in' => 'Receiving account cannot be modified when redirected from top-up.',
                'principal_amount.in' => 'Loan amount cannot be modified when redirected from top-up.',
            ]);
        }

        DB::beginTransaction();

        try {
            $principalAmount = (float)$validated['principal_amount'];
            $loanType = $validated['loan_type'];
            $customInterestAmount = $validated['custom_interest_amount'] ?? 0;

            $breakdown = $this->calculateBreakdown($principalAmount, $loanType, $customInterestAmount);

            $disbursedDate = Carbon::parse($validated['disbursed_date']);

            if ($loanType === 'other') {
                $dueDate = $validated['due_date'];
            } else {
                $dueDate = $validated['due_date'] ?? $disbursedDate->copy()->addDays(30)->format('Y-m-d');
            }

            $loanData = [
                'user_id' => Auth::id(),
                'account_id' => $validated['account_id'],
                'source' => $validated['source'],
                'principal_amount' => $principalAmount,
                'disbursed_date' => $validated['disbursed_date'],
                'due_date' => $dueDate,
                'status' => 'active',
                'notes' => $validated['notes'],
                'loan_type' => $loanType,
                'is_custom' => $loanType === 'other',
            ];

            if ($loanType === 'other') {
                $loanData['custom_interest_amount'] = $customInterestAmount;
                $loanData['interest_rate'] = null;
                $loanData['interest_amount'] = $customInterestAmount;
                $loanData['facility_fee'] = null;
                $loanData['total_amount'] = $breakdown['total_repayment'];
                $loanData['balance'] = $breakdown['total_repayment'];
            } elseif ($loanType === 'kcb_mpesa') {
                $loanData['interest_rate'] = $breakdown['interest_rate'];
                $loanData['interest_amount'] = $breakdown['interest_amount'];
                $loanData['facility_fee'] = $breakdown['facility_fee'];
                $loanData['total_amount'] = $breakdown['total_repayment'];
                $loanData['balance'] = $breakdown['total_repayment'];
            } else {
                $loanData['interest_rate'] = null;
                $loanData['interest_amount'] = null;
                $loanData['facility_fee'] = null;
                $loanData['total_amount'] = $breakdown['standard_repayment'];
                $loanData['balance'] = $breakdown['standard_repayment'];
            }

            $loan = Loan::create($loanData);

            $loanCategory = Category::firstOrCreate(
                ['name' => 'Loan Receipt', 'type' => 'liability', 'user_id' => Auth::id()],
                ['name' => 'Loan Receipt', 'type' => 'liability']
            );

            // ✅ FIX: Only KCB and Custom loans go directly as Loan Receipt
            // M-Shwari loan needs to be split: principal + excise duty
            if ($loanType === 'kcb_mpesa' || $loanType === 'other') {
                // These don't have excise duty, so single transaction for the full principal
                Transaction::create([
                    'user_id' => Auth::id(),
                    'account_id' => $validated['account_id'],
                    'category_id' => $loanCategory->id,
                    'description' => "Loan disbursement from {$validated['source']}",
                    'amount' => $principalAmount,
                    'date' => $validated['disbursed_date'],
                    'type' => 'loan_disbursement',
                    'loan_id' => $loan->id,
                ]);

            } else {
                // ✅ M-SHWARI ONLY: Two separate transactions

                // Transaction 1: Loan Receipt for FULL PRINCIPAL (what you owe)
                Transaction::create([
                    'user_id' => Auth::id(),
                    'account_id' => $validated['account_id'],
                    'category_id' => $loanCategory->id,
                    'description' => "Loan disbursement from {$validated['source']}",
                    'amount' => $principalAmount,  // FULL 1,000 - this is what you owe back
                    'date' => $validated['disbursed_date'],
                    'type' => 'loan_disbursement',
                    'loan_id' => $loan->id,
                ]);

                // Transaction 2: Excise Duty expense (deducted upfront)
                $exciseCategory = Category::firstOrCreate(
                    ['name' => 'Excise Duty', 'type' => 'expense', 'user_id' => Auth::id()],
                    ['name' => 'Excise Duty', 'type' => 'expense']
                );

                Transaction::create([
                    'user_id' => Auth::id(),
                    'account_id' => $validated['account_id'],
                    'category_id' => $exciseCategory->id,
                    'description' => "Excise duty (1.5%) on loan from {$validated['source']}",
                    'amount' => $breakdown['excise_duty'],  // 15
                    'date' => $validated['disbursed_date'],
                    'type' => 'loan_charge',
                    'loan_id' => $loan->id,
                ]);
            }

            DB::commit();

            // Update account balance AFTER transaction is committed
            $account->updateBalance();

            $depositAmount = $breakdown['deposit_amount'];
            $totalRepayment = $breakdown['total_repayment'] ?? $breakdown['standard_repayment'];

            $message = "Loan created successfully! KES " . number_format($depositAmount, 0) . " has been deposited to {$account->name}. ";

            if ($loanType === 'other') {
                if ($customInterestAmount > 0) {
                    $message .= "Total repayment: KES " . number_format($totalRepayment, 0) . " (Principal: " . number_format($principalAmount, 0) . " + Interest: " . number_format($customInterestAmount, 0) . ").";
                } else {
                    $message .= "Total repayment: KES " . number_format($totalRepayment, 0) . " (No interest).";
                }
                if ($dueDate) {
                    $message .= " Due on " . Carbon::parse($dueDate)->format('M d, Y') . ".";
                }
            } else {
                $message .= "Total repayment: KES " . number_format($totalRepayment, 0) . ". ";
                $message .= "💡 Due on " . Carbon::parse($dueDate)->format('M d, Y') . ".";
            }

            return redirect()->route('loans.show', $loan->id)->with('success', $message);

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create loan: ' . $e->getMessage())->withInput();
        }
    }

    public function calculateBreakdown($principalAmount, $loanType = 'mshwari', $customInterestAmount = 0)
    {
        if ($loanType === 'kcb_mpesa') {
            return $this->calculateKcbMpesaBreakdown($principalAmount);
        } elseif ($loanType === 'other') {
            return $this->calculateCustomBreakdown($principalAmount, $customInterestAmount);
        }

        return $this->calculateMshwariBreakdown($principalAmount);
    }

    public function calculateKcbMpesaBreakdown($principalAmount)
    {
        $config = $this->getLoanTypes()['kcb_mpesa'];

        $facilityFee = $principalAmount * $config['facility_fee_rate'];
        $interest = ($principalAmount * $config['interest_rate']) / 100;
        $totalRepayment = $principalAmount + $facilityFee + $interest;
        $earlyRepayment = $principalAmount + $interest;

        return [
            'loan_type' => 'kcb_mpesa',
            'principal' => $principalAmount,
            'facility_fee_rate' => $config['facility_fee_rate'] * 100,
            'facility_fee' => round($facilityFee, 2),
            'interest_rate' => $config['interest_rate'],
            'interest_amount' => round($interest, 2),
            'total_repayment' => round($totalRepayment, 2),
            'early_repayment' => round($earlyRepayment, 2),
            'early_repayment_savings' => round($facilityFee, 2),
            'early_repayment_days' => $config['early_repayment_days'],
            'deposit_amount' => $principalAmount,
        ];
    }

    private function getLoanTypes()
    {
        return [
            'mshwari' => [
                'name' => 'M-Shwari',
                'excise_duty_rate' => 0.015,
                'facilitation_fee_rate' => 0.075,
                'early_repayment_days' => 10,
                'early_repayment_refund_rate' => 0.20,
                'has_early_repayment' => true,
            ],
            'kcb_mpesa' => [
                'name' => 'KCB M-Pesa',
                'facility_fee_rate' => 0.0176,
                'interest_rate' => 7.05,
                'early_repayment_days' => 10,
                'has_early_repayment' => true,
            ],
            'other' => [
                'name' => 'Other Loan Source',
                'is_custom' => true,
                'has_early_repayment' => false,
            ],
        ];
    }

    public function calculateCustomBreakdown($principalAmount, $interestAmount = 0)
    {
        $totalRepayment = $principalAmount + $interestAmount;

        return [
            'loan_type' => 'other',
            'principal' => $principalAmount,
            'interest_amount' => round($interestAmount, 2),
            'total_repayment' => round($totalRepayment, 2),
            'deposit_amount' => $principalAmount,
        ];
    }

    public function calculateMshwariBreakdown($principalAmount)
    {
        $config = $this->getLoanTypes()['mshwari'];

        $exciseDuty = $principalAmount * $config['excise_duty_rate'];
        $depositAmount = $principalAmount - $exciseDuty;
        $facilitationFee = $principalAmount * $config['facilitation_fee_rate'];
        $standardRepayment = $principalAmount + $facilitationFee;
        $totalLoanFees = $exciseDuty + $facilitationFee;
        $earlyRepaymentDiscount = $totalLoanFees * $config['early_repayment_refund_rate'];
        $earlyRepayment = $standardRepayment - $earlyRepaymentDiscount;

        return [
            'loan_type' => 'mshwari',
            'principal' => $principalAmount,
            'excise_duty' => round($exciseDuty, 2),
            'deposit_amount' => round($depositAmount, 2),
            'facilitation_fee' => round($facilitationFee, 2),
            'total_loan_fees' => round($totalLoanFees, 2),
            'standard_repayment' => round($standardRepayment, 2),
            'early_repayment' => round($earlyRepayment, 2),
            'early_repayment_discount' => round($earlyRepaymentDiscount, 2),
            'early_repayment_days' => $config['early_repayment_days'],
        ];
    }

    public function create(Request $request)
    {
        $this->authorize('create', Loan::class);

        // Get accounts - Allow mpesa, airtel_money for M-Shwari/KCB, and ALL active accounts for 'other' loans
        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $fromTopup = $request->has('account_id') && $request->has('amount') && $request->has('source');

        $prefillData = [
            'account_id' => $request->query('account_id'),
            'amount' => $request->query('amount'),
            'source' => $request->query('source'),
            'date' => $request->query('date'),
            'notes' => $request->query('notes'),
        ];

        $loanType = $this->detectLoanType($prefillData['source'] ?? '');
        $loanTypes = $this->getLoanTypes();

        return view('loans.create', compact('accounts', 'fromTopup', 'prefillData', 'loanType', 'loanTypes'));
    }

    private function detectLoanType($source)
    {
        $source = strtolower($source);

        if (strpos($source, 'kcb') !== false || strpos($source, 'kcb-mpesa') !== false) {
            return 'kcb_mpesa';
        }

        if (strpos($source, 'mshwari') !== false || strpos($source, 'm-shwari') !== false) {
            return 'mshwari';
        }

        return 'other';
    }

    /**
     * Show loan details
     */
    public function show(Loan $loan)
    {
        $this->authorize('view', $loan);

        $loan->load(['account', 'payments']);

        $repayment = $this->calculateTotalRepayment(
            $loan->principal_amount,
            $loan->interest_rate,
            $loan->interest_amount
        );

        $daysElapsed = now()->diffInDays($loan->disbursed_date);
        $daysRemaining = $loan->due_date ? $loan->due_date->diffInDays(now()) : null;
        $isOverdue = $loan->due_date && now()->isAfter($loan->due_date) && $loan->status === 'active';

        return view('loans.show', compact('loan', 'repayment', 'daysElapsed', 'daysRemaining', 'isOverdue'));
    }

    private function calculateTotalRepayment($principalAmount, $interestRate = null, $interestAmount = null)
    {
        if ($interestRate !== null && $interestRate > 0) {
            $calculatedInterest = ($principalAmount * $interestRate) / 100;
            return [
                'interest' => round($calculatedInterest, 2),
                'total' => round($principalAmount + $calculatedInterest, 2)
            ];
        } elseif ($interestAmount !== null && $interestAmount > 0) {
            return [
                'interest' => round($interestAmount, 2),
                'total' => round($principalAmount + $interestAmount, 2)
            ];
        }

        return [
            'interest' => 0,
            'total' => $principalAmount
        ];
    }

    /**
     * Show repayment form
     */
    /**
     * Show repayment form
     */
    /**
     * Show repayment form
     */
    public function paymentForm(Loan $loan)
    {
        $this->authorize('makePayment', $loan);

        if ($loan->status !== 'active') {
            return back()->with('error', 'Only active loans can be repaid');
        }

        $repayment = $this->calculateTotalRepayment(
            $loan->principal_amount,
            $loan->interest_rate,
            $loan->interest_amount
        );

        // Determine loan type
        $loanType = $loan->loan_type ?? $this->detectLoanType($loan->source);

        // Get accounts based on loan type - NO MINIMUM BALANCE CHECK
        $accountsQuery = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('type', '!=', 'savings');

        // For M-Shwari and KCB M-Pesa loans, only allow mobile money accounts
        if ($loanType === 'mshwari' || $loanType === 'kcb_mpesa') {
            $accountsQuery->where('type', 'mpesa');
        }
        // For other loans, allow mobile_money, bank, and cash accounts
        else {
            $accountsQuery->whereIn('type', ['mobile_money', 'bank', 'cash']);
        }

        $accounts = $accountsQuery->orderBy('name')->get();

        $minRequiredBalance = $loan->balance * 0.25;

        return view('loans.payment', compact('loan', 'repayment', 'accounts', 'minRequiredBalance', 'loanType'));
    }


    /**
     * Record loan payment with early repayment credit (within 10 days)
     */
    /**
     * Record loan payment with early repayment credit (within 10 days)
     * Updated to support payment from any account
     */


    /**
     * Show loan edit form
     */
    public function edit(Loan $loan)
    {
        $this->authorize('update', $loan);

        if ($loan->status !== 'active') {
            return back()->with('error', 'Cannot edit non-active loans');
        }

        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereIn('type', ['cash', 'mobile_money'])
            ->get();

        return view('loans.edit', compact('loan', 'accounts'));
    }

    /**
     * Update loan
     */
    public function update(Request $request, Loan $loan)
    {
        $this->authorize('update', $loan);

        if ($loan->status !== 'active') {
            return back()->with('error', 'Cannot edit non-active loans');
        }

        $validated = $request->validate([
            'source' => 'required|string|max:255',
            'due_date' => 'nullable|date|after:disbursed_date',
            'notes' => 'nullable|string',
        ]);

        $loan->update($validated);

        return redirect()->route('loans.show', $loan->id)
            ->with('success', 'Loan updated successfully');
    }

    /**
     * Delete loan (only active loans with no payments)
     */
    /**
     * Record loan payment with early repayment credit (within 10 days)
     * Updated to support payment from any account
     */
    public function recordPayment(Request $request, Loan $loan)
    {
        $this->authorize('makePayment', $loan);

        if ($loan->status !== 'active') {
            return back()->with('error', 'Only active loans can be repaid');
        }

        $validated = $request->validate([
            'payment_account_id' => 'required|exists:accounts,id',
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date|before_or_equal:today',
            'principal_portion' => 'nullable|numeric|min:0',
            'interest_portion' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $paymentAmount = (float)$validated['payment_amount'];
            $paymentDate = $validated['payment_date'];

            // Get the account to pay from
            $paymentAccount = Account::findOrFail($validated['payment_account_id']);

            if ($paymentAccount->user_id !== Auth::id()) {
                throw new Exception("Unauthorized access to this account.");
            }

            if ($paymentAccount->current_balance < $paymentAmount) {
                throw new Exception("Insufficient balance in {$paymentAccount->name}. Required: KES " . number_format($paymentAmount, 0) . ", Available: KES " . number_format($paymentAccount->current_balance, 0));
            }

            $repayment = $this->calculateTotalRepayment(
                $loan->principal_amount,
                $loan->interest_rate,
                $loan->interest_amount
            );

            $principalPortion = $validated['principal_portion'] ? (float)$validated['principal_portion'] : 0;
            $interestPortion = $validated['interest_portion'] ? (float)$validated['interest_portion'] : 0;

            if ($principalPortion == 0 && $interestPortion == 0) {
                $remainingInterest = $repayment['interest'] - (LoanPayment::where('loan_id', $loan->id)->sum('interest_portion') ?? 0);
                $interestPortion = min($paymentAmount, $remainingInterest);
                $principalPortion = $paymentAmount - $interestPortion;
            }

            $repaymentCategory = Category::firstOrCreate(
                ['name' => 'Loan Repayment', 'type' => 'expense', 'user_id' => Auth::id()],
                ['name' => 'Loan Repayment', 'type' => 'expense']
            );

            // Create transaction from payment account
            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'account_id' => $paymentAccount->id,
                'category_id' => $repaymentCategory->id,
                'description' => "Loan repayment to {$loan->source}",
                'amount' => $paymentAmount,
                'date' => $paymentDate,
                'type' => 'loan_payment',
                'loan_id' => $loan->id,
            ]);

            // Record loan payment
            LoanPayment::create([
                'user_id' => Auth::id(),
                'loan_id' => $loan->id,
                'account_id' => $paymentAccount->id,
                'amount' => $paymentAmount,
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestPortion,
                'payment_date' => $paymentDate,
                'transaction_id' => $transaction->id,
                'notes' => $validated['notes'],
            ]);

            $loan->amount_paid += $paymentAmount;
            $loan->balance = $loan->total_amount - $loan->amount_paid;

            if ($loan->balance <= 0) {
                $loan->status = 'paid';
                $loan->repaid_date = $paymentDate;
            }

            $loan->save();

            $earlyRepaymentCredit = 0;
            $loanType = $loan->loan_type ?? $this->detectLoanType($loan->source);

            // Handle early repayment credit (goes to loan's original account)
            if ($loan->balance <= 0) {
                $daysElapsed = Carbon::parse($loan->disbursed_date)->diffInDays(Carbon::parse($paymentDate));

                if ($loanType === 'mshwari' && $daysElapsed <= 10) {
                    $breakdown = $this->calculateMshwariBreakdown($loan->principal_amount);
                    $earlyRepaymentCredit = $breakdown['early_repayment_discount'];

                    $loanFeesCategory = Category::firstOrCreate(
                        ['name' => 'Loan Fees Refund', 'type' => 'income', 'user_id' => Auth::id()],
                        ['name' => 'Loan Fees Refund', 'type' => 'income']
                    );

                    Transaction::create([
                        'user_id' => Auth::id(),
                        'account_id' => $loan->account_id,
                        'category_id' => $loanFeesCategory->id,
                        'description' => "Early repayment credit - 20% of loan fees refunded (Repaid in {$daysElapsed} days from {$loan->source})",
                        'amount' => $earlyRepaymentCredit,
                        'date' => $paymentDate,
                        'type' => 'loan_credit',
                        'loan_id' => $loan->id,
                    ]);

                } elseif ($loanType === 'kcb_mpesa' && $daysElapsed <= 10) {
                    $earlyRepaymentCredit = $loan->facility_fee ?? (($loan->principal_amount * 0.0176));

                    $facilityFeeCategory = Category::firstOrCreate(
                        ['name' => 'Facility Fee Refund', 'type' => 'income', 'user_id' => Auth::id()],
                        ['name' => 'Facility Fee Refund', 'type' => 'income']
                    );

                    Transaction::create([
                        'user_id' => Auth::id(),
                        'account_id' => $loan->account_id,
                        'category_id' => $facilityFeeCategory->id,
                        'description' => "Early repayment cashback - Facility fee refunded (Repaid in {$daysElapsed} days from {$loan->source})",
                        'amount' => $earlyRepaymentCredit,
                        'date' => $paymentDate,
                        'type' => 'loan_credit',
                        'loan_id' => $loan->id,
                    ]);
                }
            }

            DB::commit();

            // Update both accounts AFTER commit
            $paymentAccount->updateBalance();
            $loanAccount = Account::find($loan->account_id);
            $loanAccount->updateBalance();

            $successMessage = "Payment of KES " . number_format($paymentAmount, 0) . " recorded successfully from {$paymentAccount->name}!";

            if ($earlyRepaymentCredit > 0) {
                $daysElapsed = Carbon::parse($loan->disbursed_date)->diffInDays(Carbon::parse($paymentDate));
                $creditAccountName = Account::find($loan->account_id)->name;

                if ($loanType === 'kcb_mpesa') {
                    $successMessage .= " 🎉 You received an early repayment cashback of KES " . number_format($earlyRepaymentCredit, 0) . " (facility fee refunded) to {$creditAccountName} for repaying within {$daysElapsed} days!";
                } else {
                    $successMessage .= " 🎉 You received an early repayment credit of KES " . number_format($earlyRepaymentCredit, 0) . " (20% of loan fees refunded) to {$creditAccountName} for repaying within {$daysElapsed} days!";
                }
            }

            return redirect()->route('loans.show', $loan->id)
                ->with('success', $successMessage);

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Payment failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Delete loan (only active loans with no payments)
     */
    public function destroy(Loan $loan)
    {
        $this->authorize('delete', $loan);

        if ($loan->status !== 'active') {
            return back()->with('error', 'Cannot delete non-active loans');
        }

        if ($loan->amount_paid > 0) {
            return back()->with('error', 'Cannot delete loans that have been partially or fully paid');
        }

        DB::beginTransaction();

        try {
            // Delete all transactions associated with this loan
            Transaction::where('loan_id', $loan->id)->delete();

            // Delete the loan
            $loan->delete();

            DB::commit();

            // Update the account balance AFTER commit
            $account = Account::find($loan->account_id);
            $account->updateBalance();

            return redirect()->route('loans.index')
                ->with('success', 'Loan deleted successfully');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete loan: ' . $e->getMessage());
        }
    }
}
