<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
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

    /**
     * Complete balance calculation with ALL system features:
     * - Regular transactions (income/expense)
     * - Transfers (between accounts)
     * - Loans (disbursement and repayment)
     * - Client funds (tracking separate from main balance)
     * - Transaction fees
     * - Balance adjustments
     *
     * Formula:
     * Balance = Initial Balance
     *         + Income (salary, side gigs, etc) - EXCLUDES loan credits
     *         + Loan Disbursements (money borrowed)
     *         + Client Funds Received (liability)
     *         - Expenses (all spending)
     *         - Loan Repayments (money paid back)
     *         - Transfers Out
     *         + Transfers In
     */
    public function updateBalance()
    {
        $activeTransactions = $this->transactions()
            ->whereNull('deleted_at')
            ->with('category')
            ->get();

        // System categories to exclude from certain calculations
        $systemCategories = [
            'Loan Receipt',
            'Loan Repayment',
            'Excise Duty',
            'Loan Fees Refund',
            'Facility Fee Refund',
            'Transaction Fees',
            'Balance Adjustment',
            'Client Funds', // Liability tracking
        ];

        // ✅ INCOME - All income EXCEPT loan credits and balance adjustments
        $totalIncome = $activeTransactions
            ->filter(function($t) use ($systemCategories) {
                if ($t->category->type !== 'income') {
                    return false;
                }
                // Exclude loan credit transactions (refunds/cashbacks)
                if (in_array($t->category->name, ['Loan Fees Refund', 'Facility Fee Refund'])) {
                    return false;
                }
                // Exclude balance adjustments
                if ($t->category->name === 'Balance Adjustment') {
                    return false;
                }
                return true;
            })
            ->sum('amount');

        // ✅ LOAN CREDITS - Money refunded from early repayment
        $loanCredits = $activeTransactions
            ->filter(function($t) {
                return in_array($t->category->name, ['Loan Fees Refund', 'Facility Fee Refund']);
            })
            ->sum('amount');

        // ✅ LOAN DISBURSEMENTS - Money you borrowed (increases balance)
        // This is the principal amount you receive (full amount)
        $loanDisbursements = $activeTransactions
            ->filter(function($t) {
                if ($t->category->type !== 'liability') {
                    return false;
                }
                if ($t->category->name !== 'Loan Receipt') {
                    return false;
                }
                return true;
            })
            ->sum('amount');

        // NOTE: Excise Duty on M-Shwari is tracked separately as EXPENSE
        // It's NOT part of the loan amount, just a fee deducted upfront

        // ✅ CLIENT FUNDS RECEIVED - Liability (money from client)
        $clientFundsReceived = $activeTransactions
            ->filter(function($t) {
                if ($t->category->type !== 'liability') {
                    return false;
                }
                if ($t->category->name !== 'Client Funds') {
                    return false;
                }
                return $t->amount > 0; // Only positive (receipts)
            })
            ->sum('amount');

        // ✅ CLIENT FUNDS REDUCTION - Liability reduction (expenses/profits from client work)
        $clientFundsReduction = abs($activeTransactions
            ->filter(function($t) {
                if ($t->category->type !== 'liability') {
                    return false;
                }
                if ($t->category->name !== 'Client Funds') {
                    return false;
                }
                return $t->amount < 0; // Only negative (reductions)
            })
            ->sum('amount'));

        // ✅ EXPENSES - All expenses (including loan repayments, excise duty, etc)
        $totalExpenses = $activeTransactions
            ->filter(function($t) {
                if ($t->category->type !== 'expense') {
                    return false;
                }
                // Include ALL expenses: loan repayments, fees, client spending, regular expenses
                return true;
            })
            ->sum('amount');

        // ✅ BALANCE ADJUSTMENTS - Manual balance corrections
        $balanceAdjustments = $activeTransactions
            ->filter(function($t) {
                if ($t->category->name !== 'Balance Adjustment') {
                    return false;
                }
                // If positive, it's income; if negative, it's expense
                return true;
            })
            ->sum('amount');

        // ✅ TRANSFERS - Between your own accounts
        $transfersOut = $this->transfersFrom()->sum('amount');
        $transfersIn = $this->transfersTo()->sum('amount');

        // ✅ FINAL BALANCE CALCULATION
        $newBalance = (float) $this->initial_balance
            + $totalIncome              // Regular income (salary, commissions, etc)
            + $loanDisbursements        // Money you borrowed
            + $clientFundsReceived      // Money client gave you
            - $clientFundsReduction     // Already counted when client spending/profit recorded
            + $loanCredits              // Early repayment refunds
            - $totalExpenses            // All spending (includes loan repayments, excise, client spending)
            + $balanceAdjustments       // Manual adjustments
            - $transfersOut             // Money sent to other accounts
            + $transfersIn;             // Money received from other accounts

        \Log::info('UpdateBalance Calculation', [
            'account_id' => $this->id,
            'account_name' => $this->name,
            'initial_balance' => $this->initial_balance,
            'regular_income' => $totalIncome,
            'loan_disbursements' => $loanDisbursements,
            'client_funds_received' => $clientFundsReceived,
            'client_funds_reduction' => $clientFundsReduction,
            'loan_credits' => $loanCredits,
            'total_expenses' => $totalExpenses,
            'balance_adjustments' => $balanceAdjustments,
            'transfers_out' => $transfersOut,
            'transfers_in' => $transfersIn,
            'new_balance' => $newBalance,
            'total_transactions' => $activeTransactions->count(),
        ]);

        $this->current_balance = $newBalance;
        $this->save();
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
