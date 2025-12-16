<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Budget extends Model
{
    protected $fillable = [
        'category_id',
        'year',
        'month',
        'amount',
        'user_id',
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
}
