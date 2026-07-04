<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Mail\AccountBannedMail;
use App\Models\ActivityLog;
use App\Models\ContentReport;
use App\Models\NewsComment;
use App\Models\Role;
use App\Models\ServerReview;
use App\Models\User;
use App\Models\UserAdminNote;
use App\Services\ActivityLogService;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::with('roles')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'is_admin' => $user->is_admin,
                'banned' => $user->isBanned(),
                'verified' => ! is_null($user->email_verified_at),
                'role' => $user->primaryRole()?->only('name', 'slug', 'color', 'icon'),
                'roles_count' => $user->roles->count(),
                'created_at' => $user->created_at?->toDateString(),
                'last_seen_at' => $user->last_seen_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'stats' => [
                'total' => User::count(),
                'active' => User::whereNull('banned_at')->count(),
                'banned' => User::whereNotNull('banned_at')->count(),
                'admins' => User::where('is_admin', true)->count(),
                'unverified' => User::whereNull('email_verified_at')->count(),
            ],
        ]);
    }

    /** 360° detail view — everything an admin needs about one user in one place. */
    public function show(User $user): Response
    {
        $user->load('roles', 'connectedAccounts', 'achievements');

        $commentIds = NewsComment::where('user_id', $user->id)->pluck('id');
        $reviewIds = ServerReview::where('user_id', $user->id)->pluck('id');

        $reportsAgainst = ContentReport::where(function ($q) use ($commentIds, $reviewIds) {
            $q->where(fn ($sub) => $sub->where('reportable_type', NewsComment::class)->whereIn('reportable_id', $commentIds))
                ->orWhere(fn ($sub) => $sub->where('reportable_type', ServerReview::class)->whereIn('reportable_id', $reviewIds));
        });

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'banner' => $user->banner,
                'bio' => $user->bio,
                'location' => $user->location,
                'is_admin' => $user->is_admin,
                'banned' => $user->isBanned(),
                'verified' => ! is_null($user->email_verified_at),
                'online' => $user->isOnline(),
                'role' => $user->primaryRole()?->only('name', 'slug', 'color', 'icon'),
                'roles' => $user->roles->map(fn (Role $r) => $r->only('name', 'slug', 'color', 'icon')),
                'created_at' => $user->created_at?->toDayDateTimeString(),
                'last_seen_at' => $user->last_seen_at?->diffForHumans(),
            ],
            'stats' => [
                'comments' => $commentIds->count(),
                'reviews' => $reviewIds->count(),
                'followers' => $user->followers()->count(),
                'following' => $user->following()->count(),
                'achievements' => $user->achievements->count(),
                'favourites' => $user->favouriteServers()->count(),
                'reports_against' => $reportsAgainst->count(),
                'reports_filed' => ContentReport::where('reporter_id', $user->id)->count(),
            ],
            'connectedAccounts' => $user->connectedAccounts->map(fn ($a) => [
                'provider' => $a->provider,
                'created_at' => $a->created_at?->toDateString(),
            ]),
            'achievements' => $user->achievements->sortByDesc('created_at')->values()->map(fn ($a) => [
                'slug' => $a->slug,
                'earned_at' => $a->created_at?->toDateString(),
            ]),
            'recentComments' => NewsComment::with('article:id,title,slug')
                ->where('user_id', $user->id)->latest()->limit(5)->get()
                ->map(fn (NewsComment $c) => [
                    'id' => $c->id,
                    'body' => \Str::limit($c->body, 120),
                    'article_title' => $c->article?->title,
                    'article_slug' => $c->article?->slug,
                    'created_at' => $c->created_at?->diffForHumans(),
                ]),
            'recentReviews' => ServerReview::with('server:id,name')
                ->where('user_id', $user->id)->latest()->limit(5)->get()
                ->map(fn (ServerReview $r) => [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'body' => \Str::limit((string) $r->body, 120),
                    'server_name' => $r->server?->name,
                    'created_at' => $r->created_at?->diffForHumans(),
                ]),
            'recentActivity' => ActivityLog::where('causer_type', User::class)
                ->where('causer_id', $user->id)
                ->latest()->limit(10)->get()
                ->map(fn (ActivityLog $log) => [
                    'event' => $log->event,
                    'description' => $log->description,
                    'created_at' => $log->created_at?->diffForHumans(),
                ]),
            'loginHistory' => $user->loginHistories()->latest()->limit(10)->get()
                ->map(fn ($login) => [
                    'ip' => $login->ip_address,
                    'country' => $login->country,
                    'city' => $login->city,
                    'user_agent' => \Str::limit((string) $login->user_agent, 80),
                    'created_at' => $login->created_at?->toDayDateTimeString(),
                ]),
            'notes' => UserAdminNote::with('author:id,name')
                ->where('user_id', $user->id)->latest()->get()
                ->map(fn (UserAdminNote $note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author' => $note->author?->name ?? 'Deleted admin',
                    'created_at' => $note->created_at?->toDayDateTimeString(),
                ]),
        ]);
    }

    public function storeNote(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        UserAdminNote::create([
            'user_id' => $user->id,
            'author_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $this->activity->log('user.note_added', "Added admin note on {$user->email}", $user);

        return back()->with('success', 'Note added.');
    }

    public function destroyNote(User $user, UserAdminNote $note): RedirectResponse
    {
        abort_unless($note->user_id === $user->id, 404);

        $note->delete();

        $this->activity->log('user.note_deleted', "Deleted admin note on {$user->email}", $user);

        return back()->with('success', 'Note deleted.');
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::orderBy('sort')->get(['id', 'name', 'slug', 'color', 'icon']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $base = preg_replace('/[^a-z0-9_-]/i', '', strtolower(explode(' ', $data['name'])[0])) ?: 'user';
        $username = $base;
        $i = 2;
        while (User::where('username', $username)->exists()) {
            $username = $base.'_'.$i++;
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => $username,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => (bool) ($data['is_admin'] ?? false),
        ]);

        $this->syncRoles($user, $data['roles'] ?? [], $data['primary_role'] ?? null);

        $this->activity->log('user.created', "Created user {$user->email}", $user);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} created.");
    }

    public function edit(User $user): Response
    {
        $user->load('roles');

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'is_admin' => $user->is_admin,
                'banned' => $user->isBanned(),
                'verified' => ! is_null($user->email_verified_at),
                'roles' => $user->roles->pluck('slug'),
                'primary_role' => $user->primaryRole()?->slug,
                'created_at' => $user->created_at?->toDateString(),
                'last_seen_at' => $user->last_seen_at?->diffForHumans(),
            ],
            'roles' => Role::orderBy('sort')->get(['id', 'name', 'slug', 'color', 'icon']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $wasBanned = $user->isBanned();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_admin = (bool) ($data['is_admin'] ?? false);
        $user->banned_at = ($data['banned'] ?? false) ? ($user->banned_at ?? now()) : null;

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if (! $wasBanned && $user->isBanned()) {
            $this->notifyBanned($user, $data['ban_reason'] ?? null);
        }

        if (array_key_exists('roles', $data)) {
            $this->syncRoles($user, $data['roles'] ?? [], $data['primary_role'] ?? null);
        }

        $this->activity->log('user.updated', "Updated user {$user->email}", $user);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} updated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $email = $user->email;
        $user->delete();

        $this->activity->log('user.deleted', "Deleted user {$email}");

        return redirect()->route('admin.users.index')
            ->with('success', "User {$email} deleted.");
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:ban,unban,verify,delete'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $currentUserId = $request->user()->id;
        $users = User::whereIn('id', $data['user_ids'])
            ->where('id', '!=', $currentUserId)
            ->get();

        foreach ($users as $user) {
            $wasBanned = $user->isBanned();

            match ($data['action']) {
                'ban' => $user->forceFill(['banned_at' => $user->banned_at ?? now()])->save(),
                'unban' => $user->forceFill(['banned_at' => null])->save(),
                'verify' => $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save(),
                'delete' => $user->delete(),
            };

            if ($data['action'] === 'ban' && ! $wasBanned) {
                $this->notifyBanned($user);
            }
        }

        $this->activity->log('user.bulk_action', "Bulk {$data['action']} on ".count($users).' users');

        return back()->with('success', ucfirst($data['action']).' applied to '.count($users).' users.');
    }

    private function notifyBanned(User $user, ?string $reason = null): void
    {
        try {
            Mail::to($user->email)->queue(
                $reason ? new AccountBannedMail($user, $reason) : new AccountBannedMail($user)
            );
        } catch (\Exception) {
        }

        app(HookRegistry::class)->fire(Hooks::USER_BANNED, $user, $reason);
    }

    public function export(Request $request): HttpResponse
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::with('roles')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $rows = $users->map(fn (User $u) => implode(',', [
            $u->id,
            '"'.str_replace('"', '""', $u->name).'"',
            '"'.str_replace('"', '""', $u->email).'"',
            '"'.str_replace('"', '""', (string) $u->username).'"',
            '"'.str_replace('"', '""', $u->roles->pluck('name')->implode(', ')).'"',
            $u->created_at?->toDateTimeString() ?? '',
            $u->email_verified_at ? 'yes' : 'no',
            $u->banned_at ? 'yes' : 'no',
        ]));

        $header = 'id,name,email,username,roles,registered_at,verified,banned';
        $csv = $header."\n".$rows->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * Attach the given role slugs to the user, marking exactly one as primary
     * (the requested one if it's in the set, otherwise the first).
     *
     * @param  array<int, string>  $roleSlugs
     */
    private function syncRoles(User $user, array $roleSlugs, ?string $primarySlug): void
    {
        $roles = Role::whereIn('slug', $roleSlugs)->get();

        if ($roles->isEmpty()) {
            $user->roles()->sync([]);

            return;
        }

        $primary = $primarySlug ? $roles->firstWhere('slug', $primarySlug) : null;
        $primary ??= $roles->first();

        $pivotData = $roles->mapWithKeys(fn (Role $role) => [
            $role->id => ['is_primary' => $role->id === $primary->id],
        ])->all();

        $user->roles()->sync($pivotData);
    }
}
