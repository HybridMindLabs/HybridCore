# Changelog

All notable changes to HybridCore are documented in this file.

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
conventions and [Semantic Versioning](https://semver.org/).

---

## [0.4.1] — 2026-08-20

### Fixed

- rehydrate Server::cachedSnapshot from raw attributes

### Changed

- Merge pull request #77 from HybridMindLabs/feature/bridge-admin-tools
- Merge branch 'main' into feature/bridge-admin-tools

---

## [0.4.0] — 2026-08-13

### Added

- add Shop extension with cart checkout, bundles, sale pricing, wishlist, gifting
- check-now button and nightly update check
- discover and apply updates from a declared HTTPS feed
- scheduled update check with admin notice; rebuild assets on panel update
- E2E in CI with fixed specs, halve PHPStan baseline, coverage gate, dead code cleanup
- add ESLint 9 flat config for Vue/TS and wire it into CI
- adopt PHPStan level 5 with baseline; fix broken avatar job and cache-unsafe env() read

### Fixed

- scope PHPStan to the core; extensions live in their own repos
- give map hover one timing curve instead of two competing ones
- smooth map and cover hover to match the home page
- smooth server row hover by transitioning opacity with the scale
- cap presence widget and compact chips so many users stay readable
- auto-discover extension Tailwind sources at build time
- run bundled extension test suites in one process and gate composer audit in CI

### Changed

- Merge pull request #72 from HybridMindLabs/feature/bridge-admin-tools
- Merge branch 'main' into feature/bridge-admin-tools
- Merge pull request #71 from HybridMindLabs/feature/bridge-admin-tools
- Fix flaky float assertion: JSON drops trailing .0 on whole numbers
- Fix stats amount type: force float division
- Fix PHPStan: use query builder for invoice stats aggregate
- Animate error pages, bring ip-banned into shared layout
- Merge branch 'main' into feature/bridge-admin-tools
- Merge pull request #70 from HybridMindLabs/feature/bridge-admin-tools
- Fix PHPStan: payable can be null on a dangling morphTo
- Fix import order in routes/web.php
- Merge branch 'main' into feature/bridge-admin-tools
- Add invoices and a unified payment/subscription transaction ledger
- Merge pull request #69 from HybridMindLabs/feature/bridge-admin-tools
- Merge branch 'main' into feature/bridge-admin-tools
- Add core recurring billing: subscriptions, Stripe subscription checkout, webhook dispatcher
- Add layout.top slot for site-wide banners; gitignore stray NUL and tool scratch output
- Fix player chart rendering solid black: gradient id built from translated label broke url(#...) fragment lookup
- Point .env.example to the donations extension's Stripe setup docs
- Merge pull request #68 from HybridMindLabs/feature/bridge-admin-tools
- Merge branch 'main' into feature/bridge-admin-tools
- Payments: allow guest checkout (nullable user_id)
- Merge pull request #65 from HybridMindLabs/feature/bridge-admin-tools
- Merge branch 'main' into feature/bridge-admin-tools
- Add PaymentService::refund() + abandoned-payment cleanup, bump dompurify to 3.4.13
- Add core payment layer: provider-agnostic checkout + webhook pipeline (Stripe driver)
- Merge pull request #54 from HybridMindLabs/dependabot/github_actions/softprops/action-gh-release-3
- Merge pull request #60 from HybridMindLabs/feature/bridge-admin-tools
- Fake the queue in extension lifecycle tests to stop real npm builds racing under --parallel
- Fix AdminExtensionTest: announcements no longer bundled in core, test its own directory exclusion instead
- Move announcements extension to its own repo
- System Health: flag sync queue driver as misconfigured, not silently ok
- Fix PHPStan: server relation on ServerReview is never null (cascade delete)
- Remove dead orphan Updates page, throttle outbound/expensive admin actions
- Extensions & Themes: fade-in stagger, fix AbilityRegistry test after description field
- API Tokens & Webhooks: give webhook events labels/groups like abilities already had, explain both pages up front
- Webhooks: prune old delivery log entries daily, fade-in stagger on API Tokens and Webhooks
- System Logs: level/text filtering, refresh without reload, explanatory tooltips, fade-in stagger
- Backup/Export: auto-refresh list after generating a full backup, show truncated-list count, fade-in stagger
- Fix update flow: npm ci before build, restore assets on failed rebuild, fix folder perms, restart SSR, guard against updating an uninstalled instance
- Reorganize System Health: category groups, per-check explanations, refresh button, checked-at timestamp
- Settings: role dropdown for default user role, dedupe duplicate locale control, lock theme picker to Themes page, add URL/timezone auto-fill
- Rework Analytics and Activity Log: real Highcharts, accurate event map, paginated/filterable log, fix broken Email Logs pagination
- Polish Contact/Email admin: message search, fade-in stagger, unsaved indicators
- Polish News/Moderation/Trash admin: article search+category+status filter, shared EmptyState, fade-in stagger, sticky sidebar, unsaved indicators
- Polish Rules admin: fade-in stagger, shared EmptyState, sticky sidebar, unsaved indicator
- Polish Menus admin: fade-in stagger, shared EmptyState, sticky settings panel, unsaved indicator
- Polish Pages admin: search/status filter, fade-in stagger, sticky sidebar, unsaved-changes indicator
- Fix server stats undercounting past page 1, add reviews search, and polish empty states across Servers pages
- Scale Roles pages for many roles/permissions: search, expand/collapse all, sticky save sidebar
- Add fade-in stagger and unsaved-changes indicator to Edit User
- Polish Users pages with empty states and tab counts; dedupe dashboard tooltip/animation onto existing shared components
- Fill dashboard empty states, add donut center total, and drop the glow hover effect
- Add help tooltips, trend indicator, empty states and subtle motion to the admin dashboard
- Remove dead AuditLog page superseded by ActivityLog
- Let admins revoke a user's active sessions and retry failed webhook deliveries
- Fix sidebar losing scroll position on navigation, add collapsible sections and a visible search entry point
- Raise CI coverage floor to 60% (measured 67.7%)
- Fix query driver dropdown using a hardcoded list instead of the driver registry
- Add bridge command dashboard with source tracking, cancel action, and per-server rate limiting
- Merge pull request #59 from HybridMindLabs/security/hardening-pass
- Merge branch 'main' into security/hardening-pass
- Add account lockout, WebAuthn passkeys, and hardened security headers
- Alert on login from a new IP, extend login tracking to admin panel
- Enforce mandatory 2FA for admin access with per-admin grace period
- Add webhook test delivery, delivery history log, and extension-declared webhook events
- Merge pull request #58 from HybridMindLabs/security/hardening-pass
- Fix AdminExtensionTest referencing a local-only, non-git-tracked extension
- Rework demo extension into a full SDK reference, redirect stale fixture tests
- Fix phpstan generics and unsafe property access in ApiTokenController
- Merge branch 'security/hardening-pass' of github.com:HybridMindLabs/HybridCore into security/hardening-pass
- Add API Token Abilities
- Merge branch 'main' into security/hardening-pass
- Add API Token Abilities
- Add admin-issued API tokens with extensible ability registry
- Notify admins on new contact messages and server offline transitions
- Add extension settings schema, mirroring theme settings
- Wire public nav/footer accent color to theme's hc-accent token
- Add per-theme settings schema with license-gated paid themes
- Add admin-configurable scheduled database backups
- Merge pull request #57 from HybridMindLabs/security/hardening-pass
- Merge branch 'main' into security/hardening-pass
- Clear the PHPStan baseline: fix root causes instead of suppressing
- Merge pull request #50 from HybridMindLabs/security/hardening-pass
- Automate dependency merges and release versioning
- Automate dependency merges and release versioning
- Gate admin panel access on admin.access permission instead of raw is_admin
- Fix e2e: assert the actual 2FA setup error message, not the login-challenge one
- Automate patch releases on push to main
- Fix notifications tab rendering account layout twice; drop the flaky moderation e2e test
- Fix leaked browser context in moderation.spec.ts; upload laravel.log on e2e failure
- Fix e2e CI failures: register/admin-login rate limits ignored LOGIN_RATE_LIMIT override
- Low-priority hardening: scoped IP-ban exemption, per-account login lockout, forced secure cookies in prod, DOMPurify on admin preview, e2e coverage for news/messaging/profile/2FA/moderation/page-html
- Fix flaky test: ServerFactory could roll a private-range IP that the SSRF guard then rejected
- Ops hardening: document QUEUE_CONNECTION, index activity_log, add npm audit to CI
- Merge branch 'main' into security/hardening-pass
- Security hardening: signature verification for core and extension self-updates
- Security hardening: registration captcha, admin permission docs, page XSS sanitizer
- Security hardening: admin 2FA bypass, SSRF guard, API auth rate limiting
- Merge pull request #49 from HybridMindLabs/security/hardening-pass
- Security hardening: API profile privacy, CORS lockdown, no-store auth pages
- Merge pull request #48 from HybridMindLabs/feature/logged-in-sidebar-polish
- Drop stale viewerSummary baseline entries after fixing its return type
- Wrap the account notifications page in the account panel shell
- Merge branch 'main' into feature/logged-in-sidebar-polish
- Wrap the account notifications page in the account panel shell
- Redesign the logged-in sidebar card into a compact command center
- Polish the logged-in sidebar badges into labelled chips
- Merge pull request #47 from HybridMindLabs/feature/extension-license-updates
- Merge branch 'main' into feature/extension-license-updates
- Compact the extensions admin into a table; keep updates inline
- Merge pull request #46 from HybridMindLabs/feature/extension-license-updates
- Merge branch 'main' into feature/extension-license-updates
- Document the automated extension release workflow
- Merge pull request #45 from HybridMindLabs/feature/extension-license-updates
- Merge branch 'main' into feature/extension-license-updates
- Add GitHub-based extension updates with license support
- Merge pull request #44 from HybridMindLabs/chore/pagespeed-a11y
- Merge branch 'main' into chore/pagespeed-a11y
- Merge pull request #42 from HybridMindLabs/chore/pagespeed-a11y
- Merge branch 'main' into chore/pagespeed-a11y
- perf(core): isolate storage per parallel worker; fix manifest read race
- Merge pull request #39 from HybridMindLabs/chore/pagespeed-a11y
- Merge branch 'main' into chore/pagespeed-a11y
- GDPR extension points: DATA_EXPORT filter + USER_ANONYMIZED hook
- Merge pull request #38 from HybridMindLabs/chore/pagespeed-a11y
- Merge branch 'main' into chore/pagespeed-a11y
- Tailwind: @source extension Vue trees so their utility classes build
- Merge pull request #37 from HybridMindLabs/chore/pagespeed-a11y
- Merge branch 'main' into chore/pagespeed-a11y
- Emit fallback title only when SSR head lacks a title tag
- Untrack built manifest
- Merge pull request #33 from HybridMindLabs/chore/pagespeed-a11y
- Merge branch 'main' into chore/pagespeed-a11y
- Remove onboarding wizard, move game picker to preferences
- Merge pull request #32 from HybridMindLabs/chore/pagespeed-a11y
- Merge branch 'main' into chore/pagespeed-a11y
- Rework social icons with brand colours and hover states
- Fix text contrast in both themes and definition list structure
- Add meta descriptions to remaining public pages
- Enlarge carousel touch targets and give landmarks distinct names
- Fix accessible name on menu button and definition list structure
- Fix SSR hydration mismatches and captcha CSP
- Merge pull request #31 from HybridMindLabs/chore/pagespeed-a11y
- Merge branch 'main' into chore/pagespeed-a11y
- Warn about manual steps left after update
- Merge pull request #30 from HybridMindLabs/chore/pagespeed-a11y
- Merge branch 'main' into chore/pagespeed-a11y
- Generate systemd units for websocket and SSR services
- Merge pull request #29 from HybridMindLabs/chore/pagespeed-a11y
- Document SSR process supervision
- Convert game icons to WebP
- Optimise news uploads and game covers
- Add Inertia SSR for public pages
- Add a skip link and lazy-load the search modal images
- Bundle icons into one chunk and close the static a11y gaps
- Remove unreferenced components and a duplicate seeder
- Merge pull request #28 from HybridMindLabs/redesign/public-pages
- Raise status and error colours to AA on the light theme
- Fix unreadable form errors and blind password entry across auth
- Unify the auth screens and drop the dead player-history code
- Render rule pages server-side through a shared markdown renderer
- Render legal pages server-side and fix Cyrillic heading anchors
- Use the lead image as the article hero background
- Rebuild the article layout around reading
- Render markdown articles, paginate comments, fix word count
- Fix news search, category counts and missing dates
- Fix account deletion leaving personal data and locking out OAuth users
- Sign out other devices when the password changes
- Stop OAuth-only accounts locking themselves out on unlink
- Drop dead notification format from preferences, fix timezone input
- Make session revocation actually sign devices out
- Fix blank 2FA QR code, dead disable endpoint, and swallowed JSON errors
- Fix email preference switches that never affected delivery
- Merge pull request #27 from HybridMindLabs/feature/game-drivers
- Merge branch 'main' into feature/game-drivers
- Show real game icons in the admin servers and games lists
- Resolve game icons and covers across formats, preferring WebP
- Merge pull request #25 from HybridMindLabs/feature/game-drivers
- Add hybridcore:query-servers to run the sweep on demand
- Ship default query ports and seven more games
- Fetch player lists in the concurrent batch, drop GameQ
- Concurrent server sweep and query-port UI
- Add first-party game-server query drivers in app/Games
- Merge pull request #24 from HybridMindLabs/fix/fresh-install-frontend
- Merge branch 'main' into fix/fresh-install-frontend
- Generate systemd units instead of asking for a crontab entry
- Merge pull request #23 from HybridMindLabs/fix/fresh-install-frontend
- Merge branch 'main' into fix/fresh-install-frontend
- Let a failed install be retried
- Merge pull request #22 from HybridMindLabs/fix/fresh-install-frontend
- Merge branch 'main' into fix/fresh-install-frontend
- Redesign the installer
- Merge pull request #21 from HybridMindLabs/fix/fresh-install-frontend
- Merge branch 'main' into fix/fresh-install-frontend
- Check .env is writable, and tell people how to fix what fails
- Merge pull request #20 from HybridMindLabs/fix/fresh-install-frontend
- Merge branch 'main' into fix/fresh-install-frontend
- Check the directories the installer will actually write to
- Merge pull request #19 from HybridMindLabs/fix/fresh-install-frontend
- Merge branch 'main' into fix/fresh-install-frontend
- Move the installer's admin step off an /admin path
- Merge pull request #18 from HybridMindLabs/fix/fresh-install-frontend
- Merge branch 'main' into fix/fresh-install-frontend
- Trust reverse proxy headers so HTTPS installs work behind Cloudflare
- Merge pull request #17 from HybridMindLabs/fix/fresh-install-frontend
- Make Echo optional and ship the icons the manifest promises
- Merge pull request #16 from HybridMindLabs/fix/installer-db-resilience
- Merge branch 'main' into fix/installer-db-resilience
- Keep the installer alive when the database is unreachable
- Merge pull request #15 from HybridMindLabs/fix/smart-installer
- Make the installer bulletproof against unconfigured databases
- Merge pull request #14 from HybridMindLabs/dependabot/npm_and_yarn/js-minor-and-patch-bfdc771a24
- Merge pull request #11 from HybridMindLabs/HybridMind1337-patch-1
- Update README.md
- Merge pull request #10 from HybridMindLabs/feature/bridge-ingest
- Add bridge event ingest (telemetry in) with registry, queue and rolling log
- Merge pull request #9 from HybridMindLabs/fix/extension-translations-nesting
- Merge branch 'main' into fix/extension-translations-nesting
- Fix extension translations not resolving via t() on the frontend
- Merge pull request #8 from HybridMindLabs/feature/extension-sdk-registries
- Merge branch 'main' into feature/extension-sdk-registries
- Add extension SDK frontend registries and bump to 0.3.0
- Add extension SDK frontend registries and bump to 0.3.0
- Add extension SDK registries, admin command palette and notification types, bump to 0.3.0
- Add extension SDK registries and admin command palette, bump to 0.3.0
- Add extension SDK registries and bump to 0.3.0
- Add extension SDK registries for public nav, account/profile tabs, user menu and search
- Merge pull request #7 from HybridMindLabs/alert-autofix-2
- Potential fix for code scanning alert no. 2: Workflow does not contain permissions
- Merge pull request #6 from HybridMindLabs/feature/public-nav-registry
- Merge branch 'main' into feature/public-nav-registry
- Add public navigation registry to the Extension SDK
- Update FUNDING.yml
- Create FUNDING.yml
- HybridCore v0.2.0

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
- New core payment layer: a provider-agnostic checkout + webhook pipeline
  (Stripe today, any hosted-checkout gateway is a new driver later) so paid
  extensions never touch a payment SDK directly — they call
  `PaymentService::checkout()` and listen on `$registry->payments()->on('paid', ...)`.

### Themes
- Themes now declare a `settings_schema` in `theme.json` — typed, admin-editable
  fields (colors, text, toggles, selects) that actually change the live site
  instead of sitting inert. Paid themes are gated behind a license key, same
  model as paid extensions.
- Core updates now re-run `hybridcore:themes:sync` automatically so an
  upgraded install picks up manifest schema changes without a manual step.

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
