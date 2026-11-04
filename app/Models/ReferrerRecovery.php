<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ReferrerRecovery extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'referrer_id', 'loan_given_id', 'amount', 'recovered', 'status'];

    protected $casts = ['amount' => 'decimal:2', 'recovered' => 'decimal:2'];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });
    }

    public function loanGiven() { return $this->belongsTo(LoanGiven::class); }
    public function applications() { return $this->hasMany(ReferrerRecoveryApplication::class); }

    public function getOutstandingAttribute(): float
    {
        return max(0, round((float)$this->amount - (float)$this->recovered, 2));
    }
}
