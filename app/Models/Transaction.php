<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Transaction extends Model
{
    protected $fillable = [
        'date',
        'description',
        'amount',
        'payment_method',
        'category_id',
        'account_id',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.user_id", Auth::id());
            }
        });
    }


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
