<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'order_number'     => $this->order_number,
            'status'           => $this->status,
            'status_label'     => ucfirst($this->status),
            'total_amount'     => (float) $this->total_amount,
            'notes'            => $this->notes,
            'shipping_address' => $this->shipping_address,
            'customer_id'      => $this->customer_id,
            'customer'         => new CustomerResource($this->whenLoaded('customer')),
            'items'            => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count'      => $this->whenCounted('items'),
            'created_at'       => optional($this->created_at)->toIso8601String(),
            'updated_at'       => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
