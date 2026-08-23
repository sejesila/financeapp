<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    // =====================================================================
    // RELATIONSHIPS
    // =====================================================================

    /**
     * Get the user that owns this rolling fund
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the account for this rolling fund
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get all transactions linked to this rolling fund
     *
     * @return HasMany
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'rolling_fund_id');
    }

    /**
     * Get transactions with relationships loaded and ready for display
     * Excludes soft-deleted transactions and orders by date
     *
     * This is the primary method to use for querying related transactions
     * as it handles all the scope and eager-loading issues automatically.
     */
    public function relatedTransactions()
    {
        return $this->transactions()
            ->with(['category', 'account'])
            ->withoutTrashed()
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get only the stake (expense) transaction
     * This is the "Rolling Funds Out" transaction
     */
    public function stakeTransaction()
    {
        return $this->transactions()
            ->where('description', 'Rolling Funds Out')
            ->withoutTrashed()
            ->first();
    }

    /**
     * Get only the returns (income) transaction
     * This is the "Rolling Funds Returns" transaction
     */
    public function returnsTransaction()
    {
        return $this->transactions()
            ->where('description', 'like', 'Rolling Funds Returns%')
            ->withoutTrashed()
            ->first();
    }

    // =====================================================================
    // ACCESSORS
    // =====================================================================

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

    // =====================================================================
    // HELPER METHODS
    // =====================================================================

    /**
     * Check if this rolling fund is a winning session
     */
    public function isWin(): bool
    {
        return $this->status === 'completed' && $this->winnings > $this->stake_amount;
    }

    /**
     * Check if this rolling fund is a losing session
     */
    public function isLoss(): bool
    {
        return $this->status === 'completed' && $this->winnings < $this->stake_amount;
    }

    /**
     * Check if this rolling fund broke even
     */
    public function isBreakEven(): bool
    {
        return $this->status === 'completed' && $this->winnings == $this->stake_amount;
    }

    /**
     * Check if this rolling fund is still pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
