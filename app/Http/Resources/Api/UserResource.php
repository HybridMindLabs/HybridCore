<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'location' => $this->location,
            'role' => $this->roles->first()
                ? ['name' => $this->roles->first()->name, 'color' => $this->roles->first()->color]
                : null,
            'verified' => $this->hasVerifiedEmail(),
            'is_online' => $this->isOnline(),
            'joined_at' => $this->created_at->toIso8601String(),
        ];
    }
}
