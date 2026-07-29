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
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'amount_paid'      => 'decimal:2',
        'balance'          => 'decimal:2',
        'interest_amount'  => 'decimal:2',
        'interest_rate'    => 'decimal:2',
        'disbursed_date'   => 'date',
        'due_date'         => 'date',
        'repaid_date'      => 'date',
        'referrer_share_percentage' => 'decimal:2',
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
     * Outstanding principal still owed (ignores any surplus received as interest).
     */
    public function getRemainingPrincipalAttribute()
    {
        return max(0, $this->principal_amount - $this->amount_paid);
    }

    /**
     * Amount received so far beyond principal. Only meaningful/"final" once closed —
     * before closure this is just a running preview, not a committed interest figure.
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
     * Recompute amount_paid / balance from payments. Does NOT decide interest or
     * close the loan — that only happens explicitly via closeAsRepaid(), since more
     * installments might still be coming even after principal is recovered.
     */
    public function updateBalance()
    {
        $this->amount_paid = $this->payments()->sum('amount');
        $this->balance      = max(0, $this->principal_amount - $this->amount_paid);
        $this->save();
    }

    /**
     * Close the loan as fully repaid. Interest is derived here, from whatever total
     * amount actually came back vs. principal — this is the "you calculate rate"
     * step, since interest fluctuates and isn't known until this point.
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
