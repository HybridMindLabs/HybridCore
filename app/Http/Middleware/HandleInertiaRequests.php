<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\UpdateController;
use App\Models\ContactMessage;
use App\Models\Extension;
use App\Models\Game;
use App\Models\LegalPage;
use App\Models\Menu;
use App\Models\Server;
use App\Services\Auth\OAuthProviderRegistry;
use App\Services\Extensions\Registries\AccountTabRegistry;
use App\Services\Extensions\Registries\FilterRegistry;
use App\Services\Extensions\Registries\FooterLinkRegistry;
use App\Services\Extensions\Registries\NavigationRegistry;
use App\Services\Extensions\Registries\NotificationTypeRegistry;
use App\Services\Extensions\Registries\PublicNavigationRegistry;
use App\Services\Extensions\Registries\QuickActionRegistry;
use App\Services\Extensions\Registries\SlotRegistry;
use App\Services\Extensions\Registries\UserMenuRegistry;
use App\Services\Localization\LocaleService;
use App\Services\SettingsService;
use App\Services\Themes\ThemeResolver;
use App\Support\Filters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly ThemeResolver $themeResolver,
        private readonly NavigationRegistry $navigation,
        private readonly PublicNavigationRegistry $publicNavigation,
        private readonly AccountTabRegistry $accountTabs,
        private readonly UserMenuRegistry $userMenu,
        private readonly FooterLinkRegistry $footerLinks,
        private readonly QuickActionRegistry $quickActions,
        private readonly NotificationTypeRegistry $notificationTypes,
        private readonly SlotRegistry $slots,
        private readonly LocaleService $locales,
        private readonly OAuthProviderRegistry $oauth,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    private function resolveThemeProps(): array
    {
        $theme = $this->themeResolver->resolve();

        return [
            'slug' => $theme?->slug ?? 'Default',
            'settings' => $theme?->metadata['settings'] ?? [],
        ];
    }

    private function resolveAuthShell(): array
    {
        // Only rendered on auth pages, but shared closures run on every
        // request — cache so the random-server pick isn't queried each time.
        return Cache::remember('inertia.auth_shell', 60, function () {
            $games = Game::orderBy('name')->limit(6)->get(['name', 'slug']);

            $servers = Server::with(['latestSnapshot', 'game'])
                ->whereHas('latestSnapshot', fn ($q) => $q->where('is_online', true))
                ->inRandomOrder()
                ->limit(3)
                ->get();

            return [
                'games' => $games->map(fn (Game $g) => [
                    'name' => $g->name,
                    'slug' => $g->slug,
                ])->values(),
                'servers' => $servers->map(fn (Server $s) => [
                    'name' => $s->latestSnapshot?->name ?? $s->name,
                    'map' => $s->latestSnapshot?->map ?? '—',
                    'players' => ($s->latestSnapshot?->players_online ?? 0).' / '.($s->latestSnapshot?->players_max ?? 0),
                    'ping' => $s->latestSnapshot?->ping ? $s->latestSnapshot->ping.'ms' : '—',
                    'slug' => $s->game?->slug ?? '',
                ])->values(),
            ];
        });
    }

    public const MENUS_CACHE_KEY = 'inertia.menus';

    public const LEGAL_PAGES_CACHE_KEY = 'inertia.legal_pages';

    public const FOOTER_CACHE_KEY = 'inertia.site_footer';

    /**
     * messages.php of every enabled extension, nested as ext.{namespace} so the
     * frontend resolves t('ext.{namespace}.{key}'). Failures are silently
     * skipped — a broken lang file must not take down every page.
     *
     * @return array{ext?: array<string, array<string, mixed>>}
     */
    private function extensionTranslations(): array
    {
        try {
            if (! Schema::hasTable('extensions')) {
                return [];
            }

            $ext = [];

            foreach (Extension::where('enabled', true)->get() as $extension) {
                $manifest = $extension->metadata ?? [];
                $namespace = is_string($manifest['lang_namespace'] ?? null) && $manifest['lang_namespace'] !== ''
                    ? $manifest['lang_namespace']
                    : (string) str(($manifest['slug'] ?? 'extension'))->afterLast('-')->afterLast('/');

                $messages = trans($namespace.'::messages');

                if (is_array($messages)) {
                    $ext[$namespace] = $messages;
                }
            }

            // Nested under "ext" so the frontend's dotted resolver reaches
            // t('ext.{namespace}.{key}') as translations.ext.{namespace}.{key}.
            return $ext === [] ? [] : ['ext' => $ext];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Games hosted here plus a live snapshot, for the public footer.
     *
     * @return array{games: array<int, array{slug: string, name: string, servers: int, players: int}>, servers_online: int, servers_total: int, players_online: int}
     */
    private function buildFooter(): array
    {
        $servers = Server::query()
            ->where('is_active', true)
            ->with(['latestSnapshot', 'game:id,slug,name'])
            ->get();

        $online = $servers->filter(fn (Server $s) => (bool) ($s->latestSnapshot?->is_online ?? false));

        $games = $servers
            ->filter(fn (Server $s) => $s->game !== null)
            ->groupBy(fn (Server $s) => $s->game->slug)
            ->map(fn ($group) => [
                'slug' => $group->first()->game->slug,
                'name' => $group->first()->game->name,
                'servers' => $group->count(),
                'players' => $group->sum(
                    fn (Server $s) => ($s->latestSnapshot?->is_online ?? false)
                        ? ($s->latestSnapshot->players_online ?? 0)
                        : 0
                ),
            ])
            ->sortByDesc('players')
            ->values()
            ->take(8)
            ->all();

        return [
            'games' => $games,
            'servers_online' => $online->count(),
            'servers_total' => $servers->count(),
            'players_online' => $online->sum(fn (Server $s) => $s->latestSnapshot->players_online ?? 0),
        ];
    }

    private function resolveMenus(): array
    {
        try {
            return Cache::remember(self::MENUS_CACHE_KEY, 3600, fn () => $this->buildMenus());
        } catch (\Throwable) {
            // Database unreachable (installer / outage) — render without menus.
            return [];
        }
    }

    /** @return array<string, array<int, array<string, string>>> */
    private function buildMenus(): array
    {
        $menus = Menu::with(['items' => fn ($q) => $q->whereNull('parent_id')->orderBy('sort'), 'items.children' => fn ($q) => $q->orderBy('sort')])
            ->whereNotNull('location')
            ->get();

        $result = [];
        foreach ($menus as $menu) {
            $result[$menu->location] = $menu->items->map(fn ($item) => [
                'label' => $item->label,
                'url' => $item->url,
                'target' => $item->target,
                'children' => $item->children->map(fn ($c) => [
                    'label' => $c->label,
                    'url' => $c->url,
                    'target' => $c->target,
                ])->toArray(),
            ])->toArray();
        }

        return $result;
    }

    public function share(Request $request): array
    {
        $shared = [
            ...parent::share($request),
            'app' => [
                'name' => $this->settings->appName(),
                'version' => UpdateController::VERSION,
                'theme' => fn () => $this->resolveThemeProps(),
            ],
            'socialLinks' => fn () => array_filter([
                'discord' => $this->settings->get('social_discord', ''),
                'steam' => $this->settings->get('social_steam', ''),
                'twitter' => $this->settings->get('social_twitter', ''),
                'youtube' => $this->settings->get('social_youtube', ''),
            ]),
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->only('id', 'name', 'email', 'is_admin'),
                    'username' => $request->user()->username,
                    'avatar' => $request->user()->avatar,
                    'verified' => $request->user()->hasVerifiedEmail(),
                    'two_factor_enabled' => (bool) $request->user()->two_factor_secret,
                ] : null,
            ],
            'impersonating' => $request->session()->has('impersonator_id') && $request->user()
                ? ['name' => $request->user()->name]
                : null,
            'adminNav' => fn () => $request->user()?->is_admin
                ? $this->navigation->compose()
                : [],
            // Public (site header) links registered by enabled extensions.
            // Items with a permission are only shown to users who hold it.
            'publicNav' => fn () => array_values(array_filter(
                $this->publicNavigation->compose(),
                fn (array $item) => $item['permission'] === null
                    || (bool) $request->user()?->can($item['permission']),
            )),
            // Extension-registered account-panel tabs (auth users only).
            'accountTabs' => fn () => $request->user() ? array_values(array_filter(
                $this->accountTabs->compose(),
                fn (array $item) => $item['permission'] === null
                    || (bool) $request->user()->can($item['permission']),
            )) : [],
            // Extension-registered user-dropdown links (auth users only).
            'userMenu' => fn () => $request->user() ? array_values(array_filter(
                $this->userMenu->compose(),
                fn (array $item) => $item['permission'] === null
                    || (bool) $request->user()->can($item['permission']),
            )) : [],
            // Extension-registered public footer links.
            'footerNav' => fn () => array_values(array_filter(
                $this->footerLinks->compose(),
                fn (array $item) => $item['permission'] === null
                    || (bool) $request->user()?->can($item['permission']),
            )),
            // Extension-registered admin command-palette actions (admins only).
            'quickActions' => fn () => $request->user()?->is_admin ? array_values(array_filter(
                $this->quickActions->compose(),
                fn (array $item) => $item['permission'] === null
                    || (bool) $request->user()->can($item['permission']),
            )) : [],
            // Extension notification-type styling (type => {icon, accent}).
            'notificationTypes' => fn () => $request->user() ? $this->notificationTypes->compose() : [],
            'adminBadges' => fn () => $request->user()?->is_admin ? [
                'unread_contact' => ContactMessage::whereNull('read_at')->count(),
            ] : [],
            'localization' => fn () => [
                'currentLocale' => app()->getLocale(),
                'fallbackLocale' => $this->locales->fallbackLocale(),
                'supportedLocales' => $this->locales->supportedLocales(),
                'localeDirection' => $this->locales->direction(app()->getLocale()),
                'languageSwitcherEnabled' => $request->is('admin') || $request->is('admin/*')
                    ? $this->locales->adminSwitcherEnabled()
                    : $this->locales->publicSwitcherEnabled(),
            ],
            'translations' => fn () => [
                'auth' => trans('auth'),
                'account' => trans('account'),
                'messages' => trans('messages'),
                'navigation' => trans('navigation'),
                'home' => trans('home'),
                'roles' => trans('roles'),
                'members' => trans('members'),
                'profile' => trans('profile'),
                'servers' => trans('servers'),
                'news' => trans('news'),
                'rules' => trans('rules'),
                'contact' => trans('contact'),
                'achievements' => trans('achievements'),
                'onboarding' => trans('onboarding'),
                'report' => trans('report'),
                // Enabled extensions' messages.php, keyed "ext.{namespace}" —
                // reachable in Vue via t('ext.store.some_key').
                ...$this->extensionTranslations(),
            ],
            // Slot data is shared lazily — evaluated only when the page renders.
            // Frontend uses <ExtensionSlot name="home.right.bottom" /> to render.
            'extensionSlots' => fn () => $this->slots->compose(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // One-time reveal of a freshly generated bridge token.
                'bridge_token' => fn () => $request->session()->get('bridge_token'),
            ],
            'oauthProviders' => fn () => $this->oauth->compose(),
            'legalPages' => fn () => rescue(fn () => Cache::remember(
                self::LEGAL_PAGES_CACHE_KEY,
                3600,
                fn () => LegalPage::orderBy('sort_order')->orderBy('id')
                    ->limit(5)
                    ->get(['slug', 'title'])
                    ->toArray(),
            ), [], report: false),
            // Footer content for the public layout: the games actually hosted
            // here plus a live line. The footer renders on every public page,
            // most of which have no other status indicator.
            'siteFooter' => fn () => rescue(fn () => Cache::remember(
                self::FOOTER_CACHE_KEY,
                120,
                fn () => $this->buildFooter(),
            ), null, report: false),
            'menus' => fn () => $this->resolveMenus(),
            'authShell' => fn () => rescue(fn () => $this->resolveAuthShell(), null, report: false),
        ];

        // Extensions may add or reshape shared props via the filter chain.
        return app(FilterRegistry::class)
            ->apply(Filters::INERTIA_SHARED, $shared, $request);
    }
}
