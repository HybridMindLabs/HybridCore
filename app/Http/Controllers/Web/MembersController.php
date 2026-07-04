<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class MembersController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $members = User::query()
            ->with(['roles' => fn ($q) => $q->wherePivot('is_primary', true), 'achievements'])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            }))
            ->whereNull('banned_at')
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'banner' => $user->banner,
                'bio' => $user->bio,
                'location' => $user->location,
                'role' => $user->roles->first() ? [
                    'name' => $user->roles->first()->name,
                    'color' => $user->roles->first()->color,
                    'icon' => $user->roles->first()->icon,
                ] : null,
                'joined_at' => $user->created_at->format('M Y'),
                'verified' => $user->hasVerifiedEmail(),
                'is_online' => $user->isOnline(),
                'achievements' => $user->achievements->pluck('slug'),
            ]);

        return Inertia::render('Web/Members', [
            'members' => $members,
            'filters' => ['search' => $search],
            'total' => Cache::remember('members.total', 60, fn () => User::whereNull('banned_at')->count()),
            'online_count' => Cache::remember('members.online_count', 60, fn () => User::whereNull('banned_at')
                ->where('last_seen_at', '>=', now()->subMinutes(5))
                ->count()),
        ]);
    }
}
