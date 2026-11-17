<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $service_order_id
 * @property int $item_id
 * @property int $quantity
 * @property Carbon|null $released_at
 */
class StockReservation extends Model
{
    protected $fillable = ['service_order_id', 'item_id', 'quantity', 'released_at'];

    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }
}
