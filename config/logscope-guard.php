<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Guard
    |--------------------------------------------------------------------------
    |
    | Master switch. When false, the blocking middleware passes all requests
    | through and no blocks are enforced.
    |
    */

    'enabled' => env('GUARD_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Redis Cache
    |--------------------------------------------------------------------------
    |
    | Guard uses a Redis Hash to check blocks on every request with zero DB
    | hits. The hash is rebuilt on every block/unblock and refreshed on sync.
    | TTL is a safety net — the hash is always explicitly rebuilt on changes.
    |
    */

    'cache' => [
        'key'        => 'logscope_guard:blacklist',
        'ttl_hours'  => 24,
        'connection' => env('GUARD_REDIS_CONNECTION', 'default'),
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
    | Example .env: GUARD_NEVER_BLOCK_IPS=127.0.0.1,::1,10.0.0.1
    |
    */

    'never_block' => array_filter(
        array_map('trim', explode(',', env('GUARD_NEVER_BLOCK_IPS', '127.0.0.1,::1')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Auto-Block Engine
    |--------------------------------------------------------------------------
    |
    | Automatically block IPs that match log-based rules. Disabled by default.
    | Rules are evaluated every minute via the scheduler.
    |
    | Rule shape:
    |   level            - log level to match (e.g. 'error', 'warning'). Null = any.
    |   message_contains - substring match on log message. Null = any.
    |   count            - number of matching logs within the window to trigger a block.
    |   window_minutes   - look-back window for counting logs.
    |
    */

    'auto_block' => [
        'enabled'                => env('GUARD_AUTO_BLOCK_ENABLED', false),
        'block_duration_minutes' => env('GUARD_AUTO_BLOCK_DURATION', 60),
        'rules'                  => [
            // Example:
            // [
            //     'level'            => 'error',
            //     'message_contains' => null,
            //     'count'            => 50,
            //     'window_minutes'   => 5,
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cross-Environment Sync
    |--------------------------------------------------------------------------
    |
    | Configure the master environment URL and shared HMAC secret.
    | The 'guard:sync' command pulls the full blacklist from master and
    | rebuilds the local Redis cache.
    |
    | Run on a schedule on satellite environments (e.g. every 5 minutes):
    |   $schedule->command('guard:sync')->everyFiveMinutes();
    |
    */

    'sync' => [
        'master_url' => env('GUARD_MASTER_URL'),
        'secret'     => env('GUARD_SYNC_SECRET'),
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
        'webhook_url' => env('GUARD_WEBHOOK_URL'),
        'queue'       => env('GUARD_NOTIFICATION_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | All Guard-related log entries (sync failures, auto-block skips, webhook
    | errors, push job failures) are written to this channel. Defaults to your
    | app's 'stack' channel. Set to a dedicated channel to isolate Guard logs.
    |
    | Example .env: GUARD_LOG_CHANNEL=logscope_guard
    |
    | Then add a channel to config/logging.php:
    |   'logscope_guard' => [
    |       'driver' => 'daily',
    |       'path'   => storage_path('logs/logscope-guard.log'),
    |       'level'  => 'debug',
    |       'days'   => 14,
    |   ],
    |
    */

    'log_channel' => env('GUARD_LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    |
    | When enabled, Guard automatically runs 'guard:cleanup' daily to remove
    | expired temporary blocks from the database. Only rows with a past
    | expires_at are deleted — permanent blocks (expires_at = null) are
    | never touched.
    |
    | Set to false if you prefer to schedule or run cleanup manually.
    |
    */

    'cleanup' => [
        'enabled' => env('GUARD_CLEANUP_ENABLED', true),
    ],

];
