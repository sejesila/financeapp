<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class CafeteriaOrder extends Model
{
    protected $fillable = [
        'user_id',
        'order_date',
        'meal_time',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public static array $mealTimes = [
        'breakfast' => 'Breakfast',
        'lunch' => 'Lunch',
        'dinner' => 'Dinner',
        'snack' => 'Snack',
    ];

    public static array $mealTimeIcons = [
        'breakfast' => '🌅',
        'lunch' => '☀️',
        'dinner' => '🌙',
        'snack' => '🍿',
    ];

    // ===================== RELATIONSHIPS =====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CafeteriaOrderItem::class);
    }

    // ===================== UTILITY METHODS =====================

    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('subtotal');
        $this->save();
    }

    public function getMealTimeLabelAttribute(): string
    {
        return self::$mealTimes[$this->meal_time] ?? ucfirst($this->meal_time);
    }

    public function getMealTimeIconAttribute(): string
    {
        return self::$mealTimeIcons[$this->meal_time] ?? '🍽️';
    }

    // ===================== EDIT WINDOW METHODS =====================

    /**
     * Check if the order is still editable (within 1 hour of creation)
     *
     * @return bool
     */
    public function isEditable(): bool
    {
        $createdAt = $this->created_at;
        $oneHourAgo = Carbon::now()->subHour();

        return $createdAt->isAfter($oneHourAgo);
    }

    /**
     * Get time remaining until the order becomes read-only (in minutes)
     *
     * @return int Minutes remaining, or 0 if already expired
     */
    public function getEditTimeRemainingMinutes(): int
    {
        $createdAt = $this->created_at;
        $oneHourAfter = $createdAt->addHour();
        $now = Carbon::now();

        if ($now->isAfter($oneHourAfter)) {
            return 0;
        }

        return $oneHourAfter->diffInMinutes($now);
    }

    /**
     * Get a human-readable string of time remaining
     *
     * Examples: "45m remaining", "1h 15m remaining", "Read-only"
     *
     * @return string
     */
    public function getEditTimeRemainingFormatted(): string
    {
        $minutes = $this->getEditTimeRemainingMinutes();

        if ($minutes === 0) {
            return 'Read-only';
        }

        if ($minutes > 60) {
            $hours = (int)floor($minutes / 60);
            $mins = $minutes % 60;
            return $mins > 0 ? "{$hours}h {$mins}m remaining" : "{$hours}h remaining";
        }

        return "{$minutes}m remaining";
    }

    /**
     * Get the deadline timestamp when this order becomes read-only
     *
     * @return Carbon
     */
    public function getEditDeadline(): Carbon
    {
        return $this->created_at->addHour();
    }

    /**
     * Check if the order is past its edit deadline
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return !$this->isEditable();
    }

    /**
     * Get percentage of edit time used (0-100)
     *
     * Useful for progress bars or visual indicators
     *
     * @return float
     */
    public function getEditTimePercentageUsed(): float
    {
        $createdAt = $this->created_at;
        $oneHourAfter = $createdAt->addHour();
        $now = Carbon::now();

        // If already expired, return 100%
        if ($now->isAfter($oneHourAfter)) {
            return 100.0;
        }

        $totalMinutes = 60;
        $minutesUsed = $createdAt->diffInMinutes($now);

        return round(($minutesUsed / $totalMinutes) * 100, 2);
    }

    /**
     * Get percentage of edit time remaining (0-100)
     *
     * Useful for progress bars or visual indicators
     *
     * @return float
     */
    public function getEditTimePercentageRemaining(): float
    {
        return 100.0 - $this->getEditTimePercentageUsed();
    }
}
