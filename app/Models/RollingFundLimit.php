<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RollingFundLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'monthly_stake_limit',
        'single_stake_limit',
        'is_active',
    ];

    protected $casts = [
        'monthly_stake_limit' => 'decimal:2',
        'single_stake_limit'  => 'decimal:2',
        'is_active'           => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Current-month usage helpers ─────────────────────────────────────────

    /**
     * Total staked by the owner this calendar month.
     */
    public function monthlyStakedSoFar(): float
    {
        return (float) RollingFund::where('user_id', $this->user_id)
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->sum('stake_amount');
    }

    /**
     * How much of the monthly budget remains (null when no limit set).
     */
    public function monthlyRemaining(): ?float
    {
        if (! $this->monthly_stake_limit) {
            return null;
        }
        return max(0, (float) $this->monthly_stake_limit - $this->monthlyStakedSoFar());
    }

    /**
     * Percentage of monthly budget used (0-100). Null when no limit set.
     */
    public function monthlyUsagePercent(): ?float
    {
        if (! $this->monthly_stake_limit || $this->monthly_stake_limit == 0) {
            return null;
        }
        return min(100, round(($this->monthlyStakedSoFar() / (float) $this->monthly_stake_limit) * 100, 1));
    }

    // ─── Guard helpers (used in controller) ──────────────────────────────────

    /**
     * Returns 'blocked', 'warning', or 'ok' for a proposed stake amount.
     */
    public function checkStake(float $amount): string
    {
        if (! $this->is_active) {
            return 'ok';
        }

        // Single-stake check
        if ($this->single_stake_limit && $amount > (float) $this->single_stake_limit) {
            return 'blocked';
        }

        // Monthly budget check
        if ($this->monthly_stake_limit) {
            $used    = $this->monthlyStakedSoFar();
            $limit   = (float) $this->monthly_stake_limit;
            $newUsed = $used + $amount;

            if ($newUsed > $limit) {
                return 'blocked';
            }

            if ($newUsed >= $limit * 0.8) {
                return 'warning';
            }
        }

        return 'ok';
    }

    /**
     * Human-readable reason why a stake was blocked/warned.
     * Returns null when status is 'ok'.
     */
    public function checkMessage(float $amount): ?string
    {
        if (! $this->is_active) {
            return null;
        }

        if ($this->single_stake_limit && $amount > (float) $this->single_stake_limit) {
            return 'This stake of KES ' . number_format($amount, 0)
                . ' exceeds your single-session limit of KES '
                . number_format($this->single_stake_limit, 0) . '.';
        }

        if ($this->monthly_stake_limit) {
            $used    = $this->monthlyStakedSoFar();
            $limit   = (float) $this->monthly_stake_limit;
            $newUsed = $used + $amount;

            if ($newUsed > $limit) {
                return 'Adding KES ' . number_format($amount, 0)
                    . ' would exceed your monthly limit of KES '
                    . number_format($limit, 0)
                    . '. You have KES ' . number_format(max(0, $limit - $used), 0) . ' remaining.';
            }

            if ($newUsed >= $limit * 0.8) {
                $remaining = $limit - $newUsed;
                return 'Heads up — after this stake you\'ll have only KES '
                    . number_format($remaining, 0)
                    . ' left of your KES ' . number_format($limit, 0) . ' monthly limit.';
            }
        }

        return null;
    }
}
