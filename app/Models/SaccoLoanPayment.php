<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SaccoLoanPayment extends Model
{
    protected $fillable = [
        'sacco_loan_id', 'user_id', 'period_date', 'amount', 'interest_portion', 'principal_portion',
    ];

    protected $casts = [
        'period_date'       => 'date',
        'amount'            => 'decimal:2',
        'interest_portion'  => 'decimal:2',
        'principal_portion' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable() . '.user_id', Auth::id());
            }
        });
    }

    public function loan()
    {
        return $this->belongsTo(SaccoLoan::class, 'sacco_loan_id');
    }
}
