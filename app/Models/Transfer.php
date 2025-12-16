<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'amount',
        'date',
        'description',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
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


    public function fromAccount()
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }
}
