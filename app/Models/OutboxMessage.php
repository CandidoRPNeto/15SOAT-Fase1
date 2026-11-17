<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $message_id
 * @property string $type
 * @property array<string, mixed> $body
 * @property int $attempts
 * @property Carbon|null $published_at
 */
class OutboxMessage extends Model
{
    protected $fillable = ['message_id', 'type', 'body', 'attempts', 'published_at'];

    protected function casts(): array
    {
        return ['body' => 'array', 'published_at' => 'datetime'];
    }
}
