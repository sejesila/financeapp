<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Referrer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'contact', 'default_share_percentage', 'is_active','default_interest_rate',
    ];

    protected $casts = [
        'default_share_percentage' => 'decimal:2',
        'is_active'                => 'boolean',
        'default_interest_rate' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });
    }

    public function loans()
    {
        return $this->hasMany(LoanGiven::class);
    }

    public function payouts()
    {
        return $this->hasMany(ReferrerPayout::class);
    }

    /**
     * The dedicated referrer_float account this referrer's collected money
     * (principal + interest) lands in, if one's been set up. A referrer can
     * have at most one — if you find yourself needing more than one, that's
     * really two different referrers.
     */
    public function floatAccount()
    {
        return $this->hasOne(Account::class)->where('type', 'referrer_float');
    }

    /**
     * Interest earned on her referred loans since the last payout (or ever,
     * if she's never been paid), based on closed loans only.
     */
    public function getUnpaidInterestAttribute()
    {
        return LoanGivenPayment::whereNull('referrer_payout_id')
            ->where('referrer_commission_kept', false)
            ->where('referrer_commission', '>', 0)
            ->whereHas('loanGiven', fn ($q) => $q->where('referrer_id', $this->id))
            ->sum('interest_portion');
    }
    /**
     * Per-loan float interest that is safe to move out to YOU: only loans that
     * are fully paid, and only YOUR share of what's pending.
     *
     * pendingFloatInterestByLoan() is gross — on a closed loan it still
     * includes the referrer's own commission until that has been paid out.
     * That commission has to stay in the float so Pay Out Referrer can still
     * take it from there, so it's subtracted here.
     *
     * Returns [loan_given_id => amount], oldest-closed loan first.
     */
    public function reconcilableFloatInterestByLoan(): \Illuminate\Support\Collection
    {
        $pending = $this->pendingFloatInterestByLoan();

        if ($pending->isEmpty()) {
            return collect();
        }

        $loans = $this->loans()
            ->whereIn('id', $pending->keys())
            ->where('status', 'paid')
            ->orderBy('repaid_date')
            ->orderBy('id')
            ->get();

        // Commission still sitting in her float: accrued on payments,
        // not yet paid out, and not kept by her before depositing.
        $commission = LoanGivenPayment::whereIn('loan_given_id', $loans->pluck('id'))
            ->whereNull('referrer_payout_id')
            ->where('referrer_commission_kept', false)
            ->groupBy('loan_given_id')
            ->selectRaw('loan_given_id, SUM(referrer_commission) as total')
            ->pluck('total', 'loan_given_id');

        return $loans->mapWithKeys(fn ($loan) => [
            $loan->id => max(0, round((float) $pending[$loan->id] - (float) ($commission[$loan->id] ?? 0), 2)),
        ])->filter(fn ($amount) => $amount > 0.01);
    }

    /**
     * Per-loan interest that has been recognized (via a rollover payment or
     * final closure) into this referrer's float account, but not yet
     * reconciled out via a ReferrerFloatReconciliation row. Returns
     * [loan_given_id => pending_amount], loans with nothing pending omitted.
     *
     * This is unrelated to getUnpaidInterestAttribute()/payouts() above —
     * those track the referrer's OWN commission on interest. This tracks
     * YOUR money (principal + interest) that she's collected on your behalf
     * and is holding, still owed back to you.
     *
     * Deliberately not filtered by loan status: a loan that has already
     * closed can still have interest stuck in the float from an earlier
     * rollover payment, if the final closing interest happened to route
     * elsewhere — that money is still pending regardless of the loan's
     * current status.
     */
    public function pendingFloatInterestByLoan(): \Illuminate\Support\Collection
    {
        $floatAccount = $this->floatAccount;

        if (!$floatAccount) {
            return collect();
        }

        $earned = DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->join('loan_given_payments', 'transactions.reference_id', '=', 'loan_given_payments.id')
            ->join('loans_given', 'loan_given_payments.loan_given_id', '=', 'loans_given.id')
            ->where('loans_given.referrer_id', $this->id)
            ->where('transactions.account_id', $floatAccount->id)
            ->where('categories.name', 'Loan Interest')
            ->whereNull('transactions.deleted_at')
            ->selectRaw('loans_given.id as loan_id, SUM(transactions.amount) as total')
            ->groupBy('loans_given.id')
            ->get()
            ->pluck('total', 'loan_id');

        if ($earned->isEmpty()) {
            return collect();
        }

        $reconciled = DB::table('referrer_float_reconciliations')
            ->join('loans_given', 'referrer_float_reconciliations.loan_given_id', '=', 'loans_given.id')
            ->where('loans_given.referrer_id', $this->id)
            ->where(fn ($q) => $q
                ->whereNull('referrer_float_reconciliations.component')
                ->orWhere('referrer_float_reconciliations.component', 'interest'))
            ->selectRaw('loans_given.id as loan_id, SUM(referrer_float_reconciliations.amount) as total')
            ->groupBy('loans_given.id')
            ->get()
            ->pluck('total', 'loan_id');

        $pending = collect();

        foreach ($earned as $loanId => $earnedTotal) {
            $remaining = round((float) $earnedTotal - (float) ($reconciled[$loanId] ?? 0), 2);
            if ($remaining > 0.01) {
                $pending->put($loanId, $remaining);
            }
        }

        return $pending;
    }
    /**
     * Principal collected into her float and not yet remitted back to you,
     * keyed by loan id. Same shape as pendingFloatInterestByLoan().
     */
    public function pendingFloatPrincipalByLoan(): \Illuminate\Support\Collection
    {
        $floatAccount = $this->floatAccount;

        if (!$floatAccount) {
            return collect();
        }

        // What's left of each repayment transaction on her float after the
        // interest was carved out. Pure-interest payments (soft-deleted) drop out.
        $received = DB::table('loan_given_payments as p')
            ->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->join('loans_given as l', 'l.id', '=', 'p.loan_given_id')
            ->where('l.referrer_id', $this->id)
            ->where('t.account_id', $floatAccount->id)
            ->whereNull('t.deleted_at')
            ->selectRaw('l.id as loan_id, SUM(t.amount) as total')
            ->groupBy('l.id')
            ->pluck('total', 'loan_id');

        if ($received->isEmpty()) {
            return collect();
        }

        $reconciled = DB::table('referrer_float_reconciliations as r')
            ->join('loans_given as l', 'l.id', '=', 'r.loan_given_id')
            ->where('l.referrer_id', $this->id)
            ->where('r.component', 'principal')
            ->selectRaw('l.id as loan_id, SUM(r.amount) as total')
            ->groupBy('l.id')
            ->pluck('total', 'loan_id');

        $pending = collect();

        foreach ($received as $loanId => $total) {
            $remaining = round((float) $total - (float) ($reconciled[$loanId] ?? 0), 2);
            if ($remaining > 0.01) {
                $pending->put($loanId, $remaining);
            }
        }

        return $pending;
    }


