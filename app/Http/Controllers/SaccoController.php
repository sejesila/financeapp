<?php

namespace App\Http\Controllers;

use App\Models\Sacco;
use App\Models\SaccoEntry;
use App\Models\SaccoLoan;
use App\Models\SaccoLoanPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaccoController extends Controller
{
    public const REMOVE_WINDOW_MINUTES = 5;

    public function index()
    {
        $sacco = Sacco::first();

        if (! $sacco) {
            return view('sacco.index', ['sacco' => null]);
        }

        $loans = $sacco->loans()->with('payments')
            ->orderByRaw("status = 'active' desc")
            ->orderByDesc('disbursed_date')
            ->orderByDesc('id')
            ->get();

        return view('sacco.index', [
            'sacco'          => $sacco,
            'balances'       => collect(array_keys(Sacco::LEDGERS))->mapWithKeys(fn ($l) => [$l => $sacco->ledgerBalance($l)])->all(),
            'outstanding'    => $sacco->loansOutstanding(),
            'net'            => $sacco->netPosition(),
            'borrowingPower' => $sacco->borrowingPower(),
            'loans'          => $loans,
            'canAddLoan'     => ! $loans->contains(fn ($l) => $l->status === 'active'),
            'withdrawalOpen' => Sacco::withdrawalWindowOpen(),
            'entries'        => $sacco->entries()->orderByDesc('period_date')->orderByDesc('id')->limit(20)->get(),
            'recordable'     => $sacco->recordableMonth(),
            'dividendYear'   => $sacco->pendingDividendYear(),
            'recordedMonths' => $sacco->entries()->where('kind', 'contribution')->pluck('slot')
                ->map(fn ($s) => substr($s, -7))->unique()->values()->all(),
        ]);
    }

    public function store(Request $request)
    {
        if (Sacco::exists()) {
            return redirect()->route('sacco.index')->with('error', 'Your sacco is already set up.');
        }

        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'opening_date'       => 'required|date',
            'opening_shares'     => 'required|numeric|min:0',
            'opening_deposits'   => 'required|numeric|min:0',
            'opening_wallet'     => 'required|numeric|min:0',
            'borrowing_multiple' => 'required|numeric|min:0|max:10',
        ]);

        Sacco::create($data + ['user_id' => Auth::id()]);

        return redirect()->route('sacco.index')->with('success', 'Sacco set up.');
    }

    // ── Monthly payroll deposits ──────────────────────────────────────────────

    public function storeContribution(Request $request)
    {
        $sacco = Sacco::firstOrFail();

        $request->validate([
            'month'    => 'required|date_format:Y-m',
            'deposits' => 'required|numeric|min:0.01',
        ]);

        $period = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();

        if ($period->gte(now()->startOfMonth())) {
            return back()->withInput()->with('error', 'You can only record months that have ended. The latest is '
                . now()->subMonthNoOverflow()->format('F Y') . '.');
        }

        if ($period->lte($sacco->opening_date->copy()->startOfMonth())) {
            return back()->withInput()->with('error', 'Your opening balance (as at '
                . $sacco->opening_date->format('M d, Y') . ') already includes ' . $period->format('F Y') . '.');
        }

        $saved = 0;
        $locked = 0;

        DB::transaction(function () use ($request, $sacco, $period, &$saved, &$locked) {
            foreach (['deposits'] as $ledger) {
                $amount = (float) $request->input($ledger, 0);
                if ($amount <= 0) {
                    continue;
                }

                $slot = "$ledger:contribution:" . $period->format('Y-m');
                if ($sacco->entries()->where('slot', $slot)->exists()) {
                    $locked++;
                    continue;
                }

                SaccoEntry::create([
                    'sacco_id'    => $sacco->id,
                    'user_id'     => Auth::id(),
                    'ledger'      => $ledger,
                    'kind'        => 'contribution',
                    'period_date' => $period->copy()->endOfMonth()->toDateString(),
                    'amount'      => $amount,
                    'description' => 'Payroll ' . ($ledger === 'shares' ? 'shares' : 'deposit') . ' ' . $period->format('M Y'),
                    'slot'        => $slot,
                ]);
                $saved++;
            }
        });

        if ($saved === 0) {
            return back()->withInput()->with('error', $locked > 0
                ? $period->format('F Y') . ' is already recorded. Remove it within ' . self::REMOVE_WINDOW_MINUTES . ' minutes of saving to re-enter it.'
                : 'Enter at least one amount.');
        }

        return back()->with('success', 'Saved ' . $period->format('F Y') . '.');
    }

    // ── Yearly dividend / interest ────────────────────────────────────────────

    public function storeDividend(Request $request)
    {
        $sacco = Sacco::firstOrFail();
        $year  = $sacco->pendingDividendYear();

        if ($year === null) {
            return back()->with('error', 'No dividend is due to be recorded right now.');
        }

        $data = $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'credited_to' => 'required|in:' . implode(',', array_keys(Sacco::LEDGERS)),
            'date'        => 'required|date|before_or_equal:today',
        ]);

        SaccoEntry::create([
            'sacco_id'    => $sacco->id,
            'user_id'     => Auth::id(),
            'ledger'      => $data['credited_to'],
            'kind'        => 'dividend',
            'period_date' => $data['date'],
            'amount'      => $data['amount'],
            'description' => "Dividend / interest $year",
            'slot'        => "dividend:$year",
        ]);

        return back()->with('success', "Dividend for $year recorded.");
    }

    // ── Wallet withdrawal (15 March – 30 April) ───────────────────────────────

    public function storeWithdrawal(Request $request)
    {
        $sacco = Sacco::firstOrFail();

        if (! Sacco::withdrawalWindowOpen()) {
            return back()->with('error', 'Wallet withdrawals are only available from 15 March to 30 April.');
        }

        $data = $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'charges'     => 'nullable|numeric|min:0',
            'date'        => 'required|date|before_or_equal:today',
            'description' => 'nullable|string|max:100',
        ]);

        $charges = (float) ($data['charges'] ?? 0);
        $total   = (float) $data['amount'] + $charges;

        if ($total > $sacco->ledgerBalance('wallet')) {
            return back()->withInput()->with('error', 'Not enough in the wallet. Available: KES '
                . number_format($sacco->ledgerBalance('wallet'), 2) . '.');
        }

        DB::transaction(function () use ($sacco, $data, $charges) {
            $base = ['sacco_id' => $sacco->id, 'user_id' => Auth::id(), 'ledger' => 'wallet', 'period_date' => $data['date']];

            SaccoEntry::create($base + [
                    'kind' => 'withdrawal', 'amount' => -(float) $data['amount'],
                    'description' => $data['description'] ?? 'Wallet withdrawal',
                ]);

            if ($charges > 0) {
                SaccoEntry::create($base + ['kind' => 'charges', 'amount' => -$charges, 'description' => 'Withdrawal charges']);
            }
        });

        return back()->with('success', 'Withdrawal recorded.');
    }

    // ── Loans (12% reducing balance, max 36 months) ───────────────────────────

    public function storeLoan(Request $request)
    {
        $sacco = Sacco::firstOrFail();

        if ($sacco->loans()->where('status', 'active')->exists()) {
            return back()->with('error', 'You already have an active loan. Clear it, or refinance it.');
        }

        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'principal'      => 'required|numeric|min:1',
            'term_months'    => 'required|integer|min:1|max:' . SaccoLoan::MAX_TERM,
            'disbursed_date' => 'required|date|before_or_equal:today',
            'existing'       => 'nullable|boolean',
        ]);

        // The amount applied for is paid out in full. The fee is added to what you owe,
        // so the repayable balance (and the interest base) is amount + fee.
        $amount   = (float) $data['principal'];
        $existing = $request->boolean('existing');
        $fee      = $existing ? 0 : SaccoLoan::newLoanFee($amount);
        $owed     = round($amount + $fee, 2);

        SaccoLoan::create([
            'sacco_id'       => $sacco->id,
            'user_id'        => Auth::id(),
            'name'           => $data['name'],
            'principal'      => $owed,
            'annual_rate'    => SaccoLoan::ANNUAL_RATE,
            'term_months'    => $data['term_months'],
            'instalment'     => SaccoLoan::pmt($owed, SaccoLoan::ANNUAL_RATE, (int) $data['term_months']),
            'fee'            => $fee,
            'amount_received' => $existing ? null : $amount,
            'disbursed_date' => $data['disbursed_date'],
            'status'         => 'active',
        ]);

        return back()->with('success', 'Loan added.');
    }

    /**
     * Refinance / top up: the outstanding principal is rolled into a new loan.
     * Fee = 10% of outstanding + 2% of the top-up + 200.
     */
    public function refinanceLoan(Request $request, SaccoLoan $loan)
    {
        abort_unless($loan->user_id === Auth::id(), 403);

        if ($loan->status !== 'active') {
            return back()->with('error', 'Only an active loan can be refinanced.');
        }

        $data = $request->validate([
            'topup'       => 'required|numeric|min:0.01',
            'term_months' => 'required|integer|min:1|max:' . SaccoLoan::MAX_TERM,
            'date'        => 'required|date|before_or_equal:today',
        ]);

        $from = $loan->refinanceEligibleFrom();

        if (! $from) {
            return back()->withInput()->with('error', 'Refinancing opens ' . SaccoLoan::REFI_WAIT_MONTHS
                . ' months after your first repayment. Record the first repayment first.');
        }

        if (Carbon::parse($data['date'])->lt($from) || now()->lt($from)) {
            return back()->withInput()->with('error', 'You can refinance from ' . $from->format('M d, Y')
                . ' (' . SaccoLoan::REFI_WAIT_MONTHS . ' months after your first repayment).');
        }

        $outstanding = round($loan->outstanding(), 2);
        $topup       = (float) $data['topup'];
        $fee         = SaccoLoan::refinanceFee($outstanding, $topup);
        // The top-up is paid out in full; the fee is added to the new balance.
        $principal   = round($outstanding + $topup + $fee, 2);

        DB::transaction(function () use ($loan, $data, $principal, $fee, $topup) {
            $loan->update(['status' => 'refinanced']);

            SaccoLoan::create([
                'sacco_id'           => $loan->sacco_id,
                'user_id'            => Auth::id(),
                'name'               => $loan->name,
                'principal'          => $principal,
                'annual_rate'        => SaccoLoan::ANNUAL_RATE,
                'term_months'        => $data['term_months'],
                'instalment'         => SaccoLoan::pmt($principal, SaccoLoan::ANNUAL_RATE, (int) $data['term_months']),
                'fee'                => $fee,
                'amount_received'    => $topup,
                'disbursed_date'     => $data['date'],
                'status'             => 'active',
                'refinanced_from_id' => $loan->id,
            ]);
        });

        return back()->with('success', 'Loan refinanced. You receive KES ' . number_format($topup, 2)
            . ' in full. Fees of KES ' . number_format($fee, 2) . ' are added to the new balance of KES ' . number_format($principal, 2) . '.');
    }

    public function storeLoanPayment(Request $request, SaccoLoan $loan)
    {
        abort_unless($loan->user_id === Auth::id(), 403);

        if ($loan->status !== 'active') {
            return back()->with('error', 'This loan is no longer active.');
        }

        $data = $request->validate([
            'month'    => 'required|date_format:Y-m',
            'amount'   => 'required|numeric|min:0.01',
            'interest' => 'nullable|numeric|min:0',
        ]);

        $period = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();

        if ($period->gte(now()->startOfMonth())) {
            return back()->withInput()->with('error', 'You can only record months that have ended.');
        }

        if ($period->lte($loan->disbursed_date->copy()->startOfMonth())) {
            return back()->withInput()->with('error', 'The first instalment is the month after the loan was disbursed.');
        }

        if ($loan->payments()->whereDate('period_date', $period->toDateString())->exists()) {
            return back()->withInput()->with('error', $period->format('F Y') . ' is already recorded for this loan.');
        }

        $outstanding = $loan->outstanding();
        $amount      = (float) $data['amount'];
        $interest    = isset($data['interest']) && $data['interest'] !== null
            ? (float) $data['interest']
            : $loan->monthlyInterest();
        $interest  = min($interest, $amount);
        $principal = round($amount - $interest, 2);

        if ($principal > $outstanding + 0.005) {
            return back()->withInput()->with('error', 'That is more than the outstanding balance (KES '
                . number_format($outstanding, 2) . ' plus interest).');
        }

        DB::transaction(function () use ($loan, $period, $amount, $interest, $principal) {
            SaccoLoanPayment::create([
                'sacco_loan_id'     => $loan->id,
                'user_id'           => Auth::id(),
                'period_date'       => $period->toDateString(),
                'amount'            => $amount,
                'interest_portion'  => $interest,
                'principal_portion' => $principal,
            ]);

            if ($loan->outstanding() <= 0.005) {
                $loan->update(['status' => 'cleared']);
            }
        });

        return back()->with('success', 'Instalment for ' . $period->format('F Y') . ' recorded.');
    }

    public function destroyLoan(SaccoLoan $loan)
    {
        abort_unless($loan->user_id === Auth::id(), 403);

        if ($loan->payments()->exists()) {
            return back()->with('error', 'A loan with payments cannot be deleted.');
        }

        // A refinanced loan can be undone only shortly after, which reinstates the original.
        if ($loan->refinanced_from_id) {
            if ($loan->created_at->lt(now()->subMinutes(self::REMOVE_WINDOW_MINUTES))) {
                return back()->with('error', 'A refinanced loan can only be undone for ' . self::REMOVE_WINDOW_MINUTES . ' minutes after saving.');
            }

            DB::transaction(function () use ($loan) {
                SaccoLoan::where('id', $loan->refinanced_from_id)->update(['status' => 'active']);
                $loan->delete();
            });

            return back()->with('success', 'Refinance undone. Your original loan is active again.');
        }

        $loan->delete();

        return back()->with('success', 'Loan removed.');
    }

    // ── Removals (5-minute window) ────────────────────────────────────────────

    public function destroyEntry(SaccoEntry $entry)
    {
        abort_unless($entry->user_id === Auth::id(), 403);

        if ($entry->created_at->lt(now()->subMinutes(self::REMOVE_WINDOW_MINUTES))) {
            return back()->with('error', 'The ' . self::REMOVE_WINDOW_MINUTES . '-minute removal window has passed.');
        }

        $entry->delete();

        return back()->with('success', 'Entry removed.');
    }

    public function destroyLoanPayment(SaccoLoanPayment $payment)
    {
        abort_unless($payment->user_id === Auth::id(), 403);

        if ($payment->created_at->lt(now()->subMinutes(self::REMOVE_WINDOW_MINUTES))) {
            return back()->with('error', 'The ' . self::REMOVE_WINDOW_MINUTES . '-minute removal window has passed.');
        }

        $loan = $payment->loan;
        $payment->delete();

        if ($loan->status === 'cleared' && $loan->outstanding() > 0.005) {
            $loan->update(['status' => 'active']);
        }

        return back()->with('success', 'Payment removed.');
    }
}
