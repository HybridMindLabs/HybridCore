<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use App\Notifications\NewFollowerNotification;
use App\Services\Extensions\Registries\OnboardingStepRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        if (auth()->user()->hasCompletedOnboarding()) {
            return redirect()->route('home');
        }
        $games = class_exists(Game::class) ? Game::orderBy('name')->get(['id', 'name', 'icon', 'color']) : collect();

        return Inertia::render('Onboarding/Welcome', [
            'games' => $games,
            'suggestedMembers' => $this->suggestedMembers(),
            'extensionSteps' => app(OnboardingStepRegistry::class)->compose(),
        ]);
    }

    /** Recently-active members worth following, excluding the new user. */
    private function suggestedMembers(): array
    {
        return User::whereNull('banned_at')
            ->whereNotNull('username')
            ->where('id', '!=', auth()->id())
            ->orderByDesc('last_seen_at')
            ->limit(9)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'name' => $u->name,
                'avatar' => $u->avatar,
                'is_online' => $u->isOnline(),
            ])
            ->all();
    }

    public function complete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:100'],
            'favourite_games' => ['nullable', 'array'],
            'favourite_games.*' => ['integer', 'exists:games,id'],
            'follow_users' => ['nullable', 'array', 'max:9'],
            'follow_users.*' => ['integer', 'exists:users,id'],
        ]);

        $user = auth()->user();
        $user->update([
            'display_name' => $data['display_name'] ?? null,
            'bio' => $data['bio'] ?? null,
            'location' => $data['location'] ?? null,
            'onboarding_completed_at' => now(),
        ]);

        if (! empty($data['favourite_games'])) {
            $user->preferredGames()->sync($data['favourite_games']);
        }

        if (! empty($data['follow_users'])) {
            $targets = User::whereIn('id', $data['follow_users'])
                ->whereNull('banned_at')
                ->where('id', '!=', $user->id)
                ->get();

            foreach ($targets as $target) {
                $user->following()->syncWithoutDetaching([$target->id]);
                $target->notify(new NewFollowerNotification($user));
            }
        }

        return redirect()->route('home')->with('success', 'Welcome to '.config('app.name').'!');
    }
}
