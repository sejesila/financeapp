<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SaccoLoan extends Model
{
    /** Sacco terms. */
    public const ANNUAL_RATE          = 12.0;   // % per year, reducing balance
    public const MAX_TERM             = 36;     // months
    public const NEW_LOAN_RATE        = 0.02;   // 2% of the amount borrowed
    public const REFI_OUTSTANDING_RATE = 0.10;  // 10% of outstanding principal
    public const REFI_TOPUP_RATE      = 0.02;   // 2% of the amount topped up
    public const FLAT_FEE             = 200;    // added to every new loan / refinance

    protected $fillable = [
        'sacco_id', 'user_id', 'name', 'principal', 'annual_rate',
        'term_months', 'instalment', 'fee', 'amount_received', 'disbursed_date', 'status', 'refinanced_from_id',
    ];

    protected $casts = [
        'principal'      => 'decimal:2',
        'annual_rate'    => 'decimal:2',
        'instalment'     => 'decimal:2',
        'fee'            => 'decimal:2',
        'amount_received' => 'decimal:2',
        'disbursed_date' => 'date',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable() . '.user_id', Auth::id());
            }
        });
    }

    public function sacco()
    {
        return $this->belongsTo(Sacco::class);
    }

    public function payments()
    {
        return $this->hasMany(SaccoLoanPayment::class);
    }

    public function outstanding(): float
    {
        return max(0, (float) $this->principal - (float) $this->payments()->sum('principal_portion'));
    }

    public function interestPaid(): float
    {
        return (float) $this->payments()->sum('interest_portion');
    }

    /** Interest for the next instalment, on the reducing balance. */
    public function monthlyInterest(): float
    {
        return round($this->outstanding() * (float) $this->annual_rate / 1200, 2);
    }

    public function monthsLeft(): int
    {
        return max(0, $this->term_months - $this->payments()->count());
    }

    /** Refinancing opens this many months after the first repayment. */
    public const REFI_WAIT_MONTHS = 6;

    /** First day refinancing is allowed, or null until a first repayment is recorded. */
    public function refinanceEligibleFrom(): ?\Carbon\Carbon
    {
        $first = $this->payments()->min('period_date');

        return $first
            ? \Carbon\Carbon::parse($first)->startOfMonth()->addMonthsNoOverflow(self::REFI_WAIT_MONTHS)
            : null;
    }

    public function canRefinance(): bool
    {
        $from = $this->refinanceEligibleFrom();

        return $this->status === 'active' && $from !== null && now()->gte($from);
    }

    /** One-off fee on a brand-new loan: 2% of the amount + 200. */
    public static function newLoanFee(float $amount): float
    {
        return round($amount * self::NEW_LOAN_RATE + self::FLAT_FEE, 2);
    }

    /** Refinance fee: 10% of outstanding principal + 2% of the top-up + 200. */
    public static function refinanceFee(float $outstanding, float $topup): float
    {
        return round($outstanding * self::REFI_OUTSTANDING_RATE + $topup * self::REFI_TOPUP_RATE + self::FLAT_FEE, 2);
    }

    /** Standard amortised monthly instalment. */
    public static function pmt(float $principal, float $annualRate, int $months): float
    {
        $r = $annualRate / 1200;

        if ($r <= 0) {
            return round($principal / max(1, $months), 2);
        }

        return round($principal * $r / (1 - pow(1 + $r, -$months)), 2);
    }
}
