<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlockController extends Controller
{
    public function index(Request $request): Response
    {
        $blocks = UserBlock::with('blocked')
            ->where('blocker_id', $request->user()->id)
            ->latest('created_at')
            ->get()
            ->map(fn (UserBlock $b) => [
                'id' => $b->id,
                'user' => [
                    'id' => $b->blocked->id,
                    'username' => $b->blocked->username,
                    'display_name' => $b->blocked->display_name ?: $b->blocked->username,
                    'avatar' => $b->blocked->avatar,
                ],
                'blocked_at' => $b->created_at->toFormattedDateString(),
            ]);

        return Inertia::render('Account/BlockedUsers', ['blocks' => $blocks]);
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        $blocker = $request->user();

        if ($blocker->id === $user->id) {
            return back()->withErrors(['user' => __('account.block_self')]);
        }

        UserBlock::firstOrCreate([
            'blocker_id' => $blocker->id,
            'blocked_id' => $user->id,
        ]);

        // Blocking someone who already follows you (or whom you follow) has to
        // break the existing link too — otherwise the block only stops future
        // follows and the old one silently survives.
        $blocker->following()->detach($user->id);
        $blocker->followers()->detach($user->id);

        return back()->with('success', __('account.user_blocked', ['name' => $user->username]));
    }

    public function unblock(Request $request, User $user): RedirectResponse
    {
        UserBlock::where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user->id)
            ->delete();

        return back()->with('success', __('account.user_unblocked', ['name' => $user->username]));
    }
}
