<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class LoanGiven extends Model
{
    use HasFactory;

    protected $table = 'loans_given';

    protected $fillable = [
        'user_id',
        'account_id',
        'disbursement_transaction_id',
        'borrower_name',
        'borrower_contact',
        'principal_amount',
        'amount_paid',
        'principal_paid',
        'balance',
        'interest_amount',
        'interest_rate',
        'disbursed_date',
        'due_date',
        'repaid_date',
        'status',
        'notes',
        'referrer_id',
        'referrer_share_percentage',
        'referrer_payout_id',
        'referrer_deducted_before_deposit',
        'referrer_retained_amount',
        'expected_interest_rate',
        'expected_interest_amount',
        'rollover_count',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'amount_paid'      => 'decimal:2',
        'principal_paid'   => 'decimal:2',
        'balance'          => 'decimal:2',
        'interest_amount'  => 'decimal:2',
        'interest_rate'    => 'decimal:2',
        'disbursed_date'   => 'date',
        'due_date'         => 'date',
        'repaid_date'      => 'date',
        'referrer_share_percentage' => 'decimal:2',
        'referrer_deducted_before_deposit' => 'boolean',
        'referrer_retained_amount' => 'decimal:2',
        'expected_interest_rate'   => 'decimal:2',
        'expected_interest_amount' => 'decimal:2',
        'rollover_count'           => 'integer',

    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.user_id", Auth::id());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function payments()
    {
        return $this->hasMany(LoanGivenPayment::class);
    }

    /**
     * Outstanding principal still owed. Driven by principal_paid, NOT amount_paid —
     * amount_paid is the lifetime total of everything received (principal + any
     * interest already split out per payment), while principal_paid is only the
     * portion of that which actually reduced the debt. Before per-payment interest
     * splitting existed, the two were identical for every payment, which is exactly
     * what the principal_paid backfill preserves for old loans.
     */
    public function getRemainingPrincipalAttribute()
    {
        return max(0, $this->principal_amount - $this->principal_paid);
    }

    /**
     * Amount received so far beyond principal, across the loan's entire life —
     * this is unaffected by per-payment interest splitting, since it's still just
     * lifetime amount_paid minus the (fixed) principal_amount. Only meaningful/
     * "final" once closed — before closure this is just a running preview.
     */
    public function getSurplusReceivedAttribute()
    {
        return max(0, $this->amount_paid - $this->principal_amount);
    }

    public function isOverdue()
    {
        if (!$this->due_date || $this->status !== 'active') {
            return false;
        }

        return now()->isAfter($this->due_date);
    }

    public function daysRemaining()
    {
        if (!$this->due_date) {
            return null;
        }

        return $this->due_date->diffInDays(now(), false);
    }

    /**
     * Recompute amount_paid / principal_paid / balance from payments — the
     * authoritative source, not manual increments, so this self-heals if a
     * payment is ever edited or deleted. amount_paid is every shilling ever
     * received (interest included); principal_paid excludes whatever each
     * payment's interest_portion was. Does NOT decide final interest or close
     * the loan — that only happens explicitly via closeAsRepaid(), since more
     * installments (and more interest) might still be coming.
     */
    public function updateBalance()
    {
        $payments = $this->payments()->get(['amount', 'interest_portion']);

        $this->amount_paid    = $payments->sum('amount');
        $this->principal_paid = $payments->sum(fn ($p) => $p->amount - $p->interest_portion);
        $this->balance         = max(0, $this->principal_amount - $this->principal_paid);
        $this->save();
    }

    /**
     * Close the loan as fully repaid. Interest is derived here, from whatever total
     * amount actually came back vs. principal — this is the "you calculate rate"
     * step, since interest fluctuates and isn't known for certain until this point.
     *
     * This deliberately still uses amount_paid (not principal_paid): amount_paid is
     * the lifetime total of everything ever received, so amount_paid - principal_amount
     * already nets out to the FULL interest earned across every rollover payment
     * along the way, with no double-counting — even though some of that interest
     * was already split into its own transaction earlier, at the time each rollover
     * payment was recorded. This number is the final, authoritative one.
     */
    public function closeAsRepaid(?string $repaidDate = null)
    {
        $this->interest_amount = max(0, $this->amount_paid - $this->principal_amount);
        $this->interest_rate   = $this->principal_amount > 0
            ? round(($this->interest_amount / $this->principal_amount) * 100, 2)
            : 0;

        $this->status      = 'paid';
        $this->balance      = 0;
        $this->repaid_date = $repaidDate ?? now()->toDateString();
        $this->save();
    }
    /**
     * Auto-compounds an overdue loan: once due_date is more than $graceDays
     * in the past, the currently expected interest is capitalized into
     * principal_amount (the borrower now genuinely owes that much), a fresh
     * expected_interest_amount is computed at the same expected_interest_rate
     * against the new, larger principal, and due_date is pushed $rolloverDays
     * past its OLD value (not from today) — starting a new interest period.
     *
     * Loops so a loan that's gone unchecked for multiple rollover periods
     * catches up in one call, rather than needing to be visited once per
     * period. Only acts on active loans that have both a due_date and an
     * expected_interest_rate set — a loan with no referrer/rate (and no
     * manual rate entered) is left alone entirely, matching "unless stated
     * otherwise".
     *
     * Persists immediately and recomputes balance. Safe to call unconditionally
     * on every page load — it's a no-op once due_date is within the grace
     * period.
     */
    public function processOverdueRollover(int $graceDays = 5, int $rolloverDays = 30): bool
    {
        if ($this->status !== 'active' || !$this->due_date || $this->expected_interest_rate === null) {
            return false;
        }

        $rolledOver = false;

        while ($this->due_date->copy()->addDays($graceDays)->isPast()) {
            $expectedInterest = (float) $this->expected_interest_amount;
            $rate = (float) $this->expected_interest_rate;

            $newPrincipal = round((float) $this->principal_amount + $expectedInterest, 2);
            $newExpectedInterest = round($newPrincipal * ($rate / 100), 2);

            $this->principal_amount = $newPrincipal;
            $this->expected_interest_amount = $newExpectedInterest;
            $this->due_date = $this->due_date->copy()->addDays($rolloverDays);
            $this->rollover_count = ($this->rollover_count ?? 0) + 1;

            $rolledOver = true;
        }

        if ($rolledOver) {
            $this->save();
            $this->updateBalance(); // principal_amount changed — balance must follow
        }

        return $rolledOver;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeDefaulted($query)
    {
        return $query->where('status', 'defaulted');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'active')
            ->where('due_date', '<', now()->toDateString());
    }
    public function disbursementTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'disbursement_transaction_id');
    }
    public function referrer()
    {
        return $this->belongsTo(Referrer::class);
    }

    public function payout()
    {
        return $this->belongsTo(ReferrerPayout::class, 'referrer_payout_id');
    }
}
