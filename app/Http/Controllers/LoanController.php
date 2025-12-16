<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    /**
     * Loan type configurations
     */
    private function getLoanTypes()
    {
        return [
            'mshwari' => [
                'name' => 'M-Shwari',
                'excise_duty_rate' => 0.015, // 1.5%
                'facilitation_fee_rate' => 0.075, // 7.5%
                'early_repayment_days' => 10,
                'early_repayment_refund_rate' => 0.24, // 24% of facilitation fee
                'has_early_repayment' => true,
            ],
            'kcb_mpesa' => [
                'name' => 'KCB M-Pesa',
                'interest_rate' => 8.93, // 8.93%
                'has_early_repayment' => false,
            ],
        ];
    }

    /**
     * Detect loan type from source name
     */
    private function detectLoanType($source)
    {
        $source = strtolower($source);

        if (strpos($source, 'kcb') !== false || strpos($source, 'kcb-mpesa') !== false) {
            return 'kcb_mpesa';
        }

        if (strpos($source, 'mshwari') !== false || strpos($source, 'm-shwari') !== false) {
            return 'mshwari';
        }

        // Default to M-Shwari for other sources
        return 'mshwari';
    }

    /**
     * Calculate total repayment amount
     */
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
     * Display all loans with filtering options
     */
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'active');
        $year = $request->get('year');

        // Calculate dynamic year range based on actual loan data
        $minYear = Loan::min(DB::raw('YEAR(disbursed_date)'));
        $minYear = $minYear ?? date('Y');
        $maxYear = date('Y');

        // Active loans query
        $activeLoansQuery = Loan::with('account')->where('status', 'active');

        // Apply year filter to active loans if specified
        if ($year && $filter === 'active') {
            $activeLoansQuery->whereYear('disbursed_date', $year);
        }

        $activeLoans = $activeLoansQuery->orderBy('disbursed_date', 'desc')->get();

        // Paid loans query with filtering
        $paidLoansQuery = Loan::with('account')->where('status', 'paid');

        // Apply filters to paid loans
        if ($year) {
            $paidLoansQuery->whereYear('repaid_date', $year);
        }

        // Determine limit based on filter
        $limit = ($filter === 'all_paid' || $year) ? null : 10;

        if ($limit) {
            $paidLoansQuery->limit($limit);
        }

        $paidLoans = $paidLoansQuery
            ->orderBy('repaid_date', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('loans.index', compact(
            'activeLoans',
            'paidLoans',
            'filter',
            'year',
            'minYear',
            'maxYear'
        ));
    }

    /**
     * Show create loan form
     */
    public function create(Request $request)
    {
        // Show all mobile money accounts (mpesa, airtel_money)
        $accounts = Account::where('is_active', true)
            ->whereIn('type', ['mpesa', 'airtel_money'])
            ->orderBy('name')
            ->get();

        // Check if this is a redirect from top-up (has from_topup flag)
        $fromTopup = $request->has('account_id') && $request->has('amount') && $request->has('source');

        // Pre-fill data from query parameters
        $prefillData = [
            'account_id' => $request->query('account_id'),
            'amount' => $request->query('amount'),
            'source' => $request->query('source'),
            'date' => $request->query('date'),
            'notes' => $request->query('notes'),
        ];

        // Detect loan type
        $loanType = $this->detectLoanType($prefillData['source'] ?? '');
        $loanTypes = $this->getLoanTypes();

        return view('loans.create', compact('accounts', 'fromTopup', 'prefillData', 'loanType', 'loanTypes'));
    }

    /**
     * Calculate detailed loan breakdown for M-Shwari
     */
    public function calculateMshwariBreakdown($principalAmount)
    {
        $config = $this->getLoanTypes()['mshwari'];

        $exciseDuty = $principalAmount * $config['excise_duty_rate'];
        $depositAmount = $principalAmount - $exciseDuty;
        $facilitationFee = $principalAmount * $config['facilitation_fee_rate'];
        $standardRepayment = $principalAmount + $facilitationFee;
        $earlyRepaymentDiscount = $facilitationFee * $config['early_repayment_refund_rate'];
        $earlyRepayment = $standardRepayment - $earlyRepaymentDiscount;

        return [
            'loan_type' => 'mshwari',
            'principal' => $principalAmount,
            'excise_duty' => round($exciseDuty, 2),
            'deposit_amount' => round($depositAmount, 2),
            'facilitation_fee' => round($facilitationFee, 2),
            'standard_repayment' => round($standardRepayment, 2),
            'early_repayment' => round($earlyRepayment, 2),
            'early_repayment_discount' => round($earlyRepaymentDiscount, 2),
            'early_repayment_days' => $config['early_repayment_days'],
        ];
    }

    /**
     * Calculate detailed loan breakdown for KCB M-Pesa
     */
    public function calculateKcbMpesaBreakdown($principalAmount)
    {
        $config = $this->getLoanTypes()['kcb_mpesa'];

        $interest = ($principalAmount * $config['interest_rate']) / 100;
        $totalRepayment = $principalAmount + $interest;

        return [
            'loan_type' => 'kcb_mpesa',
            'principal' => $principalAmount,
            'interest_rate' => $config['interest_rate'],
            'interest_amount' => round($interest, 2),
            'total_repayment' => round($totalRepayment, 2),
            'deposit_amount' => $principalAmount, // Full amount deposited
        ];
    }

    /**
     * Calculate breakdown based on loan type
     */
    public function calculateBreakdown($principalAmount, $loanType = 'mshwari')
    {
        if ($loanType === 'kcb_mpesa') {
            return $this->calculateKcbMpesaBreakdown($principalAmount);
        }

        return $this->calculateMshwariBreakdown($principalAmount);
    }

    /**
     * Store a new loan
     */
    public function store(Request $request)
    {
        // Check if this is from top-up redirect (immutable fields)
        $fromTopup = $request->input('from_topup') === '1';

        $rules = [
            'source' => 'required|string|max:255',
            'account_id' => 'required|exists:accounts,id',
            'principal_amount' => 'required|numeric|min:1',
            'disbursed_date' => 'required|date',
            'due_date' => 'nullable|date|after:disbursed_date',
            'notes' => 'nullable|string',
            'loan_type' => 'required|in:mshwari,kcb_mpesa',
        ];

        // If from top-up, add additional validation to ensure fields weren't tampered with
        if ($fromTopup) {
            $rules['from_topup'] = 'required|in:1';
            $rules['original_source'] = 'required|string';
            $rules['original_account_id'] = 'required|exists:accounts,id';
            $rules['original_amount'] = 'required|numeric|min:1';

            // Validate that the values match the originals
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

        $validated = $request->validate($rules);

        DB::beginTransaction();

        try {
            $principalAmount = (float) $validated['principal_amount'];
            $loanType = $validated['loan_type'];

            // Calculate breakdown based on loan type
            $breakdown = $this->calculateBreakdown($principalAmount, $loanType);

            // Create loan record with calculated amounts
            if ($loanType === 'kcb_mpesa') {
                $loan = Loan::create([
                    'account_id' => $validated['account_id'],
                    'source' => $validated['source'],
                    'principal_amount' => $principalAmount,
                    'interest_rate' => $breakdown['interest_rate'],
                    'interest_amount' => $breakdown['interest_amount'],
                    'total_amount' => $breakdown['total_repayment'],
                    'balance' => $breakdown['total_repayment'],
                    'disbursed_date' => $validated['disbursed_date'],
                    'due_date' => $validated['due_date'],
                    'status' => 'active',
                    'notes' => $validated['notes'],
                    'loan_type' => 'kcb_mpesa',
                ]);
            } else {
                $loan = Loan::create([
                    'account_id' => $validated['account_id'],
                    'source' => $validated['source'],
                    'principal_amount' => $principalAmount,
                    'interest_rate' => null,
                    'interest_amount' => null,
                    'total_amount' => $breakdown['standard_repayment'],
                    'balance' => $breakdown['standard_repayment'],
                    'disbursed_date' => $validated['disbursed_date'],
                    'due_date' => $validated['due_date'],
                    'status' => 'active',
                    'notes' => $validated['notes'],
                    'loan_type' => 'mshwari',
                ]);
            }

            // Get or create loan receipt category
            $loanCategory = Category::firstOrCreate(
                ['name' => 'Loan Receipt', 'type' => 'income']
            );

            $account = Account::find($validated['account_id']);

            if ($loanType === 'kcb_mpesa') {
                // KCB M-Pesa: Full amount deposited
                Transaction::create([
                    'account_id' => $validated['account_id'],
                    'category_id' => $loanCategory->id,
                    'description' => "Loan disbursement from {$validated['source']}",
                    'amount' => $principalAmount,
                    'date' => $validated['disbursed_date'],
                    'type' => 'loan_disbursement',
                    'loan_id' => $loan->id,
                ]);

                $account->current_balance += $principalAmount;

            } else {
                // M-Shwari: Deposit after excise duty deduction
                $depositAfterExcise = $breakdown['deposit_amount'];

                Transaction::create([
                    'account_id' => $validated['account_id'],
                    'category_id' => $loanCategory->id,
                    'description' => "Loan disbursement from {$validated['source']}",
                    'amount' => $depositAfterExcise,
                    'date' => $validated['disbursed_date'],
                    'type' => 'loan_disbursement',
                    'loan_id' => $loan->id,
                ]);

                // Record excise duty as expense
                $exciseCategory = Category::firstOrCreate(
                    ['name' => 'Excise Duty', 'type' => 'expense']
                );

                Transaction::create([
                    'account_id' => $validated['account_id'],
                    'category_id' => $exciseCategory->id,
                    'description' => "Excise duty (1.5%) on loan from {$validated['source']}",
                    'amount' => $breakdown['excise_duty'],
                    'date' => $validated['disbursed_date'],
                    'type' => 'loan_charge',
                    'loan_id' => $loan->id,
                ]);

                $account->current_balance += $depositAfterExcise;
            }

            $account->save();

            DB::commit();

            $depositAmount = $breakdown['deposit_amount'];
            $totalRepayment = $loanType === 'kcb_mpesa' ? $breakdown['total_repayment'] : $breakdown['standard_repayment'];

            return redirect()->route('loans.show', $loan->id)
                ->with('success', "Loan created successfully! KES " . number_format($depositAmount, 0) . " has been deposited to {$account->name}. Total repayment: KES " . number_format($totalRepayment, 0));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create loan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show loan details
     */
    public function show(Loan $loan)
    {
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

    /**
     * Show repayment form
     */
    public function paymentForm(Loan $loan)
    {
        if ($loan->status !== 'active') {
            return back()->with('error', 'Only active loans can be repaid');
        }

        $repayment = $this->calculateTotalRepayment(
            $loan->principal_amount,
            $loan->interest_rate,
            $loan->interest_amount
        );

        return view('loans.payment', compact('loan', 'repayment'));
    }

    /**
     * Record loan payment with early repayment credit for M-Shwari only
     */
    public function recordPayment(Request $request, Loan $loan)
    {
        if ($loan->status !== 'active') {
            return back()->with('error', 'Only active loans can be repaid');
        }

        $validated = $request->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date|before_or_equal:today',
            'principal_portion' => 'nullable|numeric|min:0',
            'interest_portion' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $paymentAmount = (float) $validated['payment_amount'];
            $paymentDate = $validated['payment_date'];
            $account = Account::find($loan->account_id);

            // Check account balance
            if ($account->current_balance < $paymentAmount) {
                throw new \Exception("Insufficient balance. Required: KES " . number_format($paymentAmount, 0) . ", Available: KES " . number_format($account->current_balance, 0));
            }

            // Calculate principal and interest portions
            $repayment = $this->calculateTotalRepayment(
                $loan->principal_amount,
                $loan->interest_rate,
                $loan->interest_amount
            );

            $principalPortion = $validated['principal_portion'] ? (float) $validated['principal_portion'] : 0;
            $interestPortion = $validated['interest_portion'] ? (float) $validated['interest_portion'] : 0;

            // If portions not specified, allocate: interest first, then principal
            if ($principalPortion == 0 && $interestPortion == 0) {
                $remainingInterest = $repayment['interest'] - (LoanPayment::where('loan_id', $loan->id)->sum('interest_portion') ?? 0);
                $interestPortion = min($paymentAmount, $remainingInterest);
                $principalPortion = $paymentAmount - $interestPortion;
            }

            // Get or create loan repayment category
            $repaymentCategory = Category::firstOrCreate(
                ['name' => 'Loan Repayment', 'type' => 'expense']
            );

            // Record payment transaction
            $transaction = Transaction::create([
                'account_id' => $loan->account_id,
                'category_id' => $repaymentCategory->id,
                'description' => "Loan repayment to {$loan->source}",
                'amount' => $paymentAmount,
                'date' => $paymentDate,
                'type' => 'loan_payment',
                'loan_id' => $loan->id,
            ]);

            // Create loan payment record
            LoanPayment::create([
                'loan_id' => $loan->id,
                'account_id' => $loan->account_id,
                'amount' => $paymentAmount,
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestPortion,
                'payment_date' => $paymentDate,
                'transaction_id' => $transaction->id,
                'notes' => $validated['notes'],
            ]);

            // Update loan balance and status
            $loan->amount_paid += $paymentAmount;
            $loan->balance = $loan->total_amount - $loan->amount_paid;

            if ($loan->balance <= 0) {
                $loan->status = 'paid';
                $loan->repaid_date = $paymentDate;
            }

            $loan->save();

            // Update account balance (deduct payment)
            $account->current_balance -= $paymentAmount;

            // Handle early repayment credit ONLY for M-Shwari loans
            $earlyRepaymentCredit = 0;
            $loanType = $loan->loan_type ?? $this->detectLoanType($loan->source);

            if ($loanType === 'mshwari' && $loan->balance <= 0) {
                $daysElapsed = \Carbon\Carbon::parse($loan->disbursed_date)->diffInDays(\Carbon\Carbon::parse($paymentDate));

                if ($daysElapsed <= 10) {
                    $breakdown = $this->calculateMshwariBreakdown($loan->principal_amount);
                    $totalLoanFees = $breakdown['excise_duty'] + $breakdown['facilitation_fee'];
                    $earlyRepaymentCredit = $totalLoanFees * 0.20;

                    $loanFeesCategory = Category::firstOrCreate(
                        ['name' => 'Loan Fees', 'type' => 'income']
                    );

                    Transaction::create([
                        'account_id' => $loan->account_id,
                        'category_id' => $loanFeesCategory->id,
                        'description' => "Early repayment credit - 20% of loan fees waived (Loan from {$loan->source})",
                        'amount' => $earlyRepaymentCredit,
                        'date' => $paymentDate,
                        'type' => 'loan_credit',
                        'loan_id' => $loan->id,
                    ]);

                    $account->current_balance += $earlyRepaymentCredit;
                }
            }

            $account->save();

            DB::commit();

            $successMessage = "Payment of KES " . number_format($paymentAmount, 0) . " recorded successfully!";

            if ($earlyRepaymentCredit > 0) {
                $successMessage .= " You received an early repayment credit of KES " . number_format($earlyRepaymentCredit, 0) . " (20% of loan fees waived).";
            }

            return redirect()->route('loans.show', $loan->id)
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Payment failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show loan edit form
     */
    public function edit(Loan $loan)
    {
        if ($loan->status !== 'active') {
            return back()->with('error', 'Cannot edit non-active loans');
        }

        $accounts = Account::where('is_active', true)
            ->whereIn('type', ['cash', 'mobile_money'])
            ->get();

        return view('loans.edit', compact('loan', 'accounts'));
    }

    /**
     * Update loan
     */
    public function update(Request $request, Loan $loan)
    {
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
    public function destroy(Loan $loan)
    {
        if ($loan->status !== 'active') {
            return back()->with('error', 'Cannot delete non-active loans');
        }

        if ($loan->amount_paid > 0) {
            return back()->with('error', 'Cannot delete loans that have been partially or fully paid');
        }

        DB::beginTransaction();

        try {
            // Delete related transactions
            Transaction::where('loan_id', $loan->id)->delete();

            // Update account balance to remove the loan amount
            $account = Account::find($loan->account_id);
            $loanType = $loan->loan_type ?? $this->detectLoanType($loan->source);

            if ($loanType === 'kcb_mpesa') {
                $account->current_balance -= $loan->principal_amount;
            } else {
                $breakdown = $this->calculateMshwariBreakdown($loan->principal_amount);
                $account->current_balance -= $breakdown['deposit_amount'];
            }

            $account->save();

            // Delete loan
            $loan->delete();

            DB::commit();

            return redirect()->route('loans.index')
                ->with('success', 'Loan deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete loan: ' . $e->getMessage());
        }
    }
}
