<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request, string $username): UserResource
    {
        $user = User::with('roles')
            ->where('username', $username)
            ->firstOrFail();

        // Mirror the web profile's privacy rules — without this the API is a
        // way around a "private" or "members-only" profile. The route carries
        // no auth middleware, so read the token through the sanctum guard
        // explicitly: it identifies the viewer when a bearer token is sent and
        // is null on an anonymous call.
        $viewer = $request->user('sanctum');
        $privacy = $user->profile_privacy ?? 'public';
        $isSelf = $viewer !== null && $viewer->id === $user->id;

        abort_if($privacy === 'private' && ! $isSelf, 404);
        abort_if($privacy === 'members' && $viewer === null, 404);

        return new UserResource($user);
    }
}
