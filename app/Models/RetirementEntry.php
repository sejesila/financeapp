<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RetirementEntry extends Model
{
    protected $fillable = [
        'retirement_fund_id', 'user_id', 'entry_type', 'section', 'period_date',
        'employee_amount', 'employer_amount', 'voluntary_amount', 'interest_amount', 'notes',
    ];

    protected $casts = [
        'period_date'      => 'date',
        'employee_amount'  => 'decimal:2',
        'employer_amount'  => 'decimal:2',
        'voluntary_amount' => 'decimal:2',
        'interest_amount'  => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable() . '.user_id', Auth::id());
            }
        });
    }

    public function fund()
    {
        return $this->belongsTo(RetirementFund::class, 'retirement_fund_id');
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->employee_amount + (float) $this->employer_amount
            + (float) $this->voluntary_amount + (float) $this->interest_amount;
    }
}
