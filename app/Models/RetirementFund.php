<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
}
