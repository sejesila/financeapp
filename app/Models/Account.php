<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Account extends Model
{
    protected $fillable = [
        'name',
        'type',
        'initial_balance',
        'current_balance',
        'currency',
        'notes',
        'is_active',
        'user_id',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.user_id", Auth::id());
            }
        });
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function transfersFrom()
    {
        return $this->hasMany(Transfer::class, 'from_account_id');
    }

    public function transfersTo()
    {
        return $this->hasMany(Transfer::class, 'to_account_id');
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    // Update balance based on ACTIVE transactions only
    // Update balance based on ACTIVE transactions only
    public function updateBalance()
    {
        // Count ALL non-deleted transactions (don't hide reversed ones)
        $activeTransactions = $this->transactions()->whereNull('deleted_at')->get();

        $totalIncome = $activeTransactions
            ->filter(fn($t) => $t->category->type === 'income')
            ->sum('amount');

        $totalLiability = $activeTransactions
            ->filter(fn($t) => $t->category->type === 'liability')
            ->sum('amount');

        $totalExpense = $activeTransactions
            ->filter(fn($t) => $t->category->type === 'expense')
            ->sum('amount');

        $transfersOut = $this->transfersFrom()->sum('amount');
        $transfersIn = $this->transfersTo()->sum('amount');

        // Balance = Initial + Income + Liability - Expense - Transfers Out + Transfers In
        $this->current_balance = $this->initial_balance
            + $totalIncome
            + $totalLiability
            - $totalExpense
            - $transfersOut
            + $transfersIn;

        $this->save();
    }

}

class Transfer extends Model
{
    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'amount',
        'date',
        'description'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function fromAccount()
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }
}
