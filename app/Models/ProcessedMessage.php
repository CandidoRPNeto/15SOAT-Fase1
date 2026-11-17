<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedMessage extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'message_id';

    protected $keyType = 'string';

    protected $fillable = ['message_id', 'type', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
