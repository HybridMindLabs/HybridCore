# Deploying HybridCore

A production install guide for **self-hosting** HybridCore on your own Linux
server. Follow it top to bottom for a first install; jump to [Upgrading](#upgrading)
for updates.

- [1. Server requirements](#1-server-requirements)
- [2. Get the code](#2-get-the-code)
- [3. Install dependencies](#3-install-dependencies)
- [4. Environment configuration](#4-environment-configuration)
- [5. Web server](#5-web-server)
- [6. Background services (Supervisor)](#6-background-services-supervisor)
- [7. Run the installer](#7-run-the-installer)
- [8. Post-install hardening](#8-post-install-hardening)
- [9. Upgrading](#9-upgrading)
- [10. Building a release archive](#10-building-a-release-archive)
- [Troubleshooting](#troubleshooting)

---

## 1. Server requirements

| Component | Minimum | Notes |
|-----------|---------|-------|
| **PHP** | 8.3+ | with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `bcmath` |
| **Database** | MySQL 8.0 / MariaDB 10.6+ | PostgreSQL works but is untested |
| **Redis** | 6+ | queues (Horizon), cache, sessions |
| **Web server** | nginx or Apache | nginx config provided below |
| **Node.js** | 20+ | only to build assets — not needed if you deploy a pre-built release ZIP |
| **Composer** | 2.x | only if you don't ship `vendor/` in the release |
| **Supervisor** | any | keeps the queue worker, websocket server and scheduler alive |

A modest VPS (2 vCPU / 2–4 GB RAM / 20 GB disk) comfortably runs a
mid-sized community.

Install the PHP extensions on Ubuntu/Debian:

```bash
sudo apt install php8.3-{cli,fpm,mysql,mbstring,xml,bcmath,curl,zip,gd,redis} \
    redis-server supervisor nginx
```

---

## 2. Get the code

**Option A — release ZIP (recommended for most).** Download the latest
`hybridcore-X.Y.Z.zip` from the releases page and extract it into your web root:

```bash
cd /var/www
unzip hybridcore-1.0.0.zip        # extracts into ./hybridcore
cd hybridcore
```

**Option B — git clone** (enables one-command panel/CLI updates):

```bash
cd /var/www
git clone https://github.com/HybridMindLabs/HybridCore.git hybridcore
cd hybridcore
```

---

## 3. Install dependencies

Skip the composer step if your release ZIP already bundles `vendor/`
(built with `hybridcore:release --with-vendor`).

```bash
composer install --no-dev --optimize-autoloader

# Only if the release does NOT ship pre-built assets (public/build):
npm ci && npm run build
```

Set ownership so the web server can write runtime directories:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

---

## 4. Environment configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` — the values that matter in production:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# ── Database ──────────────────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=hybridcore
DB_USERNAME=hybridcore
DB_PASSWORD=change-me

# ── Redis-backed queues / cache / sessions ────────────────
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

# ── Search (no external service needed) ───────────────────
SCOUT_DRIVER=database

# ── Real-time (Reverb websockets) ─────────────────────────
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=hybridcore
REVERB_APP_KEY=generate-a-random-string
REVERB_APP_SECRET=generate-another-random-string
REVERB_HOST=your-domain.com
REVERB_PORT=443
REVERB_SCHEME=https

# The browser bundle reads these at BUILD time (npm run build). They must
# match the REVERB_* values above, or live features won't connect.
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# ── Mail (use a real SMTP provider in production) ─────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="noreply@your-domain.com"

# ── Monitoring retention (keep the DB small) ──────────────
PULSE_STORAGE_KEEP="7 days"

# ── Updates ───────────────────────────────────────────────
# Set to false on immutable/CI-managed deploys to hide the panel updater.
HYBRIDCORE_PANEL_UPDATES=true
```

> **Reverb credentials** — `REVERB_APP_KEY`/`SECRET` can be any random
> strings; generate with `php artisan key:generate --show` and strip the
> `base64:` prefix, or `openssl rand -hex 20`.
>
> Because the `VITE_*` values are compiled into the JS bundle, a **pre-built
> release ZIP** carries the maintainer's placeholders — rebuild assets
> (`npm run build`) after setting your own `REVERB_HOST` for live features to
> work, or point `REVERB_HOST` at your real domain before building.

Create the database first if you haven't:

```sql
CREATE DATABASE hybridcore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hybridcore'@'localhost' IDENTIFIED BY 'change-me';
GRANT ALL ON hybridcore.* TO 'hybridcore'@'localhost';
```

Then link storage and cache the framework config:

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 5. Web server

Point the web root at `public/`. The websocket path `/app` must proxy to
Reverb (port 8080). Example nginx server block:

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/hybridcore/public;

    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    index index.php;
    charset utf-8;
    client_max_body_size 20M;   # extension/avatar uploads

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Reverb websockets (BROADCAST over wss://your-domain.com/app)
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}

server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$host$request_uri;
}
```

Get a free TLS certificate with `sudo certbot --nginx -d your-domain.com`.

---

## 6. Background services (Supervisor)

Three long-running processes must always be up. **Do not run
`queue:work` directly** — Horizon supervises the workers and powers the
`/horizon` dashboard.

Create `/etc/supervisor/conf.d/hybridcore.conf`:

```ini
[program:hybridcore-horizon]
command=php /var/www/hybridcore/artisan horizon
directory=/var/www/hybridcore
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/hybridcore/storage/logs/horizon.log

[program:hybridcore-reverb]
command=php /var/www/hybridcore/artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/hybridcore
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/hybridcore/storage/logs/reverb.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

The **scheduler** runs from cron. Add for `www-data`:

```cron
* * * * * cd /var/www/hybridcore && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler drives: server queries, the weekly email digest, achievement
checks, trash/bridge-command pruning, and the health-page heartbeats.

---

## 7. Run the installer

Visit **`https://your-domain.com/install`** in a browser. The wizard:

1. **Requirements** — verifies PHP version, extensions and writable dirs
2. **Database** — tests the connection and runs migrations
3. **Admin account** — creates the first super-admin
4. **Settings** — site name, locale, timezone
5. **Finish** — seeds defaults and writes `storage/installed.lock`

Once `installed.lock` exists, `/install` is sealed off automatically.

> Prefer the CLI? `php artisan migrate --force` then create an admin with
> tinker — but the wizard is the supported path.

---

## 8. Post-install hardening

- **`APP_DEBUG=false`** — never expose stack traces in production
- **Firewall** — only expose 80/443; Reverb (8080) and Redis (6379) stay local
- **Backups** — schedule `mysqldump` + the `storage/app` directory; admins can
  also export from **Admin → System → Backup**
- **Monitoring** — `/pulse` (exceptions, slow queries, 7-day retention) and
  `/horizon` (queue throughput, failed jobs) are linked from
  **Admin → System → Health**; both are super-admin only
- **Social links, captcha, OAuth, mail** — configured in **Admin → Settings**,
  not `.env` (OAuth credentials are stored encrypted)
- **Game-server bridge** — if you connect an in-game plugin, generate the
  per-server token from **Admin → Servers** (plug icon); tokens are hashed
  and shown only once

---

## 9. Upgrading

Always back up the database and `.env` first.

**Git install** — one command handles maintenance mode, pull, composer,
migrations, cache rebuild and queue restart:

```bash
php artisan hybridcore:update
```

**ZIP install** — replace the files (keep `.env` and `storage/`), then:

```bash
php artisan hybridcore:update --local        # skips git pull
# add --no-composer if the release bundled vendor/
```

Admins on a git install also see a **"new version available"** banner in
**Admin → System → Updates** and can apply it from the panel (unless
`HYBRIDCORE_PANEL_UPDATES=false`). The panel and CLI run the identical, safe
sequence and always lift maintenance mode even if a step fails.

After any upgrade, restart the workers so new code is loaded:

```bash
sudo supervisorctl restart hybridcore-horizon hybridcore-reverb
```

---

## 10. Building a release archive

Maintainers package a distributable ZIP with:

```bash
php artisan hybridcore:release                # sources + built assets
php artisan hybridcore:release --with-vendor  # also bundle vendor/ (no composer on server)
```

The archive lands in `storage/app/releases/hybridcore-X.Y.Z.zip` and excludes
`.env`, `.git`, `node_modules`, tests and runtime state — only what a hosting
upload needs.

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| **Jobs not processing** (emails, server queries stuck) | Horizon isn't running — `sudo supervisorctl status`; check `storage/logs/horizon.log` |
| **No live notifications / online counts** | Reverb down or `/app` not proxied — verify the nginx `location /app` block and `REVERB_*` env |
| **500 after upgrade** | Stale caches — `php artisan optimize:clear` then re-cache |
| **"Not a git installation"** on update | You're on a ZIP install — use `php artisan hybridcore:update --local` |
| **Scheduler never runs** | Cron entry missing/typo'd — confirm with `php artisan schedule:list` |
| **Uploads fail** | `client_max_body_size` too low, or `storage/` not writable by `www-data` |

Health at a glance: **Admin → System → Health** shows scheduler, queue worker
and Reverb status in real time.
