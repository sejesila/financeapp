<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ReferrerFloatReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'loan_given_id',
        'transfer_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });
    }

    public function loanGiven()
    {
        return $this->belongsTo(LoanGiven::class);
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
}
