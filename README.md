# HybridCore

> A modular, self-hosted platform for gaming communities, server owners and multiplayer projects.

**Version 0.2.0** — feature-complete core with a full extension SDK. Production-ready for self-hosting.

---

## What is HybridCore?

HybridCore is a professional self-hosted platform for gaming communities —
think *WHMCS for game servers*. Server owners and network operators run their
entire online presence from one admin panel: servers, users, news, community,
real-time features and more, without touching source code.

Everything game-specific (store, voting, ban lists, forums…) is built as an
**extension** on top of a lean, stable core — so the platform grows without
bloating the foundation.

---

## Who is it for?

- Counter-Strike 1.6 / CS2 server owners
- Minecraft, Rust and FiveM network operators
- Any multiplayer community that wants one home for its players
- Developers building and selling gaming extensions
- Designers building and selling themes

---

## Features (v0.2.0)

**Identity & accounts**
- Registration, login, password reset, email verification, 2FA (TOTP)
- OAuth sign-in (Discord, Steam, Google) with encrypted credential storage
- Roles & permissions with a visual editor, multi-role assignment and wildcards
- Full account area: profile, avatar/banner, privacy, sessions, connected accounts, GDPR export & deletion
- Post-registration onboarding wizard

**Community**
- Public profiles, members directory, following & activity feeds
- Private messaging with typing indicators and real-time delivery
- Achievements/badges, blocking, online presence
- News CMS with categories, tags, comments, @mentions and scheduled publishing
- Content reports + a unified admin moderation center, with a restore-capable trash

**Servers**
- Live server browser with real query drivers (A2S for CS1.6/CS2/Rust, Minecraft SLP, FiveM)
- Player counts, maps, uptime history (7/30 days), reviews & ratings
- **Game-server bridge** — a secure, token-authenticated command queue so an
  in-game plugin can deliver rewards, purchases and bans

**Admin panel**
- Dashboard with growth stats, analytics, activity & audit logs
- Users (with a 360° detail view, admin notes and impersonation), IP bans, CSV export
- Settings, legal pages, menus, SEO + auto sitemap, email templates, maintenance mode
- Extensions & themes management, system health, backups, one-command updates

**Platform**
- **Extension SDK v2** — routes (web/admin/api), migrations, config, views, commands,
  assets, seeders, scheduled tasks, hooks, value filters, page slots, dependencies and versioning
- Real-time via Laravel Reverb (websockets), queues via Horizon (Redis)
- Global search (Laravel Scout), monitoring (Laravel Pulse), feature flags (Pennant)
- Live form validation (Precognition), full i18n (English + Bulgarian), PWA, security headers + CSP

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | Vue 3, Inertia.js, TypeScript |
| Styling | TailwindCSS 4 |
| Icons | `@lucide/vue` |
| Database | MySQL / MariaDB |
| Cache / Queue / Search | Redis + Horizon, Scout |
| Real-time | Laravel Reverb |
| Monitoring | Laravel Pulse |
| Build tool | Vite |

---

## Documentation

- **[DEPLOYMENT.md](DEPLOYMENT.md)** — full production self-hosting guide (requirements, nginx, Redis, Supervisor, installer, upgrades)
- **[extensions/BUILDING_EXTENSIONS.md](extensions/BUILDING_EXTENSIONS.md)** — the Extension SDK: manifest, lifecycle, hooks, filters, slots
- **[CHANGELOG.md](CHANGELOG.md)** — release history
- **[CONTRIBUTING.md](CONTRIBUTING.md)** — branch workflow, commit style, code standards
- **[SECURITY.md](SECURITY.md)** — vulnerability reporting

---

## Local Development

### Prerequisites

- PHP 8.3+ with the required extensions (see [DEPLOYMENT.md](DEPLOYMENT.md#1-server-requirements))
- Composer 2.x and Node.js 20+
- MySQL/MariaDB and Redis

Use whatever local environment you prefer (Laravel Herd/Valet, Sail, a plain
LEMP stack, etc.).

### First-time setup

```bash
git clone <repo-url> hybridcore
cd hybridcore

composer install
npm install

cp .env.example .env
php artisan key:generate

npm run build
```

Point your web server at the `public/` directory (or run `php artisan serve`),
then open the site — you'll be redirected to `/install` on first launch.

### Daily development

```bash
npm run dev            # Vite dev server (hot reload)
php artisan serve      # or your local web server
```

### Useful commands

```bash
php artisan test               # PHP feature + unit tests
./vendor/bin/pint              # PHP lint (Laravel Pint)
npm run type-check             # TypeScript type check
npm run build                  # Production asset build
php artisan optimize:clear     # Clear all caches
php artisan horizon            # Queue workers (Redis)
php artisan tinker             # REPL
```

---

## The Installer

On first launch HybridCore redirects all traffic to the wizard at `/install`:

1. **Welcome** — overview
2. **Requirements** — PHP version, extensions, file permissions
3. **Database** — connection details (tested before saving)
4. **Admin Account** — the first super-admin
5. **Site Settings** — application name, URL, locale, timezone
6. **Finish** — runs migrations, seeds defaults, writes the install lock

Installation state is detected from three independent markers
(`storage/installed.lock`, `APP_INSTALLED=true`, and the `installed_at` settings
row), so a DB outage or a lost lock file can't re-open the installer.
Inspect with `php artisan hybridcore:install-status`.

---

## Extensions & Themes

- **Extensions** live in `extensions/{vendor}/{name}/` and are managed from
  **Admin → Extensions** (sync, enable, disable, uninstall, ZIP import/update).
  Scaffold a new one with `php artisan hybridcore:make-extension vendor/name`.
- **Themes** live in `themes/{Name}/` and swap the public-facing UI (the admin
  panel is never themeable). Managed from **Admin → Themes**.

See [BUILDING_EXTENSIONS.md](extensions/BUILDING_EXTENSIONS.md) to build your own.

---

## License

Proprietary. All rights reserved. No redistribution or resale without explicit permission.
