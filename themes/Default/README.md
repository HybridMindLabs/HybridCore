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
| settings | no | Default setting values (overridable from admin) |

## Settings schema

Theme settings (logo, colours, hero text, footer) are stored in the `theme_settings` table.
A visual configuration UI arrives in a future update.

## Future work

- Full Blade view-namespace override (`themes/{slug}/layouts/`, `themes/{slug}/pages/`)
- Per-theme Vite asset pipeline
- Live preview before activation
- Theme package import (ZIP upload)

This theme is the fallback when no other theme is active.
