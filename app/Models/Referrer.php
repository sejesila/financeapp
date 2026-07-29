<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Referrer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'contact', 'default_share_percentage', 'is_active',
    ];

    protected $casts = [
        'default_share_percentage' => 'decimal:2',
        'is_active'                => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });
    }

    public function loans()
    {
        return $this->hasMany(LoanGiven::class);
    }

    public function payouts()
    {
        return $this->hasMany(ReferrerPayout::class);
    }

    /**
     * Interest earned on her referred loans since the last payout (or ever,
     * if she's never been paid), based on closed loans only.
     */
    public function getUnpaidInterestAttribute()
    {
        return $this->loans()
            ->where('status', 'paid')
            ->whereNull('referrer_payout_id')
            ->sum('interest_amount');
    }
}
