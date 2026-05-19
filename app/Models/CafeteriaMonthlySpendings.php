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
    ];

    protected $casts = [
        'total_spent' => 'decimal:2',
        'limit' => 'decimal:2',
    ];

    // ===================== RELATIONSHIPS =====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ===================== SPENDING TRACKING =====================

    /**
     * Get remaining budget for this month
     */
    public function getRemainingBudget(): float
    {
        $remaining = $this->limit - $this->total_spent;
        return max(0, (float)$remaining);
    }

    /**
     * Get spending percentage (0-100)
     */
    public function getSpendingPercentage(): float
    {
        if ($this->limit <= 0) {
            return 0;
        }

        $percentage = ($this->total_spent / $this->limit) * 100;
        return min(100, $percentage); // Cap at 100%
    }

    /**
     * Check if over budget
     */
    public function isOverBudget(): bool
    {
        return $this->total_spent > $this->limit;
    }

    /**
     * Get amount over budget (0 if under)
     */
    public function getAmountOverBudget(): float
    {
        if (!$this->isOverBudget()) {
            return 0;
        }
        return (float)($this->total_spent - $this->limit);
    }

    /**
     * Get budget status as a string
     */
    public function getBudgetStatus(): string
    {
        $remaining = $this->getRemainingBudget();
        $limit = (float)$this->limit;
        $spent = (float)$this->total_spent;

        if ($spent == 0) {
            return "No spending this month";
        }

        if ($this->isOverBudget()) {
            $over = $this->getAmountOverBudget();
            return "Over budget by KES " . number_format($over, 0);
        }

        if ($remaining < $limit * 0.1) { // Less than 10% remaining
            return "Only KES " . number_format($remaining, 0) . " left";
        }

        return "KES " . number_format($remaining, 0) . " remaining";
    }

    /**
     * Get budget status color (for UI indicators)
     * green, yellow, orange, red
     */
    public function getBudgetStatusColor(): string
    {
        $percentage = $this->getSpendingPercentage();

        if ($percentage >= 100) {
            return 'red';
        }

        if ($percentage >= 90) {
            return 'orange';
        }

        if ($percentage >= 75) {
            return 'yellow';
        }

        return 'green';
    }

    /**
     * Check if budget is critical (90%+ spent)
     */
    public function isCritical(): bool
    {
        return $this->getSpendingPercentage() >= 90;
    }

    /**
     * Check if budget is warning level (75%+ spent)
     */
    public function isWarning(): bool
    {
        return $this->getSpendingPercentage() >= 75 && !$this->isCritical();
    }

    /**
     * Add spending to this month's total
     */
    public function addSpending(float $amount): void
    {
        $this->total_spent += $amount;
        $this->save();
    }

    /**
     * Subtract spending from this month's total (for refunds/deletions)
     */
    public function subtractSpending(float $amount): void
    {
        $this->total_spent = max(0, $this->total_spent - $amount);
        $this->save();
    }

    /**
     * Get the month name with year
     */
    public function getMonthYearAttribute(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    /**
     * Get the start date of this month
     */
    public function getMonthStartAttribute(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1);
    }

    /**
     * Get the end date of this month
     */
    public function getMonthEndAttribute(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->endOfMonth();
    }
}
