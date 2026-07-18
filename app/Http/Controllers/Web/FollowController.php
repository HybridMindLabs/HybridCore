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
        $follower = $request->user();

        abort_if($follower->id === $user->id, 422, 'You cannot follow yourself.');
        abort_if($user->isBanned(), 404);

        // A block cuts the relationship both ways: neither party can follow the
        // other. Checked in both directions so blocking someone also stops them
        // from following you, not just you from following them.
        if ($follower->hasBlocked($user->id) || $follower->isBlockedBy($user->id)) {
            return back()->withErrors(['follow' => __('account.follow_blocked')]);
        }

        $wasNew = ! $follower->following()->where('followed_id', $user->id)->exists();

        $follower->following()->syncWithoutDetaching([$user->id]);

        if ($wasNew) {
            $user->notify(new NewFollowerNotification($follower));
            app(HookRegistry::class)->fire(Hooks::USER_FOLLOWED, $follower, $user);
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
