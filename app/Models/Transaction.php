<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'date',
        'description',
        'amount',
        'payment_method',
        'mobile_money_type',
        'category_id',
        'account_id',
        'user_id',
        'period_date',
        'is_reversal',
        'reversal_reason',
        'reversed_by_transaction_id',
        'reverses_transaction_id',
        'related_fee_transaction_id',
        'fee_for_transaction_id',
        'is_transaction_fee',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'period_date' => 'date',
        'is_reversal' => 'boolean',
        'is_transaction_fee' => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.user_id", Auth::id());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the fee transaction associated with this main transaction
     */
    public function feeTransaction()
    {
        return $this->belongsTo(Transaction::class, 'related_fee_transaction_id')->withoutGlobalScope('ownedByUser');
    }

    /**
     * Get the main transaction this fee belongs to
     */
    public function mainTransaction()
    {
        return $this->belongsTo(Transaction::class, 'fee_for_transaction_id')->withoutGlobalScope('ownedByUser');
    }

    /**
     * Get the reversal transaction (if this was reversed)
     */
    public function reversalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'reversed_by_transaction_id')->withoutGlobalScope('ownedByUser');
    }

    /**
     * Get the original transaction (if this is a reversal)
     */
    public function originalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'reverses_transaction_id')->withoutGlobalScope('ownedByUser');
    }

    /**
     * Get total amount including fee
     */
    public function getTotalAmountAttribute()
    {
        if ($this->is_transaction_fee) {
            return $this->amount; // Fee transactions show only their amount
        }

        $total = $this->amount;
        if ($this->feeTransaction) {
            $total += $this->feeTransaction->amount;
        }
        return $total;
    }

    /**
     * Check if this transaction has an associated fee
     */
    public function hasFee()
    {
        return $this->related_fee_transaction_id !== null;
    }

    /**
     * Scope to exclude transaction fees from queries
     */
    public function scopeWithoutFees($query)
    {
        return $query->where('is_transaction_fee', false);
    }

    /**
     * Scope to get only transaction fees
     */
    public function scopeOnlyFees($query)
    {
        return $query->where('is_transaction_fee', true);
    }
}
