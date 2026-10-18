# HybridCore Design System & UI Specifications

Official design guidelines, tokens, component standards, and visual architecture for HybridCore.

---

## 1. Design Philosophy & Principles

HybridCore is a high-performance, modular platform built for gaming communities, competitive multiplayer networks, and server operators. The interface must reflect three core values:

1. **Tactical Precision**: Information density is balanced with breathing room. Server metrics, player counts, ping, and logs must be readable at a glance without clutter.
2. **Speed & Snappiness**: Zero visual bloat. Every transition is immediate (`duration-150 ease-out`). Surfaces react instantly to hover and click states.
3. **Dark-First Immersion**: The primary canvas is deep, void-level dark, matching the atmosphere of modern gaming software and esports dashboards, while maintaining certified WCAG AA contrast.

---

## 2. Color Palette & Design Tokens

HybridCore uses an exact, calibrated palette declared in `resources/css/app.css` via Tailwind v4 tokens. These values are strict and must be preserved across all core views, themes, and official extensions.

### 2.1 Dark Mode (Primary Palette)

| Token | Hex Value | Role & Usage |
|---|---|---|
| `body / canvas` | `#050810` | Base canvas background for the entire document |
| `--color-hc-base` | `#0a0f1a` | Base layout layer, outer wrapper, sidebar backgrounds |
| `--color-hc-surface` | `#111827` | Standard card surfaces, table containers, modal bodies |
| `--color-hc-elevated` | `#1a2236` | Elevated cards, dropdown menus, popovers, active tooltips |
| `--color-hc-border` | `#1e2d47` | Primary structural borders, table dividers, panel outlines |
| `border-zinc-800/70` | `rgba(39,39,42,0.7)` | Hairline container borders in administrative consoles |

### 2.2 Accent & Interactive Tokens

| Token | Hex Value | Role & Usage |
|---|---|---|
| `--color-hc-accent` | `#22d3ee` | Electric Cyan: primary buttons, active tabs, focus rings, brand badges |
| `--color-hc-accent-hover` | `#06b6d4` | Hover state for primary actions |
| `blue-500` / `blue-600` | `#3b82f6` / `#2563eb` | Secondary interactive links and authentication accents |

### 2.3 Typography & Text Colors

| Token | Hex Value | Contrast | Role & Usage |
|---|---|---|---|
| `--color-hc-text` | `#f1f5f9` | 15.8:1 | Headings, primary titles, active input text |
| `--color-hc-muted` | `#94a3b8` | 7.2:1 | Subtitles, labels, secondary metadata, table headers |
| `--color-zinc-500` (dark) | `#94949d` | 5.8:1 | Form hints, timestamps, footer links, breadcrumbs |
| `--color-hc-faint` | `#475569` | 3.2:1 | Disabled labels, placeholder icons, structural grid lines |

### 2.4 Semantic & Status Tokens

| Token | Hex Value | Role & Usage |
|---|---|---|
| `--color-hc-success` | `#22c55e` | Online server status, positive balance, verified badge, live ping |
| `--color-hc-warning` | `#f59e0b` | Maintenance banners, pending reviews, warnings, report alerts |
| `--color-hc-danger` | `#ef4444` | Banned status, destructive buttons, critical errors, server offline |
| `--color-hc-info` | `#3b82f6` | System notifications, announcements, informational callouts |

### 2.5 Light Mode Calibration

To maintain WCAG AA readability without eye-strain glare, light mode uses soft off-whites:
- **Card Surfaces (`--color-white`)**: `#f7f8fa`
- **Page Background (`--color-zinc-50`)**: `#eceef2`
- **Subtle Fills (`--color-zinc-100`)**: `#e1e4ea`
- **Borders (`--color-zinc-200`)**: `#d3d7df`
- **Muted Text (`--color-zinc-400` / `500` / `600`)**: `#65656f` / `#5c5c66` / `#4a4a54`

---

## 3. Typography Hierarchy

### 3.1 Font Families
- **Interface / Sans**: `Inter`, ui-sans-serif, system-ui, sans-serif
- **Data / Monospace**: `ui-monospace`, `Cascadia Code`, monospace (mandatory for: SteamIDs, Server IPs, Ports, Command lines, Console outputs, Latency/Ping, Timestamps)

### 3.2 Scale & Weight Mapping

