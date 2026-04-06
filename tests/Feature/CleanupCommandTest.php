<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;
use LogScopeGuard\Enums\BlockSource;
use LogScopeGuard\Models\BlacklistedIp;

beforeEach(function () {
    Redis::shouldReceive('connection')->andReturnSelf()->byDefault();
    Redis::shouldReceive('del')->andReturn(1)->byDefault();
    Redis::shouldReceive('hmset')->andReturn(true)->byDefault();
    Redis::shouldReceive('expire')->andReturn(true)->byDefault();
    Redis::shouldReceive('exists')->andReturn(0)->byDefault();
    Redis::shouldReceive('hget')->andReturn(null)->byDefault();
});

it('deletes expired temporary blocks', function () {
    BlacklistedIp::create([
        'ip'         => '1.2.3.4',
        'source'     => BlockSource::Auto,
        'source_env' => 'testing',
        'expires_at' => now()->subHour(),
    ]);

    $this->artisan('guard:cleanup')
        ->assertSuccessful()
        ->expectsOutputToContain('Removed 1 expired block');

    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '1.2.3.4']);
});

it('does not delete blocks that have not expired yet', function () {
    BlacklistedIp::create([
        'ip'         => '2.2.2.2',
        'source'     => BlockSource::Auto,
        'source_env' => 'testing',
        'expires_at' => now()->addHour(),
    ]);

    $this->artisan('guard:cleanup')->assertSuccessful();

    $this->assertDatabaseHas('blacklisted_ips', ['ip' => '2.2.2.2']);
});

it('never deletes permanent blocks (expires_at is null)', function () {
    BlacklistedIp::create([
        'ip'         => '3.3.3.3',
        'source'     => BlockSource::Manual,
        'source_env' => 'testing',
        'expires_at' => null,
    ]);

    $this->artisan('guard:cleanup')
        ->assertSuccessful()
        ->expectsOutputToContain('Removed 0 expired block');

    $this->assertDatabaseHas('blacklisted_ips', ['ip' => '3.3.3.3']);
});

it('only rebuilds the cache when records were deleted', function () {
    // No expired blocks — cache should not be rebuilt
    Redis::shouldNotReceive('del');

    BlacklistedIp::create([
        'ip'         => '4.4.4.4',
        'source'     => BlockSource::Manual,
        'source_env' => 'testing',
        'expires_at' => null,
    ]);

    $this->artisan('guard:cleanup')->assertSuccessful();
});

it('reports zero when nothing to clean up', function () {
    $this->artisan('guard:cleanup')
        ->assertSuccessful()
        ->expectsOutputToContain('Removed 0 expired block');
});

it('can still be run manually even when GUARD_CLEANUP_ENABLED is false', function () {
    config()->set('logscope-guard.cleanup.enabled', false);

    BlacklistedIp::create([
        'ip'         => '5.5.5.5',
        'source'     => BlockSource::Auto,
        'source_env' => 'testing',
        'expires_at' => now()->subHour(),
    ]);

    // The config flag only stops the scheduler — the command itself still works
    $this->artisan('guard:cleanup')
        ->assertSuccessful()
        ->expectsOutputToContain('Removed 1 expired block');

    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '5.5.5.5']);
});
