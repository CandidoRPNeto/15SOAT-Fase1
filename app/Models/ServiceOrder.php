<?php

namespace App\Models;

use App\Enums\ServiceOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'client_id',
        'vehicle_id',
        'status',
        'total_amount',
        'notes',
        'budget_sent_at',
        'paid_at',
        'finalized_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceOrderStatus::class,
            'total_amount' => 'decimal:2',
            'budget_sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'finalized_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceOrder $order) {
            if (empty($order->number)) {
                $order->number = static::generateNumber();
            }
        });
    }

    public static function generateNumber(): string
    {
        $year = now()->format('Y');
        $last = static::whereYear('created_at', $year)->count();
        return sprintf('OS-%s-%05d', $year, $last + 1);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function orderServices(): HasMany
    {
        return $this->hasMany(ServiceOrderService::class);
    }

    public function orderParts(): HasMany
    {
        return $this->hasMany(ServiceOrderPart::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_order_services')
            ->withPivot(['quantity', 'unit_price'])
            ->withTimestamps();
    }

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'service_order_parts')
            ->withPivot(['quantity', 'unit_price'])
            ->withTimestamps();
    }

    public function calculateTotal(): float
    {
        $servicesTotal = $this->orderServices->sum(
            fn ($item) => $item->unit_price * $item->quantity
        );

        $partsTotal = $this->orderParts->sum(
            fn ($item) => $item->unit_price * $item->quantity
        );

        return round($servicesTotal + $partsTotal, 2);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isFinalized(): bool
    {
        return $this->status === ServiceOrderStatus::FINALIZED;
    }

    public function isDeliverable(): bool
    {
        return $this->status === ServiceOrderStatus::FINALIZED && $this->isPaid();
    }

    public function hoursSinceFinalized(): ?float
    {
        if ($this->finalized_at === null) {
            return null;
        }

        return $this->finalized_at->diffInHours(now());
    }
}
