# LogScope Guard

[![License](https://img.shields.io/github/license/AhmedMerza/laravel-logscope-guard?style=flat-square)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue?style=flat-square)](https://php.net)

IP blocking and cross-environment blacklist sync for [LogScope](https://github.com/AhmedMerza/laravel-logscope).

A malicious IP hits staging. You block it from the LogScope UI. Every other environment syncs within minutes — automatically.

## Quick Start

```bash
composer require ahmedmerza/logscope-guard
php artisan guard:install
```

That's it. A **Block IP** button now appears in the LogScope detail panel whenever a log entry has an IP address.

---

## How It Works

```
Admin blocks IP in LogScope UI (staging)
    │
    ├─► DB row created + Redis hash rebuilt → staging protected immediately
    │
    └─► Queued job pushes block to master env
            │
            └─► Every other env pulls from master via guard:sync (every 5 min)
                    └─► Redis rebuilt → all environments protected
```

Every incoming request is checked against a **Redis Hash** before any middleware, session, auth, or route runs. No DB hit per request.

---

## Table of Contents

- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#%EF%B8%8F-configuration)
- [Cross-Environment Sync](#-cross-environment-sync)
- [Auto-Block Rules](#-auto-block-rules)
- [Artisan Commands](#-artisan-commands)
- [Security Notes](#-security-notes)
- [License](#-license)

---

## 📋 Requirements

- PHP 8.2+
- Laravel 10+
- Redis
- [ahmedmerza/logscope](https://github.com/AhmedMerza/laravel-logscope) >= 1.5.1

---

## 📦 Installation

```bash
composer require ahmedmerza/logscope-guard
php artisan guard:install
```

The install command publishes the config and runs the migration. Add these to your `.env`:

```env
GUARD_ENABLED=true
GUARD_NEVER_BLOCK_IPS=127.0.0.1,::1,your.own.ip
```

> **Important:** Add your own IP to `GUARD_NEVER_BLOCK_IPS` before enabling. You cannot be blocked by an IP on this list — it is checked before any block operation and before Redis.

---

## ⚙️ Configuration

```env
# Master switch
GUARD_ENABLED=true

# IPs that can never be blocked (comma-separated) — prevents self-lockout
GUARD_NEVER_BLOCK_IPS=127.0.0.1,::1

# Redis connection to use for the blacklist hash
GUARD_REDIS_CONNECTION=default

# Cross-environment sync
GUARD_MASTER_URL=https://your-master-app.com
GUARD_SYNC_SECRET=a-long-random-secret

# Auto-block engine (disabled by default)
GUARD_AUTO_BLOCK_ENABLED=false
GUARD_AUTO_BLOCK_DURATION=60

# Webhook notification on every block (optional — useful for n8n, Slack, WhatsApp)
GUARD_WEBHOOK_URL=
GUARD_NOTIFICATION_QUEUE=default
```

### Block Response

By default, blocked IPs receive a plain `403 Access denied.` response. To redirect instead:

```php
// config/logscope-guard.php
'block_response' => [
    'status'   => 403,
    'message'  => 'Access denied.',
    'redirect' => null, // Set a URL to redirect instead
],
```

---

## 🌐 Cross-Environment Sync

Guard supports a **master/satellite** topology. One environment (production) is the master. Others (staging, alpha) pull from it.

### Setup

**On every environment** (master + satellites), add to `.env`:

```env
GUARD_MASTER_URL=https://your-production-app.com
GUARD_SYNC_SECRET=same-secret-on-all-environments
```

**On the master app**, expose two routes that satellites call:

```php
// routes/web.php (or api.php) — protect with HMAC middleware
Route::get('/guard/api/blacklist', fn () => response()->json([
    'data' => \LogScopeGuard\Models\BlacklistedIp::active()->get(),
]));

Route::post('/guard/api/block', function (Request $request) {
    app(\LogScopeGuard\Services\BlacklistService::class)->block(
        $request->input('ip'),
        $request->only(['reason', 'source_env', 'expires_at', 'blocked_by'])
    );
    return response()->json(['ok' => true]);
});
```

**On satellites**, schedule the sync command:

```php
// Laravel 11+ (routes/console.php)
Schedule::command('guard:sync')->everyFiveMinutes();

// Laravel 10 (app/Console/Kernel.php)
$schedule->command('guard:sync')->everyFiveMinutes();
```

### How Push + Pull Work Together

| Direction | Trigger | Speed |
|-----------|---------|-------|
| **Push** (satellite → master) | Every `BlacklistService::block()` call | Immediate (queued job) |
| **Pull** (master → satellites) | `guard:sync` schedule | Every 5 min (configurable) |

Block on staging → staging protected instantly → master updated asynchronously → production/alpha pull it within 5 minutes.

---

## 🤖 Auto-Block Rules

Automatically block IPs based on log patterns. Disabled by default.

```env
GUARD_AUTO_BLOCK_ENABLED=true
GUARD_AUTO_BLOCK_DURATION=60  # minutes
```

Define rules in `config/logscope-guard.php`:

```php
'auto_block' => [
    'enabled'                => env('GUARD_AUTO_BLOCK_ENABLED', false),
    'block_duration_minutes' => 60,
    'rules' => [
        // Block IPs that generate 50+ errors in 5 minutes
        [
            'level'            => 'error',
            'message_contains' => null,
            'count'            => 50,
            'window_minutes'   => 5,
        ],
        // Block IPs that hit 404 more than 100 times in 10 minutes
        [
            'level'            => 'warning',
            'message_contains' => '404',
            'count'            => 100,
            'window_minutes'   => 10,
        ],
    ],
],
```

Rules run every minute via the scheduler. Add the scheduler to your server if not already running:

```bash
* * * * * cd /your-app && php artisan schedule:run >> /dev/null 2>&1
```

> **Note:** IPs in `GUARD_NEVER_BLOCK_IPS` are never auto-blocked, even if they match a rule.

---

## 🔧 Artisan Commands

```bash
# First-time setup (publish config + run migration)
php artisan guard:install

# Pull blacklist from master and rebuild local Redis cache
php artisan guard:sync
```

---

## 🔒 Security Notes

**Trusted proxies:** Guard uses `$request->ip()` — the same method LogScope uses. If your app is behind a load balancer or proxy, configure Laravel's trusted proxies correctly so the real client IP is resolved, not the proxy IP.

**HMAC signatures:** All sync requests are signed with `GUARD_SYNC_SECRET` using `hash_hmac('sha256', ...)`. Use a long, random secret and keep it identical across environments.

**Redis TTL:** The blacklist Redis hash has a 24-hour TTL as a safety net. If Redis is flushed, the cache rebuilds from DB automatically on the next request boot.

---

## 🤝 Contributing

Contributions are welcome. Please open an issue or submit a pull request on [GitHub](https://github.com/AhmedMerza/laravel-logscope-guard).

---

## 📄 License

MIT License. See [LICENSE](LICENSE.md) for details.
