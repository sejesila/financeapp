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
        'is_split',
    ];

    protected $casts = [
        'date'               => 'date',
        'amount'             => 'decimal:2',
        'period_date'        => 'date',
        'is_reversal'        => 'boolean',
        'is_transaction_fee' => 'boolean',
        'is_split'           => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.user_id", Auth::id());
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

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

    public function feeTransaction()
    {
        return $this->belongsTo(Transaction::class, 'related_fee_transaction_id')
            ->withoutGlobalScope('ownedByUser');
    }

    public function mainTransaction()
    {
        return $this->belongsTo(Transaction::class, 'fee_for_transaction_id')
            ->withoutGlobalScope('ownedByUser');
    }

    public function reversalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'reversed_by_transaction_id')
            ->withoutGlobalScope('ownedByUser');
    }

    public function originalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'reverses_transaction_id')
            ->withoutGlobalScope('ownedByUser');
    }

    public function splits()
    {
        return $this->hasMany(TransactionSplit::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Get total amount including all fees.
     *
     * - Non-split: main amount + single fee transaction (existing behaviour).
     * - Split:     main amount + sum of each split's fee transaction.
     * - Fee row:   just its own amount (no recursion).
     */
    public function getTotalAmountAttribute(): float
    {
        if ($this->is_transaction_fee) {
            return (float) $this->amount;
        }

        $total = (float) $this->amount;

        if ($this->is_split) {
            // Bug 9 fix: sum fees from each split row, not from the parent
            $splitFees = $this->splits->sum(function ($split) {
                return $split->feeTransaction?->amount ?? 0;
            });
            $total += (float) $splitFees;
        } elseif ($this->feeTransaction) {
            $total += (float) $this->feeTransaction->amount;
        }

        return $total;
    }

    // ── Helper methods ────────────────────────────────────────────────────────

    /**
     * Whether any fee was charged for this transaction.
     * For splits, checks each split row's fee link.          // Bug 10 fix
     */
    public function hasFee(): bool
    {
        if ($this->is_split) {
            return $this->splits->contains(
                fn($split) => $split->related_fee_transaction_id !== null
            );
        }

        return $this->related_fee_transaction_id !== null;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeWithoutFees($query)
    {
        return $query->where('is_transaction_fee', false);
    }

    public function scopeOnlyFees($query)
    {
        return $query->where('is_transaction_fee', true);
    }
}
