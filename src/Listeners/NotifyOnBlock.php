<?php

declare(strict_types=1);

namespace LogScopeGuard\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogScopeGuard\Events\IpBlocked;

class NotifyOnBlock implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(IpBlocked $event): void
    {
        $webhookUrl = config('logscope-guard.notifications.webhook_url');

        if (! $webhookUrl) {
            return;
        }

        try {
            Http::post($webhookUrl, [
                'ip'         => $event->record->ip,
                'reason'     => $event->record->reason,
                'source'     => $event->record->source->value,
                'source_env' => $event->record->source_env,
                'blocked_by' => $event->record->blocked_by,
                'expires_at' => $event->record->expires_at?->toIso8601String(),
                'blocked_at' => $event->record->created_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('stack')->warning('LogScope Guard: webhook notification failed', [
                'ip'    => $event->record->ip,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
