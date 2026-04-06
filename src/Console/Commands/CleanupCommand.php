<?php

declare(strict_types=1);

namespace LogScopeGuard\Console\Commands;

use Illuminate\Console\Command;
use LogScopeGuard\Models\BlacklistedIp;
use LogScopeGuard\Services\BlacklistCache;

class CleanupCommand extends Command
{
    protected $signature = 'guard:cleanup';

    protected $description = 'Delete expired temporary blocks from the database and rebuild the Redis cache';

    public function __construct(private readonly BlacklistCache $cache)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $deleted = BlacklistedIp::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        if ($deleted > 0) {
            $this->cache->rebuild();
        }

        $this->info("Removed {$deleted} expired block(s). Redis cache rebuilt.");

        return self::SUCCESS;
    }
}
