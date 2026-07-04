<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;

class UserController extends Controller
{
    public function show(string $username): UserResource
    {
        $user = User::with('roles')
            ->where('username', $username)
            ->firstOrFail();

        return new UserResource($user);
    }
}
