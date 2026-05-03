<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'avg_execution_minutes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'avg_execution_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function serviceOrders(): BelongsToMany
    {
        return $this->belongsToMany(ServiceOrder::class, 'service_order_services')
            ->withPivot(['quantity', 'unit_price'])
            ->withTimestamps();
    }
}
