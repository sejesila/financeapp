<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ReferrerPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'referrer_id', 'period_start', 'period_end',
        'total_interest', 'share_percentage', 'amount_paid',
        'account_id', 'transaction_id', 'paid_date',
    ];

    protected $casts = [
        'period_start'     => 'date',
        'period_end'       => 'date',
        'total_interest'   => 'decimal:2',
        'share_percentage' => 'decimal:2',
        'amount_paid'      => 'decimal:2',
        'paid_date'        => 'date',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });
    }

    public function referrer()
    {
        return $this->belongsTo(Referrer::class);
    }

    public function loans()
    {
        return $this->hasMany(LoanGiven::class, 'referrer_payout_id');
    }
}
