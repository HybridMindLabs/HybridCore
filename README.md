# HybridCore

> A modular, self-hosted platform for gaming communities, server owners and multiplayer projects.

Feature-complete core with a full extension SDK, payments and a public API. Production-ready for self-hosting.
See [CHANGELOG.md](CHANGELOG.md) for the current version and release history.

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

## Features

**Identity & accounts**
- Registration, login, password reset, email verification, 2FA (TOTP + WebAuthn/passkeys)
- Account lockout after repeated failed logins, with email alerts on new-device sign-ins
- Mandatory-2FA policy for the admin panel — configurable per-admin grace period, not a hard cutover
- OAuth sign-in (Discord, Steam, Google) with encrypted credential storage
- Roles & permissions with a visual editor, multi-role assignment and wildcards
- Full account area: profile, avatar/banner, privacy, sessions (list & revoke), connected accounts, GDPR export & deletion
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
- Hidden servers (e.g. backends behind BungeeCord/Velocity) that stay manageable but never appear publicly,
  plus a display address for servers reached through a proxy or domain
- **Game-server bridge** — a secure, token-authenticated command queue so an
  in-game plugin can deliver rewards, purchases and bans

**Payments**
- Stripe, PayPal, crypto and Revolut gateways behind one driver interface
- Payment ledger with refunds, automatic invoices, subscriptions and signed gateway webhooks
- CSV export of payments and invoices for any date range

**Public API**
- REST API under `/api/v1`: servers, live player lists, site stats, news, member profiles, notifications
- Admin-issued service tokens with scoped abilities — e.g. `users:lookup` lets a Discord bot
  find the member linked to a Discord ID

**Admin panel**
- Dashboard with growth stats, revenue (today, month, chosen period), analytics and activity
- Search across users, servers, news, payments and admin pages (`Ctrl K`), with type filters
- Users (360° detail view, admin notes, impersonation), IP bans, CSV export
- Sortable lists, adjustable page sizes and saved filter views
- **Sudo mode** — destructive actions (deleting users, refunds, tokens, backups, updates) ask for
  the password again, once every 15 minutes
- Full audit trail: who changed what, with old → new values and stored secrets redacted
- Per-admin alerts (server offline, contact message, content report, failed payment) in the panel or by email,
  sent to every staff member whose role can act on them
- API tokens for external integrations — scoped abilities, per-token rate limiting, one-click rotation
- Outbound webhooks — any core or extension event (including `server.offline` / `server.online`),
  signed deliveries, delivery history, test-send, Discord-formatted embeds
- Settings, legal pages, menus, SEO + auto sitemap, email templates, maintenance mode
- Extensions & themes management, system health, scheduled backups
- **Self-update** from the panel or CLI — pre-flight checks, a database backup before migrating,
  and automatic rollback of code and migrations if any step fails

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
- **[BRIDGE.md](BRIDGE.md)** — the game-server bridge protocol: auth, endpoints, command/event delivery semantics
- **[CHANGELOG.md](CHANGELOG.md)** — release history
- **[CONTRIBUTING.md](CONTRIBUTING.md)** — branch workflow, commit style, code standards
- **[CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)** — community standards
- **[SECURITY.md](SECURITY.md)** — vulnerability reporting

---

## Local Development

### Prerequisites

- PHP 8.3+ with the required extensions (see [DEPLOYMENT.md](DEPLOYMENT.md#1-server-requirements))
- Composer 2.x and Node.js 20.19+
- MySQL/MariaDB and Redis

Use whatever local environment you prefer (Laravel Herd/Valet, Sail, a plain
LEMP stack, etc.).

### First-time setup

```bash
git clone https://github.com/HybridMindLabs/HybridCore.git hybridcore
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
./vendor/bin/phpstan analyse   # Static analysis (Larastan)
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
  - ZIP imports are size- and path-checked; an update swaps files atomically and
    rolls back if its migrations fail.
  - An extension that fails to load is flagged in the admin instead of taking the site down.
  - Each extension keeps its own test suite in its own repository — the core suite
    only covers the bundled `hybridcore/demo` reference extension.
- **Themes** live in `themes/{Name}/` and swap the public-facing UI (the admin
  panel is never themeable). Managed from **Admin → Themes**.

See [BUILDING_EXTENSIONS.md](extensions/BUILDING_EXTENSIONS.md) to build your own.

---

## Updating

```bash
php artisan hybridcore:update          # git install: pull, composer, migrate
php artisan hybridcore:update --local  # ZIP install: files already replaced
```

Or use **Admin → Update**, which also rebuilds the frontend assets. Updates stop before
changing anything if there are local changes to tracked files or too little disk space,
and roll back to the previous version if any step fails. Set the channel to **beta**
to be offered pre-releases.

---

## License

[PolyForm Noncommercial 1.0.0](LICENSE) — free to run and self-host for your own communities
and non-profit projects; selling or reselling HybridCore itself is not permitted.

Extensions and themes are an explicit exception: their authors own them and may sell or
license them however they like. See [LICENSE](LICENSE) for the full terms.
