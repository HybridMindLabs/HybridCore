# Default Theme

The official default dark theme for HybridCore.

## Structure

```
themes/Default/
├── theme.json          # Manifest (required)
├── screenshot.png      # Preview image (optional, shown in admin)
├── README.md
├── layouts/            # Blade layout overrides (future)
├── pages/              # Per-page view overrides (future)
├── components/         # Reusable view partials (future)
└── assets/             # Theme-specific CSS/JS (future)
```

## theme.json fields

| Field | Required | Description |
|-------|----------|-------------|
| name | yes | Human-readable name |
| slug | yes | Unique kebab-case identifier |
| version | yes | SemVer string |
| author | no | Author name |
| description | no | Short description |
| type | no | `official`, `community`, or `custom` |
| supports | no | Array of supported module types |
| preview_image | no | Filename of preview screenshot inside theme directory |
| requires_license | no | `true` for a paid theme — activation is refused until a key is stored |
| settings_schema | no | Array of editable fields, rendered by the admin UI (see below) |

## Settings schema

`settings_schema` declares the fields the admin panel renders for this theme.
Saved values land in the `theme_settings` table and override the field's
`default`; a field with no saved row falls back to its `default`.

| Key | Required | Description |
|-----|----------|-------------|
| key | yes | Storage key. For `color` fields this also names the CSS variable — see below |
| type | yes | `color`, `text`, `textarea`, `toggle`, `select`, or `image` |
| label | yes | Field label in the admin form |
| group | no | Fieldset heading, defaults to `General` |
| default | yes | Value used until an admin overrides it |
| options | for `select` | Allowed values; doubles as the server-side `in:` rule |

```json
"settings_schema": [
    { "key": "hc_accent", "type": "color", "label": "Accent", "group": "Colors", "default": "#22d3ee" },
    { "key": "density", "type": "select", "label": "Density", "default": "comfortable", "options": ["compact", "comfortable"] }
]
```

### How `color` fields reach the page

A `color` value is written to `:root` as `--color-{key-with-dashes}` before
first paint, which is the namespace Tailwind compiles every colour utility
against. So `hc_accent` sets `--color-hc-accent`, and every `bg-hc-accent` /
`text-hc-accent` on the page repaints. The same works for stock Tailwind
tokens: a key of `zinc_900` overrides `--color-zinc-900` site-wide.

Only values matching `#rrggbb` are applied, and they go through
`style.setProperty()` — never string-interpolated into a `<style>` tag.

Values are validated server-side against the schema: an unknown key is
dropped, a bad colour is rejected, and a `select` must match its `options`.

> Core's public components are still largely styled with literal `zinc-*`
> utilities rather than the `hc-*` tokens, so overriding `hc_*` currently
> repaints less of the page than it will once those are migrated.

## Future work

- Full Blade view-namespace override (`themes/{slug}/layouts/`, `themes/{slug}/pages/`)
- Per-theme Vite asset pipeline
- Live preview before activation
- Theme package import (ZIP upload)

This theme is the fallback when no other theme is active.
