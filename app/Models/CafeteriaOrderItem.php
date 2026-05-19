<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CafeteriaOrderItem extends Model
{
    protected $fillable = [
        'cafeteria_order_id',
        'cafeteria_menu_item_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CafeteriaOrder::class, 'cafeteria_order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(CafeteriaMenuItem::class, 'cafeteria_menu_item_id');
    }
}
