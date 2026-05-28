<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Transaction extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'date',
        'description',
        'amount',
        'payment_method',
        'period_date',
        'mobile_money_type',
        'category_id',
        'account_id',
        'user_id',
        'related_fee_transaction_id',
        'fee_for_transaction_id',
        'is_transaction_fee',
    ];

    protected $casts = [
        'date'               => 'datetime',
        'amount'             => 'decimal:2',
        'is_transaction_fee' => 'boolean',
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

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScopes()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
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


    public function getTotalAmountAttribute(): float
    {
        if ($this->is_transaction_fee) {
            return (float) $this->amount;
        }
        return (float) $this->amount + (float) ($this->feeTransaction?->amount ?? 0);
    }

    public function hasFee(): bool
    {
        return $this->related_fee_transaction_id !== null;
    }

    public function scopeWithoutFees($query)
    {
        return $query->where('is_transaction_fee', false);
    }

    public function scopeOnlyFees($query)
    {
        return $query->where('is_transaction_fee', true);
    }
}
