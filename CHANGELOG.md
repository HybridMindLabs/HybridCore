# Changelog

All notable changes to HybridCore are documented in this file.

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
conventions and [Semantic Versioning](https://semver.org/).

---

## [0.3.0]

### Extension SDK
- New registries let extensions plug into more of the frontend, each shared with
  per-user permission filtering:
  - **Public navigation** — links in the public site header
  - **Account tabs** — extra tabs in the user's account panel
  - **Profile panels** — panels on the public user profile
  - **User menu** — items in the top-right user dropdown
  - **Search providers** — grouped results in global search
- `extensions:test` now forces the sqlite/array/sync test environment so an
  extension's suite always runs isolated from the live database.

---

## [0.2.0] — Initial public release

The first public release of the HybridCore core — a feature-complete,
production-ready foundation for gaming-community platforms.

### Identity & accounts
- Registration, login, password reset, email verification and two-factor auth (TOTP)
- OAuth sign-in for Discord, Steam and Google with encrypted credential storage
- Roles & permissions with a visual editor, multi-role assignment, primary role and wildcard support
- Account area: profile, avatar/banner upload, privacy controls, active sessions,
  connected accounts, GDPR data export and account deletion
- Post-registration onboarding wizard (avatar, favourite games, suggested follows)

### Community
- Public profiles, members directory, following and activity feeds
- Private messaging with typing indicators and real-time delivery
- Achievements/badges, user blocking, online-presence indicators
- News CMS: categories, tags, comments, @mentions, scheduled publishing
- Content reports, a unified moderation center, and a 30-day restorable trash

### Servers
- Public server browser with real query drivers (A2S for CS1.6/CS2/Rust, Minecraft SLP, FiveM)
- Player counts, maps, 7/30-day uptime history, reviews and ratings
- Secure game-server bridge: hashed per-server tokens and an at-least-once
  command-delivery queue for in-game rewards, purchases and bans

### Admin panel
- Dashboard with growth stats and analytics; activity and audit logs
- User management with a 360° detail view, admin notes, impersonation, IP bans and CSV export
- Settings, legal pages, menus, SEO with auto-sitemap, email templates, maintenance mode
- Extensions and themes management, system health, backups and one-command updates

### Extension SDK
- Manifest-driven extensions: web/admin/api routes, migrations, config, Blade views,
  Artisan commands, published assets, seeders and scheduled tasks
- Integration points: 14 core hooks, WordPress-style value filters, page slots
  rendered site-wide, admin navigation, dashboard widgets and settings pages
- Full lifecycle: install, enable, ZIP import/update, version and inter-extension
  dependency checks, and clean uninstall (rolls back migrations, removes files/settings)
- Tooling: `make-extension` scaffolder, `extensions:zip` packager, `extensions:test` runner

### Platform
- Real-time features via Laravel Reverb (websockets)
- Redis-backed queues supervised by Laravel Horizon
- Global search via Laravel Scout (database driver, swappable for Meilisearch/Typesense)
- Application monitoring via Laravel Pulse (7-day auto-trimmed retention)
- Feature flags via Laravel Pennant; live form validation via Precognition
- Full internationalization (English + Bulgarian), PWA support, security headers with CSP nonces
- Guided installer, GitHub-release update checks, and a `hybridcore:release` packaging command

---

*HybridCore follows semantic versioning from 0.2.0 onward.*
