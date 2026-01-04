<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class LoanPayment extends Model
{
    protected $fillable = [
        'user_id',
        'loan_id',
        'account_id',
        'amount',
        'principal_portion',
        'interest_portion',
        'payment_date',
        'transaction_id',
        'notes',

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
    public function user()
    {
        return $this->belongsTo(User::class);
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
    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.user_id", Auth::id());
            }
        });
    }
}
