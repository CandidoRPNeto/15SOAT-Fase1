<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'service_order_status_history';

    protected $fillable = ['service_order_id', 'from_status', 'to_status', 'reason'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
