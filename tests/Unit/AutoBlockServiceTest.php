<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use LogScopeGuard\Enums\BlockSource;
use LogScopeGuard\Models\BlacklistedIp;
use LogScopeGuard\Services\AutoBlockService;
use LogScopeGuard\Services\BlacklistCache;
use LogScopeGuard\Services\BlacklistService;

beforeEach(function () {
    Redis::shouldReceive('connection')->andReturnSelf()->byDefault();
    Redis::shouldReceive('del')->andReturn(1)->byDefault();
    Redis::shouldReceive('hmset')->andReturn(true)->byDefault();
    Redis::shouldReceive('expire')->andReturn(true)->byDefault();
    Redis::shouldReceive('exists')->andReturn(0)->byDefault();
    Redis::shouldReceive('hget')->andReturn(null)->byDefault();

    Event::fake();
    Queue::fake();

    config()->set('logscope-guard.auto_block.enabled', true);
    config()->set('logscope-guard.auto_block.block_duration_minutes', 60);

    $this->cache = new BlacklistCache;
    $this->blacklist = new BlacklistService($this->cache);
    $this->service = new AutoBlockService($this->blacklist);
});

it('does nothing when auto-block is disabled', function () {
    config()->set('logscope-guard.auto_block.enabled', false);

    // Create a log entry that would normally trigger a block
    \Illuminate\Support\Facades\DB::table('log_entries')->insert([
        'id'          => \Illuminate\Support\Str::ulid(),
        'level'       => 'error',
        'message'     => 'Something went wrong',
        'ip_address'  => '1.2.3.4',
        'occurred_at' => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    config()->set('logscope-guard.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 1,
        'window_minutes'   => 5,
    ]]);

    $this->service->run();

    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '1.2.3.4']);
});

it('does nothing when no rules are configured', function () {
    config()->set('logscope-guard.auto_block.rules', []);

    $this->service->run();

    $this->assertDatabaseCount('blacklisted_ips', 0);
});

it('blocks an IP that exceeds the rule threshold', function () {
    config()->set('logscope-guard.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 3,
        'window_minutes'   => 5,
    ]]);

    foreach (range(1, 3) as $i) {
        \Illuminate\Support\Facades\DB::table('log_entries')->insert([
            'id'          => \Illuminate\Support\Str::ulid(),
            'level'       => 'error',
            'message'     => 'Error occurred',
            'ip_address'  => '5.5.5.5',
            'occurred_at' => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    $this->service->run();

    $this->assertDatabaseHas('blacklisted_ips', [
        'ip'     => '5.5.5.5',
        'source' => 'auto',
    ]);
});

it('does not block an IP below the threshold', function () {
    config()->set('logscope-guard.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 10,
        'window_minutes'   => 5,
    ]]);

    foreach (range(1, 5) as $i) {
        \Illuminate\Support\Facades\DB::table('log_entries')->insert([
            'id'          => \Illuminate\Support\Str::ulid(),
            'level'       => 'error',
            'message'     => 'Error occurred',
            'ip_address'  => '6.6.6.6',
            'occurred_at' => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    $this->service->run();

    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '6.6.6.6']);
});

it('matches message_contains filter correctly', function () {
    config()->set('logscope-guard.auto_block.rules', [[
        'level'            => null,
        'message_contains' => '404',
        'count'            => 2,
        'window_minutes'   => 5,
    ]]);

    // This IP sends 404 messages — should be blocked
    foreach (range(1, 2) as $i) {
        \Illuminate\Support\Facades\DB::table('log_entries')->insert([
            'id'          => \Illuminate\Support\Str::ulid(),
            'level'       => 'warning',
            'message'     => 'Route not found 404',
            'ip_address'  => '7.7.7.7',
            'occurred_at' => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    // This IP sends different messages — should not be blocked
    foreach (range(1, 2) as $i) {
        \Illuminate\Support\Facades\DB::table('log_entries')->insert([
            'id'          => \Illuminate\Support\Str::ulid(),
            'level'       => 'warning',
            'message'     => 'Something else happened',
            'ip_address'  => '8.8.8.8',
            'occurred_at' => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    $this->service->run();

    $this->assertDatabaseHas('blacklisted_ips', ['ip' => '7.7.7.7']);
    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '8.8.8.8']);
});

it('skips IPs in the never-block whitelist', function () {
    config()->set('logscope-guard.never_block', ['9.9.9.9']);
    config()->set('logscope-guard.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 1,
        'window_minutes'   => 5,
    ]]);

    \Illuminate\Support\Facades\DB::table('log_entries')->insert([
        'id'          => \Illuminate\Support\Str::ulid(),
        'level'       => 'error',
        'message'     => 'Error',
        'ip_address'  => '9.9.9.9',
        'occurred_at' => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $this->service->run();

    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '9.9.9.9']);
});

it('skips IPs that are already blocked', function () {
    config()->set('logscope-guard.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 1,
        'window_minutes'   => 5,
    ]]);

    BlacklistedIp::create([
        'ip'         => '3.3.3.3',
        'source'     => BlockSource::Manual,
        'source_env' => 'testing',
    ]);

    Redis::shouldReceive('hget')
        ->with('logscope_guard:blacklist', '3.3.3.3')
        ->andReturn('');

    \Illuminate\Support\Facades\DB::table('log_entries')->insert([
        'id'          => \Illuminate\Support\Str::ulid(),
        'level'       => 'error',
        'message'     => 'Error',
        'ip_address'  => '3.3.3.3',
        'occurred_at' => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $this->service->run();

    // Should still be exactly 1 record — no duplicate
    $this->assertDatabaseCount('blacklisted_ips', 1);
});

it('ignores log entries outside the time window', function () {
    config()->set('logscope-guard.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 1,
        'window_minutes'   => 5,
    ]]);

    \Illuminate\Support\Facades\DB::table('log_entries')->insert([
        'id'          => \Illuminate\Support\Str::ulid(),
        'level'       => 'error',
        'message'     => 'Old error',
        'ip_address'  => '4.4.4.4',
        'occurred_at' => now()->subMinutes(10),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $this->service->run();

    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '4.4.4.4']);
});