// ─────────────────────────────────────────────────────────────────────────────
// ADD these methods to App\Models\Referrer (keep everything already there).
// Needs: use Illuminate\Support\Collection;  (or keep the FQCN style)
// ─────────────────────────────────────────────────────────────────────────────

    /**
     * Commission accrued on her referred loans that is still owed to HER
     * (not paid out, not kept before deposit). It sits inside the float's
     * interest, so it is never available to remit to you. [loan_id => amount]
     */
    public function unpaidCommissionByLoan(): \Illuminate\Support\Collection
    {
        return LoanGivenPayment::whereNull('referrer_payout_id')
            ->where('referrer_commission_kept', false)
            ->where('referrer_commission', '>', 0)
            ->whereHas('loanGiven', fn($q) => $q->where('referrer_id', $this->id))
            ->groupBy('loan_given_id')
            ->selectRaw('loan_given_id, SUM(referrer_commission) as total')
            ->pluck('total', 'loan_given_id')
            ->map(fn($v) => (float)$v);
    }

    /**
     * Interest that is YOURS and can be remitted now, per loan:
     * pending interest in the float minus her unpaid commission on that loan.
     */
    public function floatInterestAvailableByLoan(): \Illuminate\Support\Collection
    {
        $commission = $this->unpaidCommissionByLoan();

        return $this->pendingFloatInterestByLoan()
            ->map(fn($pending, $loanId) => round(max(0, (float)$pending - (float)($commission[$loanId] ?? 0)), 2))
            ->filter(fn($amount) => $amount > 0.01);
    }

    /**
     * Principal she is physically holding for you, as ONE pool.
     * = float balance - all interest sitting in the float (yours + her commission).
     * Re-lent money has already left the balance, so it is never counted again.
     */
    public function floatPrincipalAvailable(): float
    {
        $account = $this->floatAccount;

        if (!$account) {
            return 0.0;
        }

        $interestHeld = (float)$this->pendingFloatInterestByLoan()->sum();

        return round(max(0, (float)$account->current_balance - $interestHeld), 2);
    }

    /**
     * Everything the reconcile page/validation needs, in one place.
     */
    public function floatReconcileLimits(): array
    {
        $account = $this->floatAccount;
        $balance = $account ? round((float)$account->current_balance, 2) : 0.0;
        $commission = round((float)$this->unpaidCommissionByLoan()->sum(), 2);

        $interest = round((float)$this->floatInterestAvailableByLoan()->sum(), 2);
        $principal = $this->floatPrincipalAvailable();

        // Hard ceiling = real cash that is not hers. (Transfer fee still has to
        // fit on top; TransferService checks that itself.)
        $cash = round(max(0, $balance - $commission), 2);

        return [
            'balance' => $balance,
            'commission' => $commission,
            'interest' => min($interest, $cash),
            'principal' => min($principal, $cash),
            'max_total' => min($cash, $interest + $principal),
        ];
    }
}
