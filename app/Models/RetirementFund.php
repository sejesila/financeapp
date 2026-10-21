<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RetirementFund extends Model
{
    protected $fillable = [
        'user_id', 'name', 'type', 'opening_balance',
        'opening_balance_date', 'notes', 'is_active',
    ];

    protected $casts = [
        'opening_balance'      => 'decimal:2',
        'opening_balance_date' => 'date',
        'is_active'            => 'boolean',
    ];

    public const SECTIONS = [
        'nssf'      => ['tier1' => 'Tier I'],
        'provident' => ['tier2' => 'Tier II (protected rights)', 'main' => 'Main'],
    ];

    /** Employer contribution as a multiple of the employee contribution. */
    public const EMPLOYER_MULTIPLIER = [
        'nssf'      => ['tier1' => 1],
        'provident' => ['tier2' => 1, 'main' => 2],
    ];

    public function employerMultiplier(string $section): int
    {
        return self::EMPLOYER_MULTIPLIER[$this->type][$section] ?? 1;
    }

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable() . '.user_id', Auth::id());
            }
        });

        // Generate the slug when a fund is created.
        static::creating(function ($fund) {
            $fund->slug = static::uniqueSlug($fund->name, $fund->user_id);
        });
    }

    /** Use the slug instead of the id in URLs. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function uniqueSlug(string $name, int $userId): string
    {
        $base = Str::slug($name) ?: 'fund';
        $slug = $base;
        $i    = 2;

        while (static::withoutGlobalScopes()->where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }


    public function entries()
    {
        return $this->hasMany(RetirementEntry::class);
    }

    public function sections(): array
    {
        return self::SECTIONS[$this->type] ?? [];
    }

    public function balance(): float
    {
        $added = $this->entries()
            ->selectRaw('COALESCE(SUM(employee_amount + employer_amount + voluntary_amount + interest_amount), 0) as t')
            ->value('t');

        return (float) $this->opening_balance + (float) $added;
    }

    /** Totals since tracking started (excludes the opening balance). */
    public function totals(): object
    {
        return $this->entries()->selectRaw('
            COALESCE(SUM(employee_amount), 0)  as employee,
            COALESCE(SUM(employer_amount), 0)  as employer,
            COALESCE(SUM(voluntary_amount), 0) as voluntary,
            COALESCE(SUM(interest_amount), 0)  as interest
        ')->first();
    }
    /** Last completed month, or null if the opening balance already covers it. */
    public function recordableMonth(): ?Carbon
    {
        $last = now()->subMonthNoOverflow()->startOfMonth();

        if ($this->opening_balance_date
            && $last->lte($this->opening_balance_date->copy()->startOfMonth())) {
            return null;
        }

        return $last;
    }

    /** True if any section is still missing last month's payslip figures. */
    public function hasPendingFigures(): bool
    {
        $month = $this->recordableMonth();
        if (! $month) {
            return false;
        }

        $recorded = $this->entries()
            ->where('entry_type', 'contribution')
            ->whereDate('period_date', $month->toDateString())
            ->pluck('section')
            ->all();

        return count(array_diff(array_keys($this->sections()), $recorded)) > 0;
    }

    /** True in Feb–Apr if any section is missing the previous year's interest. */
    public function hasPendingInterest(): bool
    {
        if (! in_array(now()->month, [2, 3, 4], true)) {
            return false;
        }

        $recorded = $this->entries()
            ->where('entry_type', 'interest')
            ->whereYear('period_date', now()->year - 1)
            ->pluck('section')
            ->all();

        return count(array_diff(array_keys($this->sections()), $recorded)) > 0;
    }

    /** Show the dashboard button on days 1–10, only while something is left to record. */
    public static function shouldShowButton(): bool
    {
        if (now()->day > 10) {
            return false;
        }

        $funds = static::where('is_active', true)->get();

        // No funds yet: show it so you can set them up.
        if ($funds->isEmpty()) {
            return true;
        }

        return $funds->contains(fn ($f) => $f->hasPendingFigures() || $f->hasPendingInterest());
    }
}
