<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use LogScopeGuard\Models\BlacklistedIp;

beforeEach(function () {
    Redis::shouldReceive('connection')->andReturnSelf()->byDefault();
    Redis::shouldReceive('del')->andReturn(1)->byDefault();
    Redis::shouldReceive('hmset')->andReturn(true)->byDefault();
    Redis::shouldReceive('expire')->andReturn(true)->byDefault();
    Redis::shouldReceive('exists')->andReturn(0)->byDefault();

    config()->set('logscope-guard.sync.master_url', 'https://master.example.com');
    config()->set('logscope-guard.sync.secret', 'test-secret');
});

it('syncs IPs from master and upserts them locally', function () {
    Http::fake([
        'master.example.com/guard/api/blacklist' => Http::response([
            'data' => [
                ['ip' => '1.2.3.4', 'reason' => 'synced', 'source_env' => 'production', 'expires_at' => null, 'blocked_by' => null, 'log_entry_id' => null],
                ['ip' => '5.6.7.8', 'reason' => null, 'source_env' => 'production', 'expires_at' => null, 'blocked_by' => null, 'log_entry_id' => null],
            ],
        ], 200),
    ]);

    $this->artisan('guard:sync')
        ->assertSuccessful()
        ->expectsOutputToContain('Synced 2 IPs');

    $this->assertDatabaseHas('blacklisted_ips', ['ip' => '1.2.3.4', 'source' => 'sync']);
    $this->assertDatabaseHas('blacklisted_ips', ['ip' => '5.6.7.8', 'source' => 'sync']);
});

it('fails gracefully when master returns an error', function () {
    Http::fake([
        'master.example.com/guard/api/blacklist' => Http::response([], 500),
    ]);

    $this->artisan('guard:sync')
        ->assertFailed()
        ->expectsOutputToContain('HTTP 500');
});

it('fails gracefully when master URL is not configured', function () {
    config()->set('logscope-guard.sync.master_url', null);

    $this->artisan('guard:sync')
        ->assertFailed()
        ->expectsOutputToContain('GUARD_MASTER_URL');
});

it('does not duplicate records on repeated syncs', function () {
    Http::fake([
        'master.example.com/guard/api/blacklist' => Http::response([
            'data' => [
                ['ip' => '1.2.3.4', 'reason' => 'initial', 'source_env' => 'production', 'expires_at' => null, 'blocked_by' => null, 'log_entry_id' => null],
            ],
        ], 200),
    ]);

    $this->artisan('guard:sync');
    $this->artisan('guard:sync');

    $this->assertDatabaseCount('blacklisted_ips', 1);
});
