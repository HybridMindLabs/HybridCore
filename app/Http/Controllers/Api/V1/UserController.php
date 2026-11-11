<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\ServiceAccount;
use App\Models\User;
use App\Services\Auth\ConnectedAccountService;
use Illuminate\Http\JsonResponse;
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

    /**
     * For integrations like the Discord bot. Admin-issued tokens only: a
     * member's own token carries every ability, and must not be able to
     * map Discord IDs to accounts.
     */
    public function showByDiscord(Request $request, ConnectedAccountService $accounts, string $discordId): JsonResponse
    {
        abort_unless(auth('sanctum')->user() instanceof ServiceAccount, 403);

        $user = $accounts->findUser('discord', $discordId);

        abort_if($user === null, 404);

        $user->load('roles');

        return response()->json(['data' => [
            ...(new UserResource($user))->resolve($request),
            'roles' => $user->roles->map(fn ($role) => ['name' => $role->name, 'color' => $role->color])->values(),
            'is_banned' => $user->isBanned(),
        ]]);
    }
}
