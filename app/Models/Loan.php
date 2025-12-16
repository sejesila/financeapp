<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Loan extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'source',
        'principal_amount',
        'interest_rate',
        'interest_amount',
        'total_amount',
        'amount_paid',
        'balance',
        'disbursed_date',
        'due_date',
        'repaid_date',
        'status',
        'notes',
        'loan_type',

    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'disbursed_date' => 'date',
        'due_date' => 'date',
        'repaid_date' => 'date',
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.user_id", Auth::id());
            }
        });
    }


    /**
     * Relationship: Loan belongs to Account
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Relationship: Loan has many Payments
     */
    public function payments()
    {
        return $this->hasMany(LoanPayment::class);
    }

    /**
     * Calculate total interest amount based on rate
     */
    public function calculateInterest()
    {
        if ($this->interest_rate > 0) {
            $this->interest_amount = ($this->principal_amount * $this->interest_rate) / 100;
        } else {
            $this->interest_amount = 0;
        }

        $this->total_amount = $this->principal_amount + $this->interest_amount;
        $this->balance = $this->total_amount - $this->amount_paid;

        return $this;
    }

    /**
     * Check if loan is overdue
     */
    public function isOverdue()
    {
        if (!$this->due_date || $this->status !== 'active') {
            return false;
        }

        return now()->isAfter($this->due_date);
    }

    /**
     * Get remaining days until due date
     * Returns negative number if overdue
     */
    public function daysRemaining()
    {
        if (!$this->due_date) {
            return null;
        }

        return $this->due_date->diffInDays(now(), false);
    }

    /**
     * Update loan balance after payment
     * Automatically marks as paid if fully repaid
     */
    public function updateBalance()
    {
        $this->amount_paid = $this->payments()->sum('amount');
        $this->balance = $this->total_amount - $this->amount_paid;

        // Mark as paid if fully repaid
        if ($this->balance <= 0) {
            $this->status = 'paid';
            $this->balance = 0;
            $this->repaid_date = now()->toDateString();
        }

        $this->save();
    }

    /**
     * Scope: Get only active loans
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Get only paid loans
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope: Get only defaulted loans
     */
    public function scopeDefaulted($query)
    {
        return $query->where('status', 'defaulted');
    }

    /**
     * Scope: Get overdue loans
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'active')
            ->where('due_date', '<', now()->toDateString());
    }
}

class LoanPayment extends Model
{
    protected $fillable = [
        'loan_id',
        'account_id',
        'amount',
        'principal_portion',
        'interest_portion',
        'payment_date',
        'transaction_id',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'principal_portion' => 'decimal:2',
        'interest_portion' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /**
     * Relationship: Payment belongs to Loan
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Relationship: Payment belongs to Account
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Relationship: Payment has related Transaction
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
