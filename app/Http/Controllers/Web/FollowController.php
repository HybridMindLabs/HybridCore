<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NewFollowerNotification;
use App\Services\AchievementService;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function __construct(private readonly AchievementService $achievements) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->id === $user->id, 422, 'You cannot follow yourself.');
        abort_if($user->isBanned(), 404);

        $wasNew = ! $request->user()->following()->where('followed_id', $user->id)->exists();

        $request->user()->following()->syncWithoutDetaching([$user->id]);

        if ($wasNew) {
            $user->notify(new NewFollowerNotification($request->user()));
            app(HookRegistry::class)->fire(Hooks::USER_FOLLOWED, $request->user(), $user);
        }

        // "popular" is based on follower count of the followed user.
        $this->achievements->check($user);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->user()->following()->detach($user->id);

        return back();
    }
}
