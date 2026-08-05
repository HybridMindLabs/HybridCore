# Demo

A reference HybridCore extension exercising every registry in the Extension
SDK, with a short, working example of each. Copy this extension's structure
as the starting point for a new one.

## Install

1. **Admin → Extensions → Sync from disk**
2. Enable **Demo**
3. Its migrations (if any) run automatically on enable.

## What's demonstrated where

All registrations live in `src/DemoServiceProvider.php`; this table points
at what backs each one.

| Registry | File(s) |
|---|---|
| `permissions()` | `DemoServiceProvider.php` |
| `abilities()` | `DemoServiceProvider.php`, `routes/api.php`, `src/Http/Controllers/Api/DemoController.php` |
| `navigation()` | `DemoServiceProvider.php`, `resources/js/pages/Admin/Index.vue` |
| `widgets()` | `DemoServiceProvider.php` |
| `settings()` | `routes/admin.php`, `src/Http/Controllers/Admin/DemoController.php::settings/updateSettings`, `resources/js/pages/Admin/Settings.vue` |
| `slots()` | `resources/js/components/HybridcoreDemoWidget.vue` |
| `publicNavigation()` / `userMenu()` / `footerLinks()` | `DemoServiceProvider.php` |
| `accountTabs()` | `routes/web.php`, `src/Http/Controllers/Web/DemoController.php::account`, `resources/js/pages/Account/Index.vue` |
| `profileTabs()` | `resources/js/components/HybridcoreDemoProfilePanel.vue` |
| `search()` | `DemoServiceProvider.php` |
| `quickActions()` | `DemoServiceProvider.php` |
| `notificationTypes()` | `src/Notifications/DemoTestNotification.php`, `src/Http/Controllers/Admin/DemoController.php::notify` (triggered by the "Send yourself a test notification" button) |
| `activityFeed()` | `DemoServiceProvider.php` |
| `onboardingSteps()` | `resources/js/components/HybridcoreDemoOnboarding.vue`, gated by the `show_onboarding_step` setting |
| `scheduledReports()` | `DemoServiceProvider.php` |
| `hooks()` | `DemoServiceProvider.php` (listens on `Hooks::USER_LOGIN`) |
| `filters()` | `DemoServiceProvider.php` (adds to `Filters::INERTIA_SHARED`) |
| `bridgeEvents()` | `DemoServiceProvider.php` (listens on `player.kill`) |

Web page: `routes/web.php` → `resources/js/pages/Web/Index.vue`.
Translations: `resources/lang/{en,bg}/messages.php`.

## Learn more

See [BUILDING_EXTENSIONS.md](../../BUILDING_EXTENSIONS.md) for the full
Extension SDK reference — manifest fields, lifecycle, hooks, filters and slots.
