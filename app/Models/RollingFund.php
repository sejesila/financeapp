<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RollingFund extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'date',
        'stake_amount',
        'winnings',
        'status',
        'completed_date',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'completed_date' => 'date',
        'stake_amount' => 'decimal:2',
        'winnings' => 'decimal:2',
    ];

    protected $appends = [
        'net_result',
        'profit_percentage',
        'outcome',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    // Accessors
    public function getNetResultAttribute()
    {
        if ($this->status === 'pending' || is_null($this->winnings)) {
            return 0;
        }
        return $this->winnings - $this->stake_amount;
    }

    public function getProfitPercentageAttribute()
    {
        if ($this->status === 'pending' || is_null($this->winnings) || $this->stake_amount == 0) {
            return 0;
        }
        return round((($this->winnings - $this->stake_amount) / $this->stake_amount) * 100, 2);
    }

    public function getOutcomeAttribute()
    {
        if ($this->status === 'pending' || is_null($this->winnings)) {
            return 'pending';
        }

        if ($this->winnings > $this->stake_amount) {
            return 'win';
        } elseif ($this->winnings < $this->stake_amount) {
            return 'loss';
        }
        return 'break_even';
    }

    // Helper methods
    public function isWin()
    {
        return $this->status === 'completed' && $this->winnings > $this->stake_amount;
    }

    public function isLoss()
    {
        return $this->status === 'completed' && $this->winnings < $this->stake_amount;
    }

    public function isBreakEven()
    {
        return $this->status === 'completed' && $this->winnings == $this->stake_amount;
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }
}
