<?php

declare(strict_types=1);

namespace LogScopeGuard\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use LogScopeGuard\Models\BlacklistedIp;

class BlacklistCache
{
    private string $key;

    private int $ttlSeconds;

    private string $connection;

    public function __construct()
    {
        $config = config('logscope-guard.cache');
        $this->key = $config['key'];
        $this->ttlSeconds = (int) $config['ttl_hours'] * 3600;
        $this->connection = $config['connection'];
    }

    /**
     * Check whether an already-normalized IP is currently blocked.
     * Pure Redis read — no DB hit.
     */
    public function isBlocked(string $ip): bool
    {
        $value = Redis::connection($this->connection)->hget($this->key, $ip);

        if ($value === null || $value === false) {
            return false;
        }

        // Empty string = permanent block (no expiry)
        if ($value === '') {
            return true;
        }

        // ISO-8601 string = temporary block, check if still active
        return now()->lt(Carbon::parse($value));
    }

    /**
     * Rebuild the entire Redis hash from the DB.
     * Called after every block/unblock and after guard:sync.
     */
    public function rebuild(): void
    {
        $redis = Redis::connection($this->connection);
        $redis->del($this->key);

        try {
            $blocks = BlacklistedIp::active()->get(['ip', 'expires_at']);
        } catch (\Throwable $e) {
            // Table doesn't exist yet (migration not run) or DB unavailable —
            // log and bail, keeping whatever Redis data was already there.
            \Illuminate\Support\Facades\Log::warning('LogScope Guard: cache rebuild failed, keeping existing Redis data', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($blocks->isEmpty()) {
            return;
        }

        $hash = [];
        foreach ($blocks as $block) {
            $hash[$block->ip] = $block->expires_at
                ? $block->expires_at->toIso8601String()
                : '';
        }

        $redis->hmset($this->key, $hash);
        $redis->expire($this->key, $this->ttlSeconds);
    }

    /**
     * Warm the cache from DB on application boot if the Redis key is missing.
     * No-op if the key already exists (TTL still valid).
     */
    public function warmOnBoot(): void
    {
        $exists = Redis::connection($this->connection)->exists($this->key);

        if ($exists) {
            return;
        }

        $this->rebuild();
    }
}
