<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CafeteriaMonthlySpendings extends Model
{
    protected $table = 'cafeteria_monthly_spendings';

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'total_spent',
        'limit',
        'carryover',
    ];

    protected $casts = [
        'total_spent' => 'decimal:2',
        'limit'       => 'decimal:2',
        'carryover'   => 'decimal:2',
    ];

    // ===================== RELATIONSHIPS =====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ===================== EFFECTIVE LIMIT =====================

    /**
     * The limit the user actually has this month after carryover is applied.
     *
     * Examples:
     *   limit=10000, carryover=+3000  →  effectiveLimit = 13,000  (had surplus last month)
     *   limit=10000, carryover=-2000  →  effectiveLimit =  8,000  (overspent last month)
     *   limit=10000, carryover=0      →  effectiveLimit = 10,000  (first month / exact spend)
     *
     * Never goes below 0.
     */
    public function getEffectiveLimitAttribute(): float
    {
        return max(0.0, (float) $this->limit + (float) $this->carryover);
    }

    // ===================== SPENDING TRACKING =====================

    public function getRemainingBudget(): float
    {
        return max(0.0, $this->effective_limit - (float) $this->total_spent);
    }

    /**
     * Spending percentage against effective limit, capped at 100.
     */
    public function getSpendingPercentage(): float
    {
        if ($this->effective_limit <= 0) {
            return 100.0;
        }

        return min(100.0, ((float) $this->total_spent / $this->effective_limit) * 100);
    }

    public function isOverBudget(): bool
    {
        return (float) $this->total_spent > $this->effective_limit;
    }

    public function getAmountOverBudget(): float
    {
        return $this->isOverBudget()
            ? (float) $this->total_spent - $this->effective_limit
            : 0.0;
    }

    public function getBudgetStatus(): string
    {
        $spent     = (float) $this->total_spent;
        $remaining = $this->getRemainingBudget();
        $limit     = $this->effective_limit;

        if ($spent == 0) {
            return 'No spending this month';
        }

        if ($this->isOverBudget()) {
            return 'Over budget by KES ' . number_format($this->getAmountOverBudget(), 0);
        }

        if ($remaining < $limit * 0.1) {
            return 'Only KES ' . number_format($remaining, 0) . ' left';
        }

        return 'KES ' . number_format($remaining, 0) . ' remaining';
    }

    public function getBudgetStatusColor(): string
    {
        return match (true) {
            $this->getSpendingPercentage() >= 100 => 'red',
            $this->getSpendingPercentage() >= 90  => 'orange',
            $this->getSpendingPercentage() >= 75  => 'yellow',
            default                               => 'green',
        };
    }

    public function isCritical(): bool
    {
        return $this->getSpendingPercentage() >= 90;
    }

    public function isWarning(): bool
    {
        return $this->getSpendingPercentage() >= 75 && !$this->isCritical();
    }

    // ===================== MUTATION HELPERS =====================

    public function addSpending(float $amount): void
    {
        $this->increment('total_spent', $amount);
    }

    public function subtractSpending(float $amount): void
    {
        $this->total_spent = max(0.0, (float) $this->total_spent - $amount);
        $this->save();
    }

    // ===================== CARRYOVER =====================

    /**
     * Compute the signed value that should be stored as `carryover` on the
     * *next* month's record when it is first created.
     *
     *   result > 0  →  surplus (spent less than effective limit) → next month gets more
     *   result < 0  →  deficit (overspent)                       → next month gets less
     *   result = 0  →  exactly on budget
     */
    public function computeCarryoverForNextMonth(): float
    {
        return $this->effective_limit - (float) $this->total_spent;
    }

    // ===================== DATE HELPERS =====================

    public function getMonthYearAttribute(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function getMonthStartAttribute(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1);
    }

    public function getMonthEndAttribute(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->endOfMonth();
    }
}
