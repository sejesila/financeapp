<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferrerRecoveryApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'referrer_recovery_id',
        'referrer_payout_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function recovery()
    {
        return $this->belongsTo(ReferrerRecovery::class, 'referrer_recovery_id');
    }

    public function payout()
    {
        return $this->belongsTo(ReferrerPayout::class, 'referrer_payout_id');
    }
}
