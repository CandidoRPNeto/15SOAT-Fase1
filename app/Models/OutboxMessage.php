<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $message_id
 * @property string $type
 * @property array<string, mixed> $body
 * @property array<string, string>|null $trace_context
 * @property int $attempts
 * @property Carbon|null $published_at
 */
class OutboxMessage extends Model
{
    protected $fillable = ['message_id', 'type', 'body', 'trace_context', 'attempts', 'published_at'];

    protected function casts(): array
    {
        return ['body' => 'array', 'trace_context' => 'array', 'published_at' => 'datetime'];
    }
}
