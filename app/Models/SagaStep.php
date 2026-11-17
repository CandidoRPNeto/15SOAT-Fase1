<?php

namespace App\Models;

use App\Domain\Saga\SagaStepName;
use App\Domain\Saga\SagaStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $saga_instance_id
 * @property SagaStepName $name
 * @property SagaStepStatus $status
 * @property string|null $command_type
 * @property array<string, mixed>|null $command_payload
 * @property int $attempts
 * @property array<string, mixed>|null $detail
 * @property Carbon|null $sent_at
 */
class SagaStep extends Model
{
    protected $fillable = [
        'saga_instance_id', 'name', 'status', 'command_type', 'command_payload', 'attempts', 'detail', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'name' => SagaStepName::class,
            'status' => SagaStepStatus::class,
            'command_payload' => 'array',
            'detail' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function sagaInstance(): BelongsTo
    {
        return $this->belongsTo(SagaInstance::class);
    }
}
