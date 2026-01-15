<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileMoneyTypeUsage extends Model
{
    protected $table = 'mobile_money_type_usage';

    protected $fillable = [
        'user_id',
        'account_type',
        'transaction_type',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the most used transaction type for a user and account type
     */
    public static function getMostUsedType(int $userId, string $accountType): ?string
    {
        return self::where('user_id', $userId)
            ->where('account_type', $accountType)
            ->orderByDesc('usage_count')
            ->value('transaction_type');
    }

    /**
     * Increment usage count for a transaction type
     */
    public static function incrementUsage(int $userId, string $accountType, string $transactionType): void
    {
        // Try to increment existing record
        $updated = self::where('user_id', $userId)
            ->where('account_type', $accountType)
            ->where('transaction_type', $transactionType)
            ->increment('usage_count');

        // If no record was updated, create a new one
        if ($updated === 0) {
            self::create([
                'user_id' => $userId,
                'account_type' => $accountType,
                'transaction_type' => $transactionType,
                'usage_count' => 1,
            ]);
        }
    }
}
