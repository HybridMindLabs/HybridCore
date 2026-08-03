<?php

namespace App\Http\Resources\Api;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $role = $user->roles->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'location' => $user->location,
            'role' => $role ? ['name' => $role->name, 'color' => $role->color] : null,
            'verified' => $user->hasVerifiedEmail(),
            'is_online' => $user->isOnline(),
            'joined_at' => $user->created_at->toIso8601String(),
        ];
    }
}
