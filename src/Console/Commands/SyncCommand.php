<?php

declare(strict_types=1);

namespace LogScopeGuard\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogScopeGuard\Enums\BlockSource;
use LogScopeGuard\Models\BlacklistedIp;
use LogScopeGuard\Services\BlacklistCache;

class SyncCommand extends Command
{
    protected $signature = 'guard:sync';

    protected $description = 'Pull the blacklist from the master environment and rebuild the local Redis cache';

    public function __construct(private readonly BlacklistCache $cache)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $masterUrl = config('logscope-guard.sync.master_url');
        $secret = config('logscope-guard.sync.secret');

        if (! $masterUrl) {
            $this->error('GUARD_MASTER_URL is not configured. Set it in your .env file.');

            return self::FAILURE;
        }

        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'GET/guard/api/blacklist', $secret);

        try {
            $response = Http::withHeaders([
                'X-Guard-Timestamp' => $timestamp,
                'X-Guard-Signature' => $signature,
                'Accept'            => 'application/json',
            ])->get($masterUrl.'/guard/api/blacklist');

            if (! $response->successful()) {
                $this->error("Sync failed — master returned HTTP {$response->status()}.");
                Log::channel(config('logscope-guard.log_channel', 'stack'))->error('LogScope Guard: sync failed', ['status' => $response->status()]);

                return self::FAILURE;
            }

            $blocks = $response->json('data', []);
            $synced = 0;
            $skipped = 0;

            foreach ($blocks as $block) {
                // Never downgrade a manual or auto block with a sync record —
                // only insert if the IP isn't already locally blocked.
                $existing = BlacklistedIp::where('ip', $block['ip'])->first();

                if ($existing && $existing->source !== BlockSource::Sync) {
                    $skipped++;
                    continue;
                }

                BlacklistedIp::updateOrCreate(
                    ['ip' => $block['ip']],
                    [
                        'reason'       => $block['reason'] ?? null,
                        'source_env'   => $block['source_env'] ?? 'master',
                        'source'       => BlockSource::Sync,
                        'expires_at'   => $block['expires_at'] ?? null,
                        'blocked_by'   => $block['blocked_by'] ?? null,
                        'log_entry_id' => $block['log_entry_id'] ?? null,
                    ]
                );
                $synced++;
            }

            $this->cache->rebuild();

            $this->info("Synced {$synced} IPs from master ({$skipped} skipped — local manual/auto blocks preserved). Redis cache rebuilt.");

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Sync error: '.$e->getMessage());
            Log::channel(config('logscope-guard.log_channel', 'stack'))->error('LogScope Guard: sync exception', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
