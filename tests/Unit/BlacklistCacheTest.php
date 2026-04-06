<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;
use LogScopeGuard\Enums\BlockSource;
use LogScopeGuard\Models\BlacklistedIp;
use LogScopeGuard\Services\BlacklistCache;

beforeEach(function () {
    Redis::shouldReceive('connection')->andReturnSelf()->byDefault();
    config()->set('logscope-guard.cache', [
        'key'        => 'logscope_guard:blacklist',
        'ttl_hours'  => 24,
        'connection' => 'default',
    ]);
    $this->cache = new BlacklistCache;
});

it('returns false for an IP not in the hash', function () {
    Redis::shouldReceive('hget')->with('logscope_guard:blacklist', '9.9.9.9')->andReturn(null);

    expect($this->cache->isBlocked('9.9.9.9'))->toBeFalse();
});

it('returns true for a permanently blocked IP (empty string value)', function () {
    Redis::shouldReceive('hget')->with('logscope_guard:blacklist', '1.2.3.4')->andReturn('');

    expect($this->cache->isBlocked('1.2.3.4'))->toBeTrue();
});

it('returns true for a temporarily blocked IP that has not expired', function () {
    $future = now()->addHour()->toIso8601String();
    Redis::shouldReceive('hget')->with('logscope_guard:blacklist', '1.2.3.4')->andReturn($future);

    expect($this->cache->isBlocked('1.2.3.4'))->toBeTrue();
});

it('returns false for a temporarily blocked IP that has expired', function () {
    $past = now()->subHour()->toIso8601String();
    Redis::shouldReceive('hget')->with('logscope_guard:blacklist', '1.2.3.4')->andReturn($past);

    expect($this->cache->isBlocked('1.2.3.4'))->toBeFalse();
});

it('does not rebuild cache on warmOnBoot when the key already exists', function () {
    Redis::shouldReceive('exists')->with('logscope_guard:blacklist')->andReturn(1);
    Redis::shouldNotReceive('del');

    $this->cache->warmOnBoot();
});

it('rebuilds cache on warmOnBoot when the key is missing', function () {
    BlacklistedIp::create([
        'ip'         => '1.2.3.4',
        'source'     => BlockSource::Manual,
        'source_env' => 'testing',
        'expires_at' => null,
    ]);

    Redis::shouldReceive('exists')->with('logscope_guard:blacklist')->andReturn(0);
    Redis::shouldReceive('del')->with('logscope_guard:blacklist')->once();
    Redis::shouldReceive('hmset')->once();
    Redis::shouldReceive('expire')->once();

    $this->cache->warmOnBoot();
});

it('deletes and rebuilds on rebuild()', function () {
    BlacklistedIp::create([
        'ip'         => '2.2.2.2',
        'source'     => BlockSource::Manual,
        'source_env' => 'testing',
        'expires_at' => now()->addDay(),
    ]);

    Redis::shouldReceive('del')->with('logscope_guard:blacklist')->once();
    Redis::shouldReceive('hmset')->once();
    Redis::shouldReceive('expire')->with('logscope_guard:blacklist', 86400)->once();

    $this->cache->rebuild();
});
