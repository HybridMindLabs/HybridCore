<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => class_basename($this->type),
            'data' => $this->data,
            'read' => (bool) $this->read_at,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
