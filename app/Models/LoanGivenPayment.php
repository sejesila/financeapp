<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class LoanGivenPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'loan_given_id',
        'account_id',
        'transaction_id',
        'amount',
        'interest_portion',
        'payment_date',
        'notes',
        'referrer_share_percentage',
        'referrer_commission',
        'referrer_commission_kept',
        'referrer_payout_id',
    ];

    protected $casts = [
        'amount'                    => 'decimal:2',
        'interest_portion'          => 'decimal:2',
        'payment_date'              => 'date',
        'referrer_share_percentage' => 'decimal:2',
        'referrer_commission'       => 'decimal:2',
        'referrer_commission_kept'  => 'boolean',
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

    public function loanGiven()
    {
        return $this->belongsTo(LoanGiven::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payout()
    {
        return $this->belongsTo(ReferrerPayout::class, 'referrer_payout_id');
    }

    /**
     * The portion of this payment that reduced principal (everything not
     * already accounted for as interest).
     */
    public function getPrincipalPortionAttribute()
    {
        return max(0, $this->amount - $this->interest_portion);
    }

    /**
     * Recompute this payment's referrer commission from its current
     * interest_portion. Idempotent: safe to call repeatedly.
     *
     * $kept = true  -> she retained her share before depositing, so the
     *                  deposited interest is NET of her cut and the commission
     *                  is grossed up: ip * share / (100 - share).
     * $kept = false -> normal: ip * share / 100, owed to her until paid out.
     * $kept = null  -> keep whatever flag the payment already has.
     *
     * Frozen once the commission has been included in a payout.
     */
    public function syncReferrerCommission(?bool $kept = null): void
    {
        $loan = $this->loanGiven()->with('referrer')->first();

        if (!$loan?->referrer_id || $this->referrer_payout_id) {
            return;
        }

        $share = (float) ($this->referrer_share_percentage
            ?? $loan->referrer_share_percentage
            ?? $loan->referrer?->default_share_percentage
            ?? 0);

        $kept ??= (bool) $this->referrer_commission_kept;
        $ip    = (float) $this->interest_portion;

        $this->referrer_share_percentage = $share;
        $this->referrer_commission_kept  = $kept;
        $this->referrer_commission = ($share <= 0 || $ip <= 0)
            ? 0
            : (($kept && $share < 100)
                ? round($ip * $share / (100 - $share), 2)
                : round($ip * $share / 100, 2));

        $this->save();
    }
}
