<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'client' => new UserResource($this->whenLoaded('client', $this->client)),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle', $this->vehicle)),
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            'budget_sent_at' => $this->budget_sent_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'finalized_at' => $this->finalized_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'parts' => PartResource::collection($this->whenLoaded('parts')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
