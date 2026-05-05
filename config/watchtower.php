<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Watchtower
    |--------------------------------------------------------------------------
    |
    | Master switch. When false, the blocking middleware passes all requests
    | through and no blocks are enforced.
    |
    */

    'enabled' => env('WATCHTOWER_ENABLED', env('GUARD_ENABLED', true)),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Where Watchtower mounts its management API. When LogScope is also
    | installed, routes mount under LogScope's prefix as `<prefix>/watchtower`
    | and inherit LogScope's Authorize middleware automatically. When
    | running standalone, routes mount at the prefix below and use the
    | middleware list here.
    |
    | ⚠️ STANDALONE AUTH WARNING: until v1.1 ships proper standalone auth,
    | the management routes have NO built-in authorization when LogScope
    | isn't installed. Either restrict access via the `middleware` array
    | (e.g. ['web', 'auth'] + a Gate check), or set `enabled` => false on
    | the routes block to disable them entirely.
    |
    */

    'routes' => [
        'enabled'    => env('WATCHTOWER_ROUTES_ENABLED', true),
        'prefix'     => env('WATCHTOWER_ROUTE_PREFIX', 'watchtower'),
        'domain'     => env('WATCHTOWER_ROUTE_DOMAIN'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Cache
    |--------------------------------------------------------------------------
    |
    | Watchtower uses a Redis Hash to check blocks on every request with zero DB
    | hits. The hash is rebuilt on every block/unblock and refreshed on sync.
    | TTL is a safety net — the hash is always explicitly rebuilt on changes.
    |
    */

    'cache' => [
        'key'        => 'watchtower:blacklist',
        'ttl_hours'  => 24,
        'connection' => env('WATCHTOWER_REDIS_CONNECTION', env('GUARD_REDIS_CONNECTION', 'default')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Block Response
    |--------------------------------------------------------------------------
    |
    | What to return when a blocked IP hits your app. Set 'redirect' to a URL
    | to redirect instead of returning a plain response.
    |
    */

    'block_response' => [
        'status'   => 403,
        'message'  => 'Access denied.',
        'redirect' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Never-Block Whitelist
    |--------------------------------------------------------------------------
    |
    | IPs that can never be blocked by any means — UI, auto-block, or sync.
    | Prevents self-lockout. Populate via env (comma-separated) or directly.
    |
    | Example .env: WATCHTOWER_NEVER_BLOCK_IPS=127.0.0.1,::1,10.0.0.1
    |
    */

    'never_block' => array_filter(
        array_map('trim', explode(',', env('WATCHTOWER_NEVER_BLOCK_IPS', env('GUARD_NEVER_BLOCK_IPS', '127.0.0.1,::1'))))
    ),

    /*
    |--------------------------------------------------------------------------
    | Auto-Block Engine
    |--------------------------------------------------------------------------
    |
    | Automatically block IPs that match log-based rules. Disabled by default.
    | Rules are evaluated every minute via the scheduler.
    |
    | Mode (global default — overrideable per rule):
    |   'block'    - actually block matching IPs (production behaviour).
    |   'warn'     - match the rule and emit a structured `would_have_blocked`
    |                log entry on the configured `log_channel`, but do NOT
    |                block. Use this when first turning auto-block on in a
    |                new environment: tail your logs (or query LogScope) for
    |                `would_have_blocked: true` to see what a rule WOULD
    |                catch before letting it lock anyone out. Once you trust
    |                the rule, flip to 'block'.
    |   'disabled' - skip the rule entirely. Useful as a per-rule kill switch
    |                without removing the rule definition.
    |
    | Rule shape:
    |   level            - log level to match (e.g. 'error', 'warning'). Null = any.
    |   message_contains - substring match on log message. Null = any.
    |   count            - number of matching logs within the window to trigger a block.
    |   window_minutes   - look-back window for counting logs.
    |   mode             - (optional) override the global mode for this rule only.
    |
    */

    'auto_block' => [
        'enabled'                => env('WATCHTOWER_AUTO_BLOCK_ENABLED', env('GUARD_AUTO_BLOCK_ENABLED', false)),
        'mode'                   => env('WATCHTOWER_AUTO_BLOCK_MODE', 'block'),
        'block_duration_minutes' => env('WATCHTOWER_AUTO_BLOCK_DURATION', env('GUARD_AUTO_BLOCK_DURATION', 60)),
        'rules'                  => [
            // Example — production rule:
            // [
            //     'level'            => 'error',
            //     'message_contains' => null,
            //     'count'            => 50,
            //     'window_minutes'   => 5,
            // ],
            //
            // Example — same rule running in warn mode while you tune it:
            // [
            //     'level'            => 'error',
            //     'message_contains' => null,
            //     'count'            => 50,
            //     'window_minutes'   => 5,
            //     'mode'             => 'warn',
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cross-Environment Sync
    |--------------------------------------------------------------------------
    |
    | Configure the master environment URL and shared HMAC secret.
    | The 'watchtower:sync' command pulls the full blacklist from master and
    | rebuilds the local Redis cache.
    |
    | Run on a schedule on satellite environments (e.g. every 5 minutes):
    |   $schedule->command('watchtower:sync')->everyFiveMinutes();
    |
    */

    'sync' => [
        'master_url' => env('WATCHTOWER_MASTER_URL', env('GUARD_MASTER_URL')),
        'secret'     => env('WATCHTOWER_SYNC_SECRET', env('GUARD_SYNC_SECRET')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Fired (always queued) when any IP is blocked. Post to a webhook —
    | useful for n8n, Slack, or WhatsApp automations.
    |
    */

    'notifications' => [
        'webhook_url' => env('WATCHTOWER_WEBHOOK_URL', env('GUARD_WEBHOOK_URL')),
        'queue'       => env('WATCHTOWER_NOTIFICATION_QUEUE', env('GUARD_NOTIFICATION_QUEUE', 'default')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | All Watchtower-related log entries (sync failures, auto-block skips,
    | webhook errors, push job failures) are written to this channel. Defaults
    | to your app's 'stack' channel. Set to a dedicated channel to isolate
    | Watchtower logs.
    |
    | Example .env: WATCHTOWER_LOG_CHANNEL=watchtower
    |
    | Then add a channel to config/logging.php:
    |   'watchtower' => [
    |       'driver' => 'daily',
    |       'path'   => storage_path('logs/watchtower.log'),
    |       'level'  => 'debug',
    |       'days'   => 14,
    |   ],
    |
    */

    'log_channel' => env('WATCHTOWER_LOG_CHANNEL', env('GUARD_LOG_CHANNEL', 'stack')),

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    |
    | When enabled, Watchtower automatically runs 'watchtower:cleanup' daily
    | to remove expired temporary blocks from the database. Only rows with a past
    | expires_at are deleted — permanent blocks (expires_at = null) are
    | never touched.
    |
    | Set to false if you prefer to schedule or run cleanup manually.
    |
    */

    'cleanup' => [
        'enabled' => env('WATCHTOWER_CLEANUP_ENABLED', env('GUARD_CLEANUP_ENABLED', true)),
    ],

];
