<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CafeteriaMenuItem extends Model
{
    protected $fillable = [
        'name',
        'category',
        'unit_price',
        'is_active',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static array $categories = [
        'main_dish' => 'Main Dish',
        'snack' => 'Snack',
        'beverage' => 'Beverage',
        'soft_drink' => 'Soft Drink',
        'fruit' => 'Fruit',
        'other' => 'Other',
    ];

    public static array $categoryIcons = [
        'main_dish' => '🍽️',
        'snack' => '🥪',
        'beverage' => '☕',
        'soft_drink' => '🥤',
        'fruit' => '🍎',
        'other' => '📦',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(CafeteriaOrderItem::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::$categories[$this->category] ?? ucfirst($this->category);
    }

    public function getCategoryIconAttribute(): string
    {
        return self::$categoryIcons[$this->category] ?? '📦';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
