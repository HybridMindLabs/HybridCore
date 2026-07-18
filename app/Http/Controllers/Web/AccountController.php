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
use App\Services\Localization\LocaleService;
use App\Services\Media\AvatarService;
use App\Services\Media\BannerService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
        $sessionCtrl = new SessionController;

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
            'sessions' => $sessionCtrl->index($request),
            'connectedAccounts' => $user->connectedAccounts->map(fn ($acc) => [
                'provider' => $acc->provider,
                'username' => $acc->provider_username,
                'avatar_url' => $acc->avatar_url,
                'connected_at' => $acc->created_at->toFormattedDateString(),
            ]),
            'oauthProviders' => $this->oauth->compose(),
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
            'notification_preferences' => ['nullable', 'array'],
            'notification_preferences.*' => ['string', 'in:dm_email,mention_email,system_email'],
        ]);

        if (! empty($data['locale']) && ! app(LocaleService::class)->isSupported($data['locale'])) {
            return back()->withErrors(['locale' => __('messages.language_unavailable')]);
        }

        $existing = $request->user()->notification_preferences ?? [];
        $channelKeys = ['dm_email', 'mention_email', 'system_email'];
        $merged = array_merge(
            array_diff_key($existing, array_flip($channelKeys)),
            array_intersect($data['notification_preferences'] ?? [], $channelKeys),
        );

        $request->user()->update([
            'locale' => $data['locale'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'notification_preferences' => $merged,
        ]);

        if (! empty($data['locale'])) {
            $request->session()->put('locale', $data['locale']);
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
        $request->user()->update(['password' => Hash::make($request->validated()['password'])]);

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
            'password' => ['required', 'string'],
        ]);

        if ($request->input('username_confirm') !== $user->username) {
            throw ValidationException::withMessages(['username_confirm' => __('account.delete_username_mismatch')]);
        }

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => __('account.wrong_current_password')]);
        }

        // Anonymize — do not hard-delete so related data keeps referential integrity
        $user->update([
            'name' => 'Deleted User',
            'username' => 'deleted_'.$user->id,
            'display_name' => null,
            'email' => 'deleted_'.$user->id.'@deleted.invalid',
            'password' => Hash::make(\Str::random(64)),
            'avatar' => null,
            'banner' => null,
            'bio' => null,
            'location' => null,
            'website' => null,
            'banned_at' => now(),
        ]);

        app(AvatarService::class)->delete($user);
        app(BannerService::class)->delete($user);

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', __('account.account_deleted'));
    }

    public function exportData(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('account.wrong_current_password')]);
        }

        // Generated on the queue — the user gets a notification with a
        // download link when the file is ready.
        GenerateDataExportJob::dispatch($request->user());

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
