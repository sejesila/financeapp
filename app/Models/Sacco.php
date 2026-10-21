<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Sacco extends Model
{
    protected $fillable = [
        'user_id', 'name', 'opening_date', 'opening_shares', 'opening_deposits',
        'opening_wallet', 'borrowing_multiple',
    ];

    protected $casts = [
        'opening_date'       => 'date',
        'opening_shares'     => 'decimal:2',
        'opening_deposits'   => 'decimal:2',
        'opening_wallet'     => 'decimal:2',
        'borrowing_multiple' => 'decimal:1',
    ];

    public const LEDGERS = [
        'shares'   => 'Share capital',
        'deposits' => 'Deposits',
        'wallet'   => 'Mobile wallet',
    ];

    public const KINDS = [
        'contribution' => 'Payroll deposit',
        'dividend'     => 'Dividend / interest',
        'withdrawal'   => 'Withdrawal',
        'charges'      => 'Charges',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable() . '.user_id', Auth::id());
            }
        });
    }

    public function entries()
    {
        return $this->hasMany(SaccoEntry::class);
    }

    public function loans()
    {
        return $this->hasMany(SaccoLoan::class);
    }

    public function ledgerBalance(string $ledger): float
    {
        return (float) $this->{'opening_' . $ledger}
            + (float) $this->entries()->where('ledger', $ledger)->sum('amount');
    }

    public function loansOutstanding(): float
    {
        return (float) $this->loans()->where('status', 'active')->get()
            ->sum(fn ($l) => $l->outstanding());
    }

    public function netPosition(): float
    {
        return $this->ledgerBalance('shares') + $this->ledgerBalance('deposits')
            + $this->ledgerBalance('wallet') - $this->loansOutstanding();
    }

    /** Assumes the sacco lends a multiple of deposits, less what you already owe. */
    public function borrowingPower(): float
    {
        return max(0, $this->ledgerBalance('deposits') * (float) $this->borrowing_multiple - $this->loansOutstanding());
    }

    /** Last completed month, or null while the opening balance already covers it. */
    public function recordableMonth(): ?Carbon
    {
        $last = now()->subMonthNoOverflow()->startOfMonth();

        if ($last->lte($this->opening_date->copy()->startOfMonth())) {
            return null;
        }

        return $last;
    }

    public function hasPendingDeposit(): bool
    {
        $m = $this->recordableMonth();

        return $m && ! $this->entries()->where('slot', 'deposits:contribution:' . $m->format('Y-m'))->exists();
    }

    public function hasPendingLoanPayments(): bool
    {
        $m = now()->subMonthNoOverflow()->startOfMonth();

        foreach ($this->loans()->where('status', 'active')->get() as $loan) {
            if ($loan->disbursed_date->copy()->startOfMonth()->lt($m)
                && ! $loan->payments()->whereDate('period_date', $m->toDateString())->exists()) {
                return true;
            }
        }

        return false;
    }

    /** Previous year's dividend, expected Feb–Jun. Null once recorded or already in the opening balance. */
    public function pendingDividendYear(): ?int
    {
        $y = now()->year - 1;

        if (! in_array(now()->month, [2, 3, 4, 5, 6], true) || $this->opening_date->year > $y) {
            return null;
        }

        return $this->entries()->where('slot', "dividend:$y")->exists() ? null : $y;
    }

    /** Dashboard button: days 1–10, only while something is left to record. */
    public static function shouldShowButton(): bool
    {
        if (now()->day > 10) {
            return false;
        }

        $sacco = static::first();

        if (! $sacco) {
            return true;
        }

        return $sacco->hasPendingDeposit()
            || $sacco->hasPendingLoanPayments()
            || $sacco->pendingDividendYear() !== null;
    }
    /** Wallet withdrawals are allowed from 15 March to 30 April. */
    public static function withdrawalWindowOpen(): bool
    {
        $now = now();

        return $now->between(
            $now->copy()->setDate($now->year, 3, 15)->startOfDay(),
            $now->copy()->setDate($now->year, 4, 30)->endOfDay()
        );
    }
}
