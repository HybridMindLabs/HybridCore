# Game-Server Bridge

The bridge lets an in-game plugin (AMXX, SourceMod, or anything that can make
an HTTPS request) securely pull commands queued by the site — vote rewards,
store purchases, bans — and report events back (kills, connects, chat) for
core/extensions to react to.

Implementation: `app/Services/Bridge/BridgeService.php`,
`app/Http/Controllers/Api/BridgeController.php`,
`app/Http/Middleware/AuthenticateBridgeServer.php`.

---

## Setup

1. Admin panel → Servers → open a server → **Generate bridge token**.
2. Copy the token — it is shown once and stored only as a SHA-256 hash. Losing
   it means generating a new one (the old one is invalidated).
3. Configure the plugin with the token and the site's base URL.
4. Revoking the token (Admin → Servers → **Revoke**) disables the bridge for
   that server immediately; a new token must be issued to reconnect.

Each server row has a **command log** (the terminal icon, badged with the
pending count) — the last 10 commands with status, delivery attempts, and
which **source** queued them (`core`, `hybridcore/store`, or any extension
that called `BridgeService::queue()`), so a command showing up in-game is
always traceable back to what asked for it. A still-pending command can be
cancelled from there before it's ever delivered.

---

## Authentication

Every request carries the token as a bearer token:

```
Authorization: Bearer hcb_<40 random chars>
```

`AuthenticateBridgeServer` resolves it to a `Server` via
`hash('sha256', $token)` lookup — the plaintext token is never stored, so a
database leak does not expose live credentials. An invalid or revoked token
gets `401 {"error": "Unauthenticated."}`.

A successful request also stamps `bridge_last_seen_at` on the server
(`saveQuietly()` — no model events), which the admin panel uses to show
online/offline.

All three endpoints are rate-limited to **120 requests/minute** (`BRIDGE_RATE_LIMIT`), keyed by the resolved server — not the caller's IP, so several servers behind one box/NAT don't share a bucket.

---

## Endpoints

### `POST /api/bridge/poll`

Fetch the next batch of pending commands (also doubles as a heartbeat — call
this on an interval, e.g. every 2–5s).

Response:

```json
{
  "server": { "id": 12, "name": "US East #1" },
  "commands": [
    { "id": 501, "command": "hc_give_vip STEAM_0:1:12345 30d" }
  ]
}
```

Up to **25** commands per poll (`BridgeService::PULL_LIMIT`). Commands are
marked `delivered` on hand-out; if they're never acked they're redelivered
after a 2-minute grace period, up to **5** attempts
(`BridgeService::MAX_ATTEMPTS`) before being abandoned. Commands past their
TTL are expired and never delivered, even mid-flight.

### `POST /api/bridge/ack`

Confirm a command actually ran, so it isn't redelivered.

```json
{ "ids": [501, 502] }
```

Response: `{ "acked": 2 }` — the count actually matched (already-acked or
unknown ids are silently ignored).

### `POST /api/bridge/events`

Report telemetry back to the site — kills, connects, chat, whatever an
extension wants to listen for.

```json
{
  "events": [
    { "id": "seq-42", "type": "player.kill", "at": 1699999999, "data": { "killer": "...", "victim": "..." } }
  ]
}
```

- `id` — plugin-assigned, used for de-duplication (a resend of an already-seen
  id is accepted but not stored twice — safe to retry on a timeout).
- `type` — free-form string, max 64 chars, no control characters.
- `data` — arbitrary object, serialized form capped at 8000 chars.
- `at` — optional unix timestamp; omit to use server receipt time.

Up to **100** events per call (`BridgeService::INGEST_LIMIT`). Accepted events
are dispatched to `App\Support\Hooks` listeners via
`App\Jobs\ProcessBridgeEvents` — see `extensions/BUILDING_EXTENSIONS.md` for
subscribing to them from an extension. Raw events are pruned after **30 days**.

Response: `{ "accepted": ["seq-42"] }` — drop these ids from the plugin's
resend queue.

---

## Queueing commands (core / extensions)

```php
app(BridgeService::class)->queue($server, "hc_give_vip {$steamId} 30d", 'hybridcore/store');
```

`$command` must be 1–500 characters with no control characters (rejected, not
sanitised — newlines could smuggle a second console command into the game
server). `$source` is a free-form label capped at 64 chars — it's what shows
up in the admin command log, so use something identifiable
(`hybridcore/store`, not `plugin`). TTL defaults to 24h.

A queued command can be pulled back before delivery:

```php
app(BridgeService::class)->cancel($server, $command); // false if already delivered
```

---

## Security notes

- Token = 40 bytes of entropy, `hcb_` prefix, only the hash is persisted.
- One token per server — scope is implicit (a plugin can only ever act as the
  server it authenticates as).
- Command payloads are length- and control-character-checked before storage;
  the plugin is still responsible for treating `command` as an opaque console
  string, not something to further interpolate.
- Event `data` is capped and stored as-is; if an extension renders it back to
  a browser, that extension owns escaping it (see `SECURITY.md`'s extension
  trust model — a compromised or malicious game server can only feed data
  into events, not execute PHP).
