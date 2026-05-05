<?php

declare(strict_types=1);

namespace LogScopeGuard\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogScopeGuard\Enums\BlockSource;

class AutoBlockService
{
    public function __construct(private readonly BlacklistService $blacklist) {}

    public function run(): void
    {
        if (! config('logscope-guard.auto_block.enabled', false)) {
            return;
        }

        $rules = config('logscope-guard.auto_block.rules', []);
        $durationMinutes = (int) config('logscope-guard.auto_block.block_duration_minutes', 60);

        foreach ($rules as $rule) {
            $this->applyRule($rule, $durationMinutes);
        }
    }

    private function applyRule(array $rule, int $durationMinutes): void
    {
        $windowMinutes   = (int) ($rule['window_minutes'] ?? 5);
        $threshold       = (int) ($rule['count'] ?? 10);
        $level           = $rule['level'] ?? null;
        $messageContains = $rule['message_contains'] ?? null;

        $table   = config('logscope-guard.auto_block.table', 'log_entries');
        $cols    = config('logscope-guard.auto_block.columns', []);
        $colIp   = $cols['ip'] ?? 'ip_address';
        $colTime = $cols['occurred_at'] ?? 'occurred_at';
        $colLvl  = $cols['level'] ?? 'level';
        $colMsg  = $cols['message'] ?? 'message';

        $query = DB::table($table)
            ->select($colIp, DB::raw('count(*) as hit_count'))
            ->whereNotNull($colIp)
            ->where($colTime, '>=', now()->subMinutes($windowMinutes))
            ->groupBy($colIp)
            ->having('hit_count', '>=', $threshold);

        if ($level) {
            $query->where($colLvl, $level);
        }

        if ($messageContains) {
            $query->where($colMsg, 'like', '%'.$messageContains.'%');
        }

        $offenders = $query->pluck($colIp);

        $expiresAt = now()->addMinutes($durationMinutes);
        $reason = sprintf(
            'Auto-blocked: %s%s exceeded %d hits in %d min',
            $level ? "level={$level} " : '',
            $messageContains ? "contains='{$messageContains}' " : '',
            $threshold,
            $windowMinutes,
        );

        foreach ($offenders as $ip) {
            if ($this->blacklist->isBlocked($ip)) {
                continue;
            }

            try {
                $this->blacklist->block($ip, [
                    'reason'     => $reason,
                    'source'     => BlockSource::Auto,
                    'expires_at' => $expiresAt,
                ]);
            } catch (\RuntimeException $e) {
                // IP is in the never-block whitelist — skip silently
                Log::channel(config('logscope-guard.log_channel', 'stack'))
                    ->debug('LogScope Guard: auto-block skipped for whitelisted IP', ['ip' => $ip]);
            }
        }
    }
}
