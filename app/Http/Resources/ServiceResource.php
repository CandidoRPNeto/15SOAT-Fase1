<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'avg_execution_minutes' => $this->avg_execution_minutes,
            'active' => $this->active,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type->value,
                    'quantity' => $item->pivot->quantity,
                ]);
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
