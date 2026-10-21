<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SaccoEntry extends Model
{
    protected $fillable = [
        'sacco_id', 'user_id', 'ledger', 'kind', 'period_date', 'amount', 'description', 'slot',
    ];

    protected $casts = [
        'period_date' => 'date',
        'amount'      => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable() . '.user_id', Auth::id());
            }
        });
    }

    public function sacco()
    {
        return $this->belongsTo(Sacco::class);
    }
}
