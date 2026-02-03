<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Account extends Model
{
    protected $fillable = [
        'name',
        'slug',
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

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.user_id", Auth::id());
            }
        });

        static::creating(function ($account) {
            if (empty($account->slug)) {
                $account->slug = Str::slug($account->name);
                $originalSlug = $account->slug;
                $count = 1;
                while (static::withoutGlobalScopes()->where('slug', $account->slug)
                    ->where('user_id', $account->user_id)
                    ->exists()) {
                    $account->slug = $originalSlug . '-' . $count++;
                }
            }
        });

        static::updating(function ($account) {
            if ($account->isDirty('name')) {
                $account->slug = Str::slug($account->name);
                $originalSlug = $account->slug;
                $count = 1;
                while (static::withoutGlobalScopes()->where('slug', $account->slug)
                    ->where('user_id', $account->user_id)
                    ->where('id', '!=', $account->id)
                    ->exists()) {
                    $account->slug = $originalSlug . '-' . $count++;
                }
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function clientFunds()
    {
        return $this->hasMany(ClientFund::class);
    }

    public function updateBalance()
    {
        // ✅ SINGLE QUERY - Let database do the heavy lifting
        $stats = $this->transactions()
            ->whereNull('deleted_at')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw("
            -- Regular Income (excluding loan credits and adjustments)
            SUM(CASE
                WHEN categories.type = 'income'
                AND categories.name NOT IN ('Loan Fees Refund', 'Facility Fee Refund', 'Balance Adjustment')
                THEN transactions.amount
                ELSE 0
            END) as total_income,

            -- Loan Credits (early repayment refunds)
            SUM(CASE
                WHEN categories.name IN ('Loan Fees Refund', 'Facility Fee Refund')
                THEN transactions.amount
                ELSE 0
            END) as loan_credits,

            -- Loan Disbursements (money borrowed)
            SUM(CASE
                WHEN categories.type = 'liability'
                AND categories.name = 'Loan Receipt'
                THEN transactions.amount
                ELSE 0
            END) as loan_disbursements,

            -- Client Funds Received (positive liability)
            SUM(CASE
                WHEN categories.type = 'liability'
                AND categories.name = 'Client Funds'
                AND transactions.amount > 0
                THEN transactions.amount
                ELSE 0
            END) as client_funds_received,

            -- Client Funds Reduction (negative liability)
            SUM(CASE
                WHEN categories.type = 'liability'
                AND categories.name = 'Client Funds'
                AND transactions.amount < 0
                THEN ABS(transactions.amount)
                ELSE 0
            END) as client_funds_reduction,

            -- All Expenses
            SUM(CASE
                WHEN categories.type = 'expense'
                THEN transactions.amount
                ELSE 0
            END) as total_expenses,

            -- Balance Adjustments (can be positive or negative)
            SUM(CASE
                WHEN categories.name = 'Balance Adjustment'
                THEN transactions.amount
                ELSE 0
            END) as balance_adjustments
        ")
            ->first();

        // ✅ EFFICIENT TRANSFER QUERIES - Use aggregation
        $transferStats = DB::table('transfers')
            ->selectRaw('
            SUM(CASE WHEN from_account_id = ? THEN amount ELSE 0 END) as transfers_out,
            SUM(CASE WHEN to_account_id = ? THEN amount ELSE 0 END) as transfers_in
        ', [$this->id, $this->id])
            ->first();

        // ✅ CALCULATE BALANCE - Simple arithmetic
        $newBalance = (float) $this->initial_balance
            + ($stats->total_income ?? 0)
            + ($stats->loan_disbursements ?? 0)
            + ($stats->client_funds_received ?? 0)
            - ($stats->client_funds_reduction ?? 0)
            + ($stats->loan_credits ?? 0)
            - ($stats->total_expenses ?? 0)
            + ($stats->balance_adjustments ?? 0)
            - ($transferStats->transfers_out ?? 0)
            + ($transferStats->transfers_in ?? 0);

        // ✅ SINGLE UPDATE - No additional queries
        $this->timestamps = false; // Don't update timestamps for balance recalc
        $this->current_balance = $newBalance;
        $this->save();
        $this->timestamps = true;
    }

    /**
     * Get net profit (excluding loans and client work)
     * Regular Income + Loan Credits - Regular Expenses
     */
    public function getNetProfit()
    {
        $activeTransactions = $this->transactions()
            ->whereNull('deleted_at')
            ->with('category')
            ->get();

        // Income (excluding loans)
        $income = $activeTransactions
            ->filter(function($t) {
                if ($t->category->type !== 'income') {
                    return false;
                }
                if (in_array($t->category->name, ['Loan Fees Refund', 'Facility Fee Refund'])) {
                    return false;
                }
                return true;
            })
            ->sum('amount');

        // Loan credits
        $loanCredits = $activeTransactions
            ->filter(fn($t) => in_array($t->category->name, ['Loan Fees Refund', 'Facility Fee Refund']))
            ->sum('amount');

        // Regular expenses (excluding loan repayments and client-related)
        $expenses = $activeTransactions
            ->filter(function($t) {
                if ($t->category->type !== 'expense') {
                    return false;
                }
                // Exclude loan repayments and client spending
                if (in_array($t->category->name, ['Loan Repayment', 'Excise Duty'])) {
                    return false;
                }
                return true;
            })
            ->sum('amount');

        return $income + $loanCredits - $expenses;
    }

    /**
     * Get total amount borrowed (active loans)
     */
    public function getTotalLoansActive()
    {
        return $this->loans()
            ->where('status', 'active')
            ->sum('balance');
    }

    /**
     * Get total client funds balance (pending)
     */
    public function getClientFundsBalance()
    {
        return $this->clientFunds()
            ->where('status', '!=', 'completed')
            ->sum('balance');
    }

    /**
     * Get total client profits earned
     */
    public function getTotalClientProfit()
    {
        return $this->clientFunds()
            ->sum('profit_amount');
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
}
