<?php

namespace App\Models;

use App\Domain\Saga\SagaStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $service_order_id
 * @property SagaStatus $status
 * @property string $correlation_id
 * @property string|null $failure_reason
 */
class SagaInstance extends Model
{
    use HasUuids;

    protected $fillable = ['service_order_id', 'status', 'correlation_id', 'failure_reason'];

    protected function casts(): array
    {
        return ['status' => SagaStatus::class];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SagaStep::class)->orderBy('id');
    }
}
