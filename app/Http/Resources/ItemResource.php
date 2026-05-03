<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'part_number' => $this->part_number,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'price' => $this->price,
            'stock_quantity' => $this->stock_quantity,
            'active' => $this->active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
