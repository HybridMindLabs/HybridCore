# Managing Extensions

Everything game-specific is built as an extension on top of the core. Manage them
from **Admin → Extensions**.

> **Trust model.** Extensions are not a sandboxed, vetted marketplace — they
> are trusted, first-party-style PHP plugins that run with full application
> privileges the moment they're enabled. Importing a ZIP validates its
> manifest and archive safety, not what its code does. Only install
> extensions from an author you trust.

## Installing an extension

**From disk** — place the extension folder under `extensions/{vendor}/{name}/`,
then click **Sync from disk**. It appears in the list, disabled.

**From a ZIP** — click **Import**, choose the `.zip`, review the preview
(version, permissions, routes, migrations) and confirm. If the same extension is
already installed, the import becomes an **update**.

## Enabling & disabling

- **Enable** — runs the extension's migrations and seeder, publishes its assets,
  and activates its routes, navigation and widgets. Version and dependency
  requirements are checked first; if they aren't met, enabling is blocked with a
  clear message.
- **Disable** — stops loading the extension. Its data stays in the database.

## Updating

Import a newer ZIP of an installed extension. Files are replaced and any new
migrations run automatically. Downgrades are rejected.

## Uninstalling

On the extension's detail page, click **Uninstall**. You choose whether to also
drop its data:

- **Delete data** — rolls back its migrations (drops its tables) and removes its
  settings and files.
- **Keep data** — removes files but preserves the database, so a future reinstall
  keeps existing content.

## Building your own

See [BUILDING_EXTENSIONS.md](https://github.com/HybridMindLabs/HybridCore/blob/main/extensions/BUILDING_EXTENSIONS.md)
for the full SDK. Quick start:

```bash
php artisan hybridcore:make-extension yourvendor/yourname --with-admin --with-web
php artisan hybridcore:extensions:zip yourvendor/yourname   # package for distribution
```
