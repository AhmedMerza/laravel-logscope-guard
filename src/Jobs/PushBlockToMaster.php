<?php

declare(strict_types=1);

namespace LogScopeGuard\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogScopeGuard\Models\BlacklistedIp;

class PushBlockToMaster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly BlacklistedIp $record) {}

    public function handle(): void
    {
        $masterUrl = config('logscope-guard.sync.master_url');
        $secret = config('logscope-guard.sync.secret');

        if (! $masterUrl) {
            return;
        }

        $payload = [
            'ip'         => $this->record->ip,
            'reason'     => $this->record->reason,
            'source_env' => app()->environment(),
            'expires_at' => $this->record->expires_at?->toIso8601String(),
            'blocked_by' => $this->record->blocked_by,
        ];

        $timestamp = now()->timestamp;
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp.'POST/guard/api/block'.$body, $secret);

        $response = Http::withHeaders([
            'X-Guard-Timestamp' => $timestamp,
            'X-Guard-Signature' => $signature,
            'Content-Type'      => 'application/json',
        ])->post($masterUrl.'/guard/api/block', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException("Master returned HTTP {$response->status()} for IP {$this->record->ip}");
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('stack')->warning('LogScope Guard: PushBlockToMaster failed', [
            'ip'    => $this->record->ip,
            'error' => $e->getMessage(),
        ]);
    }
}
