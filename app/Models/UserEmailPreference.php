<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class UserEmailPreference extends Model
{
    protected $fillable = [
        'user_id',
        'annual_reports',
        'monthly_reports',
        'monthly_day',
        'preferred_time',
        'include_pdf',
        'include_charts',
        'custom_date_ranges',
        'last_annual_sent',
        'last_monthly_sent',
    ];
    protected $casts = [
        'annual_reports' => 'boolean',
        'monthly_reports' => 'boolean',
        'include_pdf' => 'boolean',
        'include_charts' => 'boolean',
        'custom_date_ranges' => 'array',
        'last_annual_sent' => 'datetime',
        'last_monthly_sent' => 'datetime',
        'preferred_time' => 'datetime',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
