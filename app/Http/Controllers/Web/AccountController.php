<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Jobs\GenerateDataExportJob;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\Auth\LoginSecurityService;
use App\Services\Auth\OAuthProviderRegistry;
use App\Services\Auth\SessionSecurityService;
use App\Services\Localization\LocaleService;
use App\Services\Media\AvatarService;
use App\Services\Media\BannerService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function __construct(
        private readonly LoginSecurityService $security,
        private readonly OAuthProviderRegistry $oauth,
        private readonly AchievementService $achievements,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user()->load('achievements');
        // Resolved rather than newed up so its own dependencies get injected.
        $sessionCtrl = app(SessionController::class);

        return Inertia::render('Account/Index', [
            'account' => [
                'username' => $user->username,
                'display_name' => $user->getRawOriginal('display_name'),
                'email' => $user->email,
                'avatar' => $user->avatar,
                'banner' => $user->banner,
                'bio' => $user->bio,
                'location' => $user->location,
                'website' => $user->website,
                'profile_privacy' => $user->profile_privacy ?? 'public',
                'notification_preferences' => $user->notification_preferences ?? [],
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'favourite_games' => $user->preferredGames()->pluck('games.id')->all(),
                'verified' => $user->hasVerifiedEmail(),
                'created_at' => $user->created_at->toFormattedDateString(),
                'last_login_at' => $user->last_login_at?->diffForHumans(),
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'two_factor_recovery_codes' => $user->hasTwoFactorEnabled()
                    ? $user->two_factor_recovery_codes
                    : null,
                'can_change_username' => $user->canChangUsername(),
                'username_change_available_at' => $user->username_changed_at
                    ? $user->username_changed_at->addDays(
                        (int) app(SettingsService::class)->get('username_change_cooldown_days', 30)
                    )->toFormattedDateString()
                    : null,
            ],
            // Picking games used to happen in the post-registration wizard and
            // nowhere else, so anyone who skipped it could never set them.
            'games' => Game::orderBy('name')->get(['id', 'name', 'slug']),
            'sessions' => $sessionCtrl->index($request),
            'connectedAccounts' => $user->connectedAccounts->map(fn ($acc) => [
                'provider' => $acc->provider,
                'username' => $acc->provider_username,
                'avatar_url' => $acc->avatar_url,
                'connected_at' => $acc->created_at->toFormattedDateString(),
            ]),
            'oauthProviders' => $this->oauth->compose(),
            // Lets the page explain why unlinking the last provider is blocked
            // instead of just failing when the user clicks.
            'hasPassword' => $user->hasUsablePassword(),
            'achievements' => $user->achievements->map(fn ($a) => [
                'slug' => $a->slug,
                'awarded_at' => $a->awarded_at->toFormattedDateString(),
            ]),
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'unreadMessages' => $user->unreadMessagesCount(),
            'notifications' => [
                'data' => $user->notifications()->latest()->take(20)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'data' => $n->data,
                    'read' => (bool) $n->read_at,
                    'created_at' => $n->created_at->diffForHumans(),
                ]),
                'links' => [],
                'meta' => [],
            ],
            'blocks' => $user->blocks()
                ->with('blocked')
                ->latest()
                ->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'user' => [
                        'id' => $b->blocked->id,
                        'username' => $b->blocked->username,
                        'display_name' => $b->blocked->display_name,
                        'avatar' => $b->blocked->avatar,
                    ],
                    'blocked_at' => $b->created_at->toFormattedDateString(),
                ]),
            'loginHistory' => [
                'data' => $user->loginHistories()->latest()->take(20)->get()->map(fn ($h) => [
                    'id' => $h->id,
                    'ip' => $h->ip_address,
                    'user_agent' => $h->user_agent,
                    'country' => null,
                    'city' => null,
                    'at' => $h->created_at->diffForHumans(),
                    'at_full' => $h->created_at->toDateTimeString(),
                ]),
                'links' => [],
                'meta' => [],
            ],
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $emailChanged = $data['email'] !== $user->email;
        $usernameChanged = isset($data['username']) && $data['username'] !== $user->username;

        $user->fill($data);

        if ($usernameChanged) {
            $user->name = $data['username']; // keep name in sync for backward compat
            $user->username_changed_at = now();
        }

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();
        $this->achievements->check($user);

        if ($emailChanged && $this->security->emailVerificationRequired()) {
            $user->sendEmailVerificationNotification();

            return back()->with('success', __('account.profile_updated_verify'));
        }

        return back()->with('success', __('account.profile_updated'));
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'favourite_games' => ['nullable', 'array'],
            'favourite_games.*' => ['integer', 'exists:games,id'],
        ]);

        if (! empty($data['locale']) && ! app(LocaleService::class)->isSupported($data['locale'])) {
            return back()->withErrors(['locale' => __('messages.language_unavailable')]);
        }

        // This used to also write a list-shaped notification_preferences into
        // the same JSON column the Email Preferences page writes as a map — two
        // incompatible formats fighting over one column. The page never sent
        // the field, so the branch only ever stripped keys on save. Dropped.
        //
        // Empty select/input means "no preference", which is null; storing ''
        // would make the column look set when it is not. Nullable rules leave
        // absent fields out of $data entirely, so the key has to be guarded
        // before the emptiness check.
        $request->user()->update([
            'locale' => ($data['locale'] ?? null) ?: null,
            'timezone' => ($data['timezone'] ?? null) ?: null,
        ]);

        if (! empty($data['locale'])) {
            $request->session()->put('locale', $data['locale']);
        }

        // sync, not syncWithoutDetaching: clearing every box has to mean the
        // user wants no game filter, not that the save was ignored. The key is
        // only present when the form sent it, so an unrelated save is safe.
        if (array_key_exists('favourite_games', $data)) {
            $request->user()->preferredGames()->sync($data['favourite_games'] ?? []);
        }

        return back()->with('success', __('account.preferences_updated'));
    }

    public function updateEmailPreferences(Request $request): RedirectResponse
    {
        // Only categories that have a sender behind them. email_notifications
        // and email_announcements were offered here too, but nothing ever read
        // them — a switch that silently does nothing is worse than no switch.
        $prefs = $request->user()->notification_preferences ?? [];

        foreach (User::EMAIL_PREFERENCE_KEYS as $key) {
            $prefs[$key] = $request->boolean($key);
        }

        $request->user()->update(['notification_preferences' => $prefs]);

        return back()->with('success', __('account.email_prefs_updated'));
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated()['password']),
            'password_set_at' => now(),
        ]);

        // Changing a password is usually a reaction to it having leaked, so
        // anything still signed in elsewhere on the old one goes with it. The
        // device doing the change stays put.
        app(SessionSecurityService::class)->signOutOtherDevices($request, $user);

        return back()->with('success', __('account.password_changed'));
    }

    public function favorites(Request $request): Response
    {
        $user = $request->user();
        $servers = $user->favouriteServers()->with(['game', 'latestSnapshot'])->get()->map(fn (Server $s) => [
            'id' => $s->id,
            'name' => $s->name ?? $s->latestSnapshot?->name ?? $s->address,
            'game' => $s->game?->name,
            'game_slug' => $s->game?->slug,
            'game_icon' => $s->game?->cover_url,
            'address' => $s->address,
            'map' => $s->latestSnapshot?->map,
            'map_image' => $s->game ? Game::mapImageUrl($s->game->slug, $s->latestSnapshot?->map) : null,
            'players' => $s->latestSnapshot?->players_online,
            'max_players' => $s->latestSnapshot?->players_max,
            'online' => $s->is_online,
            // The favourites list previously had no way back to the server page
            // — only a connect link — so the name was a dead end.
            'show_url' => $s->game
                ? route('servers.show', [$s->game->slug, $s->ip, $s->port])
                : null,
            'connect_url' => $s->game
                ? route('servers.connect', [$s->game->slug, $s->ip, $s->port])
                : null,
        ]);

        return Inertia::render('Account/Favorites', [
            'servers' => $servers,
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'unreadMessages' => $user->unreadMessagesCount(),
        ]);
    }

    public function toggleFavorite(Request $request, Server $server): JsonResponse
    {
        $user = $request->user();

        if ($user->favouriteServers()->where('server_id', $server->id)->exists()) {
            $user->favouriteServers()->detach($server->id);
            $favorited = false;
        } else {
            $user->favouriteServers()->attach($server->id);
            $favorited = true;
        }

        return response()->json(['favorited' => $favorited]);
    }

    public function deleteAccount(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'username_confirm' => ['required', 'string'],
            // An account created through a provider has a random password its
            // owner never saw, so demanding it here made deletion impossible
            // for them. The username confirmation carries the intent instead.
            'password' => [$user->hasUsablePassword() ? 'required' : 'nullable', 'string'],
        ]);

        if ($request->input('username_confirm') !== $user->username) {
            throw ValidationException::withMessages(['username_confirm' => __('account.delete_username_mismatch')]);
        }

        if ($user->hasUsablePassword() && ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => __('account.wrong_current_password')]);
        }

        // Anonymize — do not hard-delete so related data keeps referential integrity
        $user->update([
            'name' => 'Deleted User',
            'username' => 'deleted_'.$user->id,
            'display_name' => null,
            'email' => 'deleted_'.$user->id.'@deleted.invalid',
            'password' => Hash::make(Str::random(64)),
            'password_set_at' => null,
            'avatar' => null,
            'banner' => null,
            'bio' => null,
            'location' => null,
            'website' => null,
            'notification_preferences' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => Str::random(60),
            'banned_at' => now(),
        ]);

        // The row survives for referential integrity, so the cascades on these
        // tables never fire — they have to be cleared by hand. Each holds data
        // that identifies the person: provider ids, IP addresses, devices.
        $user->connectedAccounts()->delete();
        $user->loginHistories()->delete();

        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        app(AvatarService::class)->delete($user);
        app(BannerService::class)->delete($user);

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', __('account.account_deleted'));
    }

    public function exportData(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Same reasoning as deletion: a provider-only account has no password
        // to confirm with, and the right to a copy of your data cannot hinge
        // on a credential that was never issued to you.
        if ($user->hasUsablePassword()) {
            $request->validate(['password' => ['required', 'string']]);

            if (! Hash::check($request->input('password'), $user->password)) {
                throw ValidationException::withMessages(['password' => __('account.wrong_current_password')]);
            }
        } else {
            $request->validate(['username_confirm' => ['required', 'string']]);

            if ($request->input('username_confirm') !== $user->username) {
                throw ValidationException::withMessages(['username_confirm' => __('account.delete_username_mismatch')]);
            }
        }

        // Generated on the queue — the user gets a notification with a
        // download link when the file is ready.
        GenerateDataExportJob::dispatch($user);

        return back()->with('success', __('account.export_queued'));
    }

    public function downloadExport(Request $request, string $filename): StreamedResponse
    {
        // Only the owner may download their export file.
        abort_unless(
            preg_match('/^user_'.$request->user()->id.'_\d{8}_\d{6}\.json$/', $filename) === 1,
            403,
        );
        abort_unless(Storage::disk('local')->exists('exports/'.$filename), 404);

        return Storage::disk('local')->download('exports/'.$filename);
    }

    public function activityLog(Request $request): Response
    {
        $user = $request->user();
        $history = $user->loginHistories()->latest()->paginate(20);

        return Inertia::render('Account/ActivityLog', [
            'history' => $history->through(fn ($h) => [
                'id' => $h->id,
                'ip' => $h->ip_address,
                'user_agent' => $h->user_agent,
                'country' => $h->country,
                'city' => $h->city,
                'at' => $h->created_at->diffForHumans(),
                'at_full' => $h->created_at->format('d M Y, H:i'),
            ]),
        ]);
    }
}
