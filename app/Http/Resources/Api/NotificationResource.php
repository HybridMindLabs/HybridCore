<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->resource;

        return [
            'id' => $notification->id,
            'type' => class_basename($notification->type),
            'data' => $notification->data,
            'read' => (bool) $notification->read_at,
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}