| Level | Size | Weight | Tracking | Usage |
|---|---|---|---|---|
| `Page Title` | `24px` (`text-2xl`) | Bold (`font-bold`) | `-0.02em` (`tracking-tight`) | Page headers, hero sections |
| `Section Title` | `18px` (`text-lg`) | Semibold (`font-semibold`) | `-0.01em` | Card headers, modal titles, panel titles |
| `Card Subtitle` | `14px` (`text-sm`) | Normal / Medium | Normal | Descriptions under section headings |
| `Body Text` | `14px` (`text-sm`) | Normal (`font-normal`) | Normal | Standard reading content, table rows, form inputs |
| `Label / Eyebrow` | `11px` (`text-[11px]`) | Bold (`font-bold`) | `+0.05em` (`tracking-widest`, uppercase) | Form field labels, category pills, meta categories |
| `Monospace Data` | `13px` (`text-[13px]`) | Medium (`font-mono`) | Normal | Server IPs, console commands, stats |

---

## 4. Surfaces, Radius & Elevation

### 4.1 Corner Radii
- **`rounded-xl` (`12px`)**: Main container cards, modal dialogs, dashboard stat widgets.
- **`rounded-lg` (`8px`)**: Form inputs, buttons, dropdown popovers, alert banners.
- **`rounded-md` (`6px`)**: Badges, table pills, pagination items.
- **`rounded-full`**: User avatars, status indicator dots, toggle pills.

### 4.2 Shadows & Depth
- **Card Elevation**: `shadow-[0_2px_8px_rgba(0,0,0,0.4)]`
- **Modal / Popover Elevation**: `shadow-[0_8px_32px_rgba(0,0,0,0.6)]` with border `border-[#1e2d47]`
- **Texture**: `hc-texture` utility for subtle grain on dashboard canvases.

---

## 5. Component Standards

### 5.1 Buttons (`UI/Button.vue`)
- **Primary Button**:
  - Background: `bg-[#22d3ee]` (`bg-hc-accent`) or `bg-blue-600`
  - Text: High contrast dark text (`text-zinc-950 font-semibold`)
  - Hover: `hover:bg-[#06b6d4]` with subtle glow
  - Transition: `transition-colors duration-150`
- **Secondary / Outline Button**:
  - Background: `bg-transparent` with border `border-zinc-800` or `border-[#1e2d47]`
  - Text: `text-zinc-200`
  - Hover: `hover:bg-zinc-800/60 hover:text-white`
- **Destructive Button**:
  - Background: `bg-red-500/10` with border `border-red-500/30`
  - Text: `text-red-400`
  - Hover: `hover:bg-red-500 hover:text-white`

### 5.2 Form Inputs (`UI/Input.vue`)
- **Container**: `bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm`
- **Focus Ring**: `focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500/20`
- **Placeholder**: `placeholder:text-zinc-600`
- **Error State**: `border-red-500 focus:border-red-500 focus:ring-red-500/20` with helper text in `text-red-400 text-xs`

### 5.3 Server & Status Indicators
- **Online Server**:
  - Dot: `w-2 h-2 rounded-full bg-emerald-500` with animated pulse `animate-ping`
  - Pill: `bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium px-2 py-0.5`
- **Offline / Maintenance**:
  - Dot: `w-2 h-2 rounded-full bg-red-500` (or `bg-amber-500` for maintenance)
  - Pill: `bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium px-2 py-0.5`

### 5.4 Data Tables
- Header row: Uppercase `text-[11px] font-bold tracking-wider text-zinc-500 bg-zinc-900/40 border-b border-zinc-800/70`
- Table rows: `border-b border-zinc-800/40 hover:bg-zinc-800/20 transition-colors`
- Numeric cells: Right-aligned with monospace font (`text-right font-mono text-zinc-300`)

---

## 6. Accessibility (A11y) & Usability Standards

1. **Color is Never the Sole Indicator**: Statuses must always include an icon or text label alongside colored dots.
2. **Keyboard Focus**: Every interactive control must have a visible `:focus-visible` ring.
3. **Form Labels**: Every input field requires an explicit `<label for="...">` with readable contrast.
4. **Touch Targets**: Minimum interactive tap target of `36px x 36px` on desktop and `44px x 44px` on mobile screens.
