<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'cafeteria_monthly_limit',
        'cafeteria_limit_updated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'          => 'datetime',
            'cafeteria_limit_updated_at' => 'datetime',
            'password'                   => 'hashed',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function emailPreference(): HasOne
    {
        return $this->hasOne(UserEmailPreference::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Relationship: Get monthly spendings records
     *
     * Usage: $user->cafeteriaMonthlySpendings()->get()
     */
    public function cafeteriaMonthlySpendings(): HasMany
    {
        return $this->hasMany(CafeteriaMonthlySpendings::class);
    }

    // -------------------------------------------------------------------------
    // Model Events
    // -------------------------------------------------------------------------

    protected static function booted()
    {
        static::created(function ($user) {
            $user->emailPreference()->create([
                'monthly_reports' => true,
                'annual_reports'  => true,
                'monthly_day'     => 1,
                'preferred_time'  => '08:00:00',
                'include_pdf'     => true,
                'include_charts'  => false,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Budget Limit Editing
    // -------------------------------------------------------------------------

    /**
     * Check whether the user is allowed to edit their monthly limit.
     * Editing is permitted when:
     *   - the limit has never been changed (cafeteria_limit_updated_at is null), OR
     *   - the last change happened in a previous calendar month.
     *
     * Usage: if ($user->canEditMonthlyLimit()) { ... }
     * Returns: bool
     */
    public function canEditMonthlyLimit(): bool
    {
        if (is_null($this->cafeteria_limit_updated_at)) {
            return true; // first-time setup — always allowed
        }

        $now      = Carbon::now();
        $lastEdit = $this->cafeteria_limit_updated_at;

        return $lastEdit->year < $now->year
            || ($lastEdit->year === $now->year && $lastEdit->month < $now->month);
    }

    /**
     * Safely update the monthly limit, stamping the change timestamp.
     * Throws a RuntimeException when the user has already edited this month.
     *
     * Usage: $user->updateMonthlyLimit(15000);
     */
    public function updateMonthlyLimit(float $newLimit): void
    {
        if (!$this->canEditMonthlyLimit()) {
            throw new \RuntimeException('Monthly budget limit can only be changed once per calendar month.');
        }

        $this->update([
            'cafeteria_monthly_limit'    => $newLimit,
            'cafeteria_limit_updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Return the date the user may next edit their limit, or null if editing is available now.
     *
     * Usage: $user->nextLimitEditAllowedAt()
     * Example return: "1 Jun 2025"
     */
    public function nextLimitEditAllowedAt(): ?string
    {
        if ($this->canEditMonthlyLimit()) {
            return null;
        }

        return Carbon::now()->addMonthNoOverflow()->startOfMonth()->format('j M Y');
    }

    // -------------------------------------------------------------------------
    // Working-Days Helpers
    // -------------------------------------------------------------------------

    /**
     * Count working days (Mon–Fri) remaining in the current month,
     * including today if today is itself a weekday.
     *
     * Usage: $user->workingDaysRemainingThisMonth()
     * Returns: int
     */
    public function workingDaysRemainingThisMonth(): int
    {
        $today   = Carbon::today();
        $lastDay = $today->copy()->endOfMonth();
        $count   = 0;

        for ($d = $today->copy(); $d->lte($lastDay); $d->addDay()) {
            if ($d->isWeekday()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count working days (Mon–Fri) elapsed so far this month,
     * including today if today is a weekday. Minimum 1 to avoid division by zero.
     *
     * Usage: $user->workingDaysElapsedThisMonth()
     * Returns: int
     */
    public function workingDaysElapsedThisMonth(): int
    {
        $firstDay = Carbon::today()->startOfMonth();
        $today    = Carbon::today();
        $count    = 0;

        for ($d = $firstDay->copy(); $d->lte($today); $d->addDay()) {
            if ($d->isWeekday()) {
                $count++;
            }
        }

        return max(1, $count);
    }

    // -------------------------------------------------------------------------
    // Spending Limit Methods
    // -------------------------------------------------------------------------

    /**
     * Get or create the current month's spending record.
     *
     * Usage: $user->getCurrentMonthlySpendings()
     */
    public function getCurrentMonthlySpendings(): CafeteriaMonthlySpendings
    {
        $now = Carbon::now();

        return CafeteriaMonthlySpendings::firstOrCreate(
            [
                'user_id' => $this->id,
                'year'    => $now->year,
                'month'   => $now->month,
            ],
            [
                'total_spent' => 0,
                'limit'       => $this->cafeteria_monthly_limit,
            ]
        );
    }

    /**
     * Get total spent this month.
     *
     * Usage: $user->getTotalSpentThisMonth()
     * Returns: float
     */
    public function getTotalSpentThisMonth(): float
    {
        return (float) $this->getCurrentMonthlySpendings()->total_spent;
    }

    /**
     * Get remaining budget for this month (0 if over budget).
     *
     * Usage: $user->getRemainingBudgetThisMonth()
     * Returns: float
     */
    public function getRemainingBudgetThisMonth(): float
    {
        return max(0, $this->cafeteria_monthly_limit - $this->getTotalSpentThisMonth());
    }

    /**
     * Get spending percentage capped at 100.
     *
     * Usage: $user->getSpendingPercentageThisMonth()
     * Returns: float
     */
    public function getSpendingPercentageThisMonth(): float
    {
        if ($this->cafeteria_monthly_limit <= 0) {
            return 0;
        }

        return min(100, ($this->getTotalSpentThisMonth() / $this->cafeteria_monthly_limit) * 100);
    }

    /**
     * Check if user has enough budget for a given amount.
     *
     * Usage: if ($user->hasEnoughBudget(500)) { ... }
     * Returns: bool
     */
    public function hasEnoughBudget(float $amount): bool
    {
        return $this->getRemainingBudgetThisMonth() >= $amount;
    }

    /**
     * Check if the user has exceeded their monthly limit.
     *
     * Usage: if ($user->hasExceededLimit()) { ... }
     * Returns: bool
     */
    public function hasExceededLimit(): bool
    {
        return $this->getTotalSpentThisMonth() > $this->cafeteria_monthly_limit;
    }

    /**
     * Get the amount over budget (0 if under budget).
     *
     * Usage: $user->getAmountOverBudget()
     * Returns: float
     */
    public function getAmountOverBudget(): float
    {
        return max(0, $this->getTotalSpentThisMonth() - $this->cafeteria_monthly_limit);
    }

    /**
     * Get budget status as a human-readable string.
     *
     * Usage: echo $user->getBudgetStatus()
     */
    public function getBudgetStatus(): string
    {
        $spent     = $this->getTotalSpentThisMonth();
        $limit     = $this->cafeteria_monthly_limit;
        $remaining = $this->getRemainingBudgetThisMonth();

        if ($spent == 0) {
            return 'No spending this month';
        }

        if ($spent >= $limit) {
            return 'Over budget by KES ' . number_format($spent - $limit, 0);
        }

        if ($remaining < $limit * 0.1) {
            return 'Only KES ' . number_format($remaining, 0) . ' left';
        }

        return 'KES ' . number_format($remaining, 0) . ' remaining';
    }

    /**
     * Get budget status colour for UI: 'green', 'yellow', 'orange', or 'red'.
     *
     * Usage: class="{{ $user->getBudgetStatusColor() }}-500"
     */
    public function getBudgetStatusColor(): string
    {
        $p = $this->getSpendingPercentageThisMonth();

        if ($p >= 100) return 'red';
        if ($p >= 90)  return 'orange';
        if ($p >= 75)  return 'yellow';

        return 'green';
    }

    /**
     * Check if budget is critical (90%+ spent).
     *
     * Usage: if ($user->isCriticalBudget()) { ... }
     */
    public function isCriticalBudget(): bool
    {
        return $this->getSpendingPercentageThisMonth() >= 90;
    }

    /**
     * Check if budget is at warning level (75%–89% spent).
     *
     * Usage: if ($user->isWarningBudget()) { ... }
     */
    public function isWarningBudget(): bool
    {
        $p = $this->getSpendingPercentageThisMonth();
        return $p >= 75 && $p < 90;
    }
}
