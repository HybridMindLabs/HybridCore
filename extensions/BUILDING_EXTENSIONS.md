# Building HybridCore Extensions

> A complete guide to creating production-ready extensions.
> Reference implementation: `extensions/hybridcore/announcements/`

---

## Table of Contents

1. [Directory Structure](#directory-structure)
2. [extension.json Manifest](#extensionjson-manifest)
3. [ServiceProvider — the entry point](#serviceprovider--the-entry-point)
4. [Permissions](#permissions)
5. [Admin Navigation](#admin-navigation)
6. [Admin Dashboard Widgets](#admin-dashboard-widgets)
7. [Extension Settings Page](#extension-settings-page)
8. [Page Slots — injecting into core pages](#page-slots--injecting-into-core-pages)
9. [Routes](#routes)
10. [Database Migrations](#database-migrations)
11. [Translations](#translations)
12. [Vue Pages (Inertia)](#vue-pages-inertia)
13. [Slot Vue Components](#slot-vue-components)
14. [Available Slot Names](#available-slot-names)
15. [Naming Conventions](#naming-conventions)
16. [Installation Checklist](#installation-checklist)

---

## Directory Structure

```
extensions/
  {vendor}/
    {name}/
      extension.json                   ← manifest (required)
      database/
        migrations/                    ← standard Laravel migrations
      resources/
        js/
          components/                  ← slot Vue components (auto-registered globally)
          pages/
            Admin/                     ← admin pages rendered with Inertia
            Web/                       ← public pages (optional)
        lang/
          en/messages.php
          bg/messages.php
      routes/
        admin.php                      ← auto-wrapped with web+auth+admin middleware + /admin prefix
        web.php                        ← auto-wrapped with web middleware
      src/
        {Name}ServiceProvider.php      ← main entry point (required)
        Http/
          Controllers/
            Admin/
            Web/
          Requests/
        Models/
        Services/
```

`{vendor}` and `{name}` are **lowercase** (e.g. `hybridcore/announcements`).
The `src/` namespace is PascalCase: `Hybridcore\Announcements\...`

---

## extension.json Manifest

```json
{
    "id":          "hybridcore/announcements",
    "name":        "Announcements",
    "version":     "1.0.0",
    "description": "Site-wide announcement banners.",
    "author":      "HybridCore",
    "type":        "official",
    "provider":    "Hybridcore\\Announcements\\AnnouncementsServiceProvider",
    "routes": {
        "web":   "routes/web.php",
        "admin": "routes/admin.php"
    },
    "migrations":     "database/migrations",
    "seeder":         "Hybridcore\\Announcements\\Database\\Seeders\\DefaultSeeder",
    "schedule":       "routes/schedule.php",
    "lang":           "resources/lang",
    "lang_namespace": "announcements",
    "requires": {
        "core": ">=0.1.0",
        "php":  ">=8.3"
    }
}
```

| Field             | Required | Notes |
|-------------------|----------|-------|
| `id`              | ✓        | Unique — `{vendor}/{name}` |
| `name`            | ✓        | Display name |
| `version`         | ✓        | SemVer |
| `provider`        | ✓        | Fully-qualified class name |
| `type`            |          | `official` \| `community` \| `custom` |
| `routes.web`      |          | Loaded with `web` middleware |
| `routes.admin`    |          | Loaded with `web + auth + admin` middleware, prefix `/admin` |
| `migrations`      |          | Directory path relative to extension root |
| `lang`            |          | Language files directory |
| `lang_namespace`  |          | Prefix used in `trans('slug::key')` |
| `seeder`          |          | Seeder FQCN, run on every enable — keep it idempotent |
| `schedule`        |          | PHP file with `Schedule::` calls, loaded in console only |
| `requires.core`   |          | Core version constraint (`>=0.1.0`, `^1.0`) — checked on import and enable |
| `requires.php`    |          | PHP version constraint |
| `requires.extensions` |      | Map of extension slug → version constraint; each must be installed **and enabled** |
| `routes.api`      |          | Stateless JSON routes, loaded with `api` middleware under `/api` — pick auth per route (`auth:sanctum`, custom token, or public) |
| `config`          |          | Config file merged as `config('ext.{namespace}.*')` |
| `views`           |          | Blade views dir (default `resources/views`) → `view('{namespace}::mail.receipt')` — mainly for mail templates |
| `commands`        |          | Array of Artisan command FQCNs, registered in console |
| `assets`          |          | Static files dir, published to `public/extensions/{vendor-name}/` on enable, removed on uninstall |

---

## Lifecycle

| Action        | What happens |
|---------------|--------------|
| **Import**    | ZIP is validated (manifest, zip-slip, `requires`) and extracted to `extensions/`. If the same extension is already installed, the import becomes an **update**: files are replaced, new migrations run (downgrades are rejected). |
| **Enable**    | `requires` re-checked → migrations run → `seeder` runs → assets rebuild → `Hooks::EXTENSION_ENABLED`. |
| **Disable**   | Routes/provider stop loading on next request. Data stays. `Hooks::EXTENSION_DISABLED`. |
| **Uninstall** | `Hooks::EXTENSION_UNINSTALLED` → migrations rolled back (tables dropped, unless "keep data" chosen) → settings + files deleted → record removed. |

## Seeders

Declare a seeder class in the manifest (`"seeder"`), place it in `src/Database/Seeders/`.
It runs on **every** enable, so guard inserts with `firstOrCreate()` / `updateOrCreate()`.

## Scheduled Tasks

Declare `"schedule": "routes/schedule.php"` and use the facade inside the file:

```php
<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('announcements:expire')->hourly()->name('announcements-expire');
```

The file is loaded only for enabled extensions, and only in console context.

## Filters — transforming core data

Hooks notify; **filters transform**. Core sends a value through a named filter
chain and uses whatever comes back:

```php
use App\Support\Filters;

$registry->filters()->add(Filters::SERVER_SHOW_PROPS, function (array $props, $server) {
    $props['vote_count'] = Votes::forServer($server->id);
    return $props;
});
```

Core filter points: `Filters::INERTIA_SHARED` (shared props on every page,
gets the Request), `Filters::SERVER_SHOW_PROPS` (public server page, gets the
Server), `Filters::PROFILE_SHOW_PROPS` (public profile, gets the User).
Extensions may define their own filter names (prefix with your slug) to open
integration points for other extensions.

## Core hooks

See `App\Support\Hooks` for the authoritative list. Currently fired:
`user.registered`, `user.login`, `user.banned`, `user.followed`,
`comment.created`, `review.created`, `article.published`, `message.sent`,
`server.queried`, `extension.enabled/disabled/updated/uninstalled`.

## Frontend translations

The `messages.php` group of every **enabled** extension is shared to the
frontend automatically under the `ext.{namespace}` key:

```ts
const { t } = useLocale();
t('ext.store.product_added');   // resources/lang/{locale}/messages.php → 'product_added'
```

## Game-Server Bridge

Extensions deliver in-game actions (vote rewards, store purchases, bans)
through the command bridge. Queue a console command for a server:

```php
use App\Services\Bridge\BridgeService;

app(BridgeService::class)->queue(
    $server,                       // App\Models\Server
    "hc_give_vip {$steamId} 30d",  // console command, max 500 chars, no newlines
    'hybridcore/store',            // your extension id (for auditing)
    ttlMinutes: 60,                // undelivered commands expire
);
```

Delivery contract (implemented by the in-game plugin):
- The admin generates a per-server bearer token (Admin → Servers → plug icon).
- The plugin polls `POST /api/bridge/poll` with `Authorization: Bearer hcb_…`
  and receives `{commands: [{id, command}]}` (max 25 per poll).
- After executing, it confirms with `POST /api/bridge/ack {ids: [...]}`.
- Unacked deliveries are retried up to 5 times; expired commands are never
  delivered. Tokens are stored hashed and can be rotated/revoked at any time.

## Testing

Put PHPUnit tests in `tests/` and run them with:

```bash
php artisan hybridcore:extensions:test vendor/name
```

---

## ServiceProvider — the entry point

The core loads your provider via the `provider` field in `extension.json`.
It receives `ExtensionRegistry $registry` in `boot()`.

```php
<?php

namespace Hybridcore\Announcements;

use App\Services\Extensions\Registries\ExtensionRegistry;
use App\Support\Slots;
use Hybridcore\Announcements\Services\AnnouncementService;
use Illuminate\Support\ServiceProvider;

class AnnouncementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register your own services/singletons here.
        $this->app->singleton(AnnouncementService::class);
    }

    public function boot(ExtensionRegistry $registry): void
    {
        // Register permissions, navigation, widgets, settings, slots here.
        // See sections below for each.
    }
}
```

> `register()` runs before the framework boots. `boot()` runs after.
> Only use `register()` for binding things into the container.

---

## Permissions

```php
$registry->permissions()->register(
    slug:        'announcements.view',     // unique slug
    label:       'View Announcements',     // shown in admin role editor
    group:       'announcements',          // groups related permissions together
);
```

Once registered, use permissions in:
- **Middleware**: `Route::middleware('perm:announcements.view')`
- **PHP gate**: `$user->can('announcements.view')`
- **Vue**: `page.props.auth.user.permissions` (array of granted slugs)

---

## Admin Navigation

```php
$registry->navigation()->register(
    label:      'Announcements',
    route:      'admin.announcements.index',  // must exist in routes/admin.php
    icon:       'Megaphone',                  // any Lucide icon name
    section:    'Content',                    // sidebar section header
    permission: 'announcements.view',         // null = visible to all admins
    sort:       50,                           // lower = higher in the list
);
```

**Section names** in the default admin sidebar: `Dashboard`, `Content`, `Servers`, `Users`, `Extensions`, `Settings`.
You can create a new section by using a new string — it will appear automatically.

---

## Admin Dashboard Widgets

```php
$registry->widgets()->register(
    id:         'announcements.active_count',
    title:      'Active Announcements',
    component:  'stat',              // 'stat' is the built-in card type
    data:       fn () => [
        'value'  => Announcement::visible()->count(),
        'icon'   => 'Megaphone',
        'label'  => 'active now',
        'accent' => 'blue',          // blue | green | amber | red | purple
    ],
    permission: 'announcements.view',
    sort:       10,
);
```

The `data` closure is **lazy** — it runs only when the admin dashboard is rendered.
Heavy queries here are fine; they do not affect other pages.

---

## Extension Settings Page

**Convention** — the settings URL is always:
```
/admin/settings/extensions/{slug}
```
and the named route is:
```
admin.settings.extensions.{slug}
```

**Step 1** — register in the ServiceProvider:
```php
$registry->settings()->register(
    slug:       'announcements',
    label:      'Announcements',       // shown in the settings sidebar
    permission: 'announcements.manage',
);
```

**Step 2** — define the route in `routes/admin.php` using the exact name:
```php
Route::get('/settings/extensions/announcements', [SettingsController::class, 'show'])
    ->name('admin.settings.extensions.announcements');

Route::patch('/settings/extensions/announcements', [SettingsController::class, 'update'])
    ->name('admin.settings.extensions.announcements.update');
```

**Step 3** — use `SettingsService` to persist key-value settings:
```php
// Read (second arg is the default):
$max = (int) $this->settings->get('announcements.max_shown', 3);

// Write:
$this->settings->set('announcements.max_shown', 5);
```

Use the extension slug as prefix (`announcements.*`) to avoid conflicts.

---

## Page Slots — injecting into core pages

Slots let you inject a Vue component into a pre-defined position in any core page
**without touching core code**.

```php
use App\Support\Slots;

$registry->slots()->register(
    slot:      Slots::HOME_RIGHT_TOP,          // where to inject
    component: 'HybridcoreAnnouncementsWidget', // Vue component name (global)
    data:      fn () => [                       // props passed to the component
        'announcements' => $this->getActive(),
    ],
    permission: null,                           // null = visible to everyone
    priority:   10,                             // lower renders first
);
```

**Returning `null` from `data` skips the slot** — useful for conditional display:
```php
data: fn () => $showWidget ? ['items' => $items] : null,
```

The component file must be in:
```
extensions/{vendor}/{name}/resources/js/components/{ComponentName}.vue
```

Vite discovers it automatically via `import.meta.glob` in `app.ts` and registers it globally
by filename. No manual import needed.

---

## Routes

### Admin routes (`routes/admin.php`)

Automatically loaded with:
- `web` + `auth` + `admin` middleware
- `/admin` URL prefix

```php
use Hybridcore\Announcements\Http\Controllers\Admin\AnnouncementController;
use Illuminate\Support\Facades\Route;

Route::middleware('perm:announcements.manage')->group(function (): void {
    Route::resource('announcements', AnnouncementController::class)
        ->except(['show'])
        ->names([
            'index'   => 'admin.announcements.index',
            'create'  => 'admin.announcements.create',
            'store'   => 'admin.announcements.store',
            'edit'    => 'admin.announcements.edit',
            'update'  => 'admin.announcements.update',
            'destroy' => 'admin.announcements.destroy',
        ]);
});
```

### Web routes (`routes/web.php`)

Automatically loaded with `web` middleware, no prefix.

```php
use Hybridcore\Announcements\Http\Controllers\Web\AnnouncementController;
use Illuminate\Support\Facades\Route;

Route::get('/announcements', [AnnouncementController::class, 'index'])
    ->name('announcements.index');
```

---

## Database Migrations

Place standard Laravel migration files in `database/migrations/`.
The core runs them automatically when the extension is enabled.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->enum('type', ['info', 'success', 'warning', 'danger'])->default('info');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
```

> **Tip** — prefix table names with your extension slug to avoid conflicts:
> `announcements_tags`, not `tags`.

---

## Translations

Files go in `resources/lang/{locale}/messages.php`.
The namespace is set by `lang_namespace` in `extension.json`.

```php
// resources/lang/en/messages.php
return [
    'created' => 'Announcement created.',
    'deleted' => 'Announcement deleted.',
];
```

Use translations in PHP:
```php
trans('announcements::messages.created')
// or
__('announcements::messages.created')
```

In Vue pages (translations are not automatically shared to the frontend).
Pass them as Inertia props from the controller:
```php
return Inertia::render('Extensions/hybridcore/announcements/Admin/Index', [
    'announcements' => ...,
    'labels' => [
        'created' => trans('announcements::messages.created'),
    ],
]);
```

---

## Vue Pages (Inertia)

Extension pages live in:
```
resources/js/pages/Admin/Index.vue
resources/js/pages/Admin/Form.vue
resources/js/pages/Web/PublicPage.vue
```

Rendered from a controller using the `Extensions/{vendor}/{name}/` prefix:
```php
return Inertia::render('Extensions/hybridcore/announcements/Admin/Index', [
    'announcements' => Announcement::all(),
]);
```

**Use core layouts and components** — they are globally available:
```vue
<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader  from '@/components/UI/PageHeader.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
</script>
```

**Dark mode** — always use the `useTheme` composable, not Tailwind's `dark:` class:
```ts
import { useTheme } from '@/composables/useTheme';
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
```

Then bind classes conditionally:
```vue
:class="dark ? 'bg-zinc-900 text-zinc-200' : 'bg-white text-zinc-800'"
```

---

## Slot Vue Components

A slot component is a plain `.vue` file placed in `resources/js/components/`.
It receives the `data` object from the ServiceProvider as props.

```vue
<!-- HybridcoreAnnouncementsWidget.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

interface Announcement { id: number; title: string; body: string; type: string }
defineProps<{ announcements: Announcement[] }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <div v-for="item in announcements" :key="item.id">
        {{ item.title }}
    </div>
</template>
```

**Naming** — prefix with `{PascalVendor}{PascalName}` to avoid collisions:
- `HybridcoreAnnouncementsWidget.vue` → registered as `HybridcoreAnnouncementsWidget`
- `HybridcoreStoreFeaturedProducts.vue` → `HybridcoreStoreFeaturedProducts`

The filename (without `.vue`) is used as the component name in `SlotRegistry::register()`.

---

## Available Slot Names

Use the `App\Support\Slots` constants in PHP:

| Constant                      | String value               | Location |
|-------------------------------|----------------------------|----------|
| `Slots::HOME_RIGHT_TOP`       | `home.right.top`           | Home page right column — top |
| `Slots::HOME_RIGHT_BOTTOM`    | `home.right.bottom`        | Home page right column — bottom |
| `Slots::HOME_MIDDLE_TOP`      | `home.middle.top`          | Home page middle column — top |
| `Slots::HOME_MIDDLE_BOTTOM`   | `home.middle.bottom`       | Home page middle column — bottom |
| `Slots::HOME_LEFT_BOTTOM`     | `home.left.bottom`         | Home page left sidebar — bottom |
| `Slots::SERVER_SHOW_SIDEBAR`  | `server.show.sidebar`      | Server detail page sidebar |
| `Slots::SERVER_SHOW_TABS`     | `server.show.tabs`         | Server detail extra content |
| `Slots::SERVER_LIST_SIDEBAR`  | `server.list.sidebar`      | Server list page sidebar |
| `Slots::PROFILE_SIDEBAR`      | `profile.sidebar`          | Profile page sidebar |
| `Slots::PROFILE_TABS`         | `profile.tabs`             | Profile extra tabs |
| `Slots::NEWS_SHOW_SIDEBAR`    | `news.show.sidebar`        | News article sidebar |
| `Slots::NEWS_SHOW_BOTTOM`     | `news.show.bottom`         | News article below content |
| `Slots::NEWS_LIST_SIDEBAR`    | `news.list.sidebar`        | News list page sidebar |
| `Slots::ADMIN_DASHBOARD_TOP`  | `admin.dashboard.top`      | Admin dashboard — top |
| `Slots::ADMIN_DASHBOARD_BOTTOM`| `admin.dashboard.bottom`  | Admin dashboard — bottom |

---

## Naming Conventions

| Thing | Convention | Example |
|-------|-----------|---------|
| Extension folder | lowercase | `hybridcore/announcements` |
| PHP namespace | PascalCase | `Hybridcore\Announcements` |
| ServiceProvider | `{Name}ServiceProvider` | `AnnouncementsServiceProvider` |
| Permission slugs | `{ext}.{action}` | `announcements.manage` |
| Route names (admin) | `admin.{ext}.*` | `admin.announcements.index` |
| Route names (web) | `{ext}.*` | `announcements.index` |
| Settings keys | `{ext}.*` | `announcements.max_shown` |
| Lang namespace | extension slug | `announcements::messages.created` |
| Slot components | `{PascalVendor}{PascalName}Widget` | `HybridcoreAnnouncementsWidget` |
| Admin nav section | Core sections or your own | `Content` / `My Store` |

---

## Installation Checklist

When installing a new extension:

1. Drop the folder into `extensions/{vendor}/{name}/`
2. In admin panel → Extensions → **Sync** (discovers `extension.json`)
3. Click **Enable** — core runs migrations, loads routes, and runs `npm run build`
4. Assign permissions to roles in admin → Roles
5. Done — the extension is live

To create from scratch:

```bash
# Copy the structure from the demo:
cp -r extensions/hybridcore/demo extensions/myfirm/myextension

# Edit extension.json — set id, name, provider
# Rename the namespace in src/
# Add your logic
# Sync + Enable in admin
```
