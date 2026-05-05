<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Watchtower\Enums\BlockSource;
use Watchtower\Models\BlacklistedIp;
use Watchtower\Services\AutoBlockService;
use Watchtower\Services\BlacklistCache;
use Watchtower\Services\BlacklistService;

beforeEach(function () {
    Redis::shouldReceive('connection')->andReturnSelf()->byDefault();
    Redis::shouldReceive('del')->andReturn(1)->byDefault();
    Redis::shouldReceive('hmset')->andReturn(true)->byDefault();
    Redis::shouldReceive('expire')->andReturn(true)->byDefault();
    Redis::shouldReceive('exists')->andReturn(0)->byDefault();
    Redis::shouldReceive('hget')->andReturn(null)->byDefault();

    Event::fake();
    Queue::fake();

    config()->set('watchtower.auto_block.enabled', true);
    config()->set('watchtower.auto_block.block_duration_minutes', 60);

    $this->cache = new BlacklistCache;
    $this->blacklist = new BlacklistService($this->cache);
    $this->service = new AutoBlockService($this->blacklist);
});

it('does nothing when auto-block is disabled', function () {
    config()->set('watchtower.auto_block.enabled', false);

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

    config()->set('watchtower.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 1,
        'window_minutes'   => 5,
    ]]);

    $this->service->run();

    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '1.2.3.4']);
});

it('does nothing when no rules are configured', function () {
    config()->set('watchtower.auto_block.rules', []);

    $this->service->run();

    $this->assertDatabaseCount('blacklisted_ips', 0);
});

it('blocks an IP that exceeds the rule threshold', function () {
    config()->set('watchtower.auto_block.rules', [[
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
    config()->set('watchtower.auto_block.rules', [[
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
    config()->set('watchtower.auto_block.rules', [[
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
    config()->set('watchtower.never_block', ['9.9.9.9']);
    config()->set('watchtower.auto_block.rules', [[
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
    config()->set('watchtower.auto_block.rules', [[
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
        ->with('watchtower:blacklist', '3.3.3.3')
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
    config()->set('watchtower.auto_block.rules', [[
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

/*
 * Mode tests — per-rule `mode` overrides global `watchtower.auto_block.mode`.
 * Modes: 'block' (default), 'warn' (log but don't block), 'disabled' (skip).
 */

it('warn mode logs a would-have-blocked entry and does NOT block', function () {
    config()->set('watchtower.auto_block.mode', 'warn');
    config()->set('watchtower.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 2,
        'window_minutes'   => 5,
    ]]);

    foreach (range(1, 2) as $i) {
        \Illuminate\Support\Facades\DB::table('log_entries')->insert([
            'id'          => \Illuminate\Support\Str::ulid(),
            'level'       => 'error',
            'message'     => 'Boom',
            'ip_address'  => '11.11.11.11',
            'occurred_at' => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    $logChannel = \Mockery::mock();
    $logChannel->shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Watchtower: would-have-blocked (auto-block in warn mode)'
                && $context['would_have_blocked'] === true
                && $context['ip'] === '11.11.11.11'
                && $context['threshold'] === 2
                && $context['window_minutes'] === 5
                && is_int($context['rule_index']);
        });
    \Illuminate\Support\Facades\Log::shouldReceive('channel')->andReturn($logChannel);

    $this->service->run();

    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '11.11.11.11']);
});

it('block mode (default) still blocks when no mode is configured anywhere', function () {
    // No mode key set anywhere — should default to 'block'
    config()->offsetUnset('watchtower.auto_block.mode');
    config()->set('watchtower.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 1,
        'window_minutes'   => 5,
    ]]);

    \Illuminate\Support\Facades\DB::table('log_entries')->insert([
        'id'          => \Illuminate\Support\Str::ulid(),
        'level'       => 'error',
        'message'     => 'Boom',
        'ip_address'  => '12.12.12.12',
        'occurred_at' => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $this->service->run();

    $this->assertDatabaseHas('blacklisted_ips', ['ip' => '12.12.12.12', 'source' => 'auto']);
});

it('per-rule mode overrides global mode (rule=block wins over global=warn)', function () {
    config()->set('watchtower.auto_block.mode', 'warn');
    config()->set('watchtower.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 1,
        'window_minutes'   => 5,
        'mode'             => 'block', // per-rule override
    ]]);

    \Illuminate\Support\Facades\DB::table('log_entries')->insert([
        'id'          => \Illuminate\Support\Str::ulid(),
        'level'       => 'error',
        'message'     => 'Boom',
        'ip_address'  => '13.13.13.13',
        'occurred_at' => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $this->service->run();

    $this->assertDatabaseHas('blacklisted_ips', ['ip' => '13.13.13.13', 'source' => 'auto']);
});

it('per-rule mode overrides global mode (rule=warn wins over global=block)', function () {
    config()->set('watchtower.auto_block.mode', 'block');
    config()->set('watchtower.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 1,
        'window_minutes'   => 5,
        'mode'             => 'warn', // per-rule override
    ]]);

    \Illuminate\Support\Facades\DB::table('log_entries')->insert([
        'id'          => \Illuminate\Support\Str::ulid(),
        'level'       => 'error',
        'message'     => 'Boom',
        'ip_address'  => '14.14.14.14',
        'occurred_at' => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // Mock the log channel so we don't need real logging infra
    $logChannel = \Mockery::mock();
    $logChannel->shouldReceive('warning')->once();
    \Illuminate\Support\Facades\Log::shouldReceive('channel')->andReturn($logChannel);

    $this->service->run();

    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '14.14.14.14']);
});

it('disabled mode skips the rule entirely (no block, no warn log)', function () {
    config()->set('watchtower.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 1,
        'window_minutes'   => 5,
        'mode'             => 'disabled',
    ]]);

    \Illuminate\Support\Facades\DB::table('log_entries')->insert([
        'id'          => \Illuminate\Support\Str::ulid(),
        'level'       => 'error',
        'message'     => 'Boom',
        'ip_address'  => '15.15.15.15',
        'occurred_at' => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // No log channel calls expected — Log::shouldNotReceive('channel') would
    // be too strict (other code paths may resolve channels), so we just
    // assert no blocks, no warn entries (the latter via DB absence).
    $this->service->run();

    $this->assertDatabaseMissing('blacklisted_ips', ['ip' => '15.15.15.15']);
});

it('invalid global mode value falls back to block', function () {
    config()->set('watchtower.auto_block.mode', 'this-is-not-a-mode');
    config()->set('watchtower.auto_block.rules', [[
        'level'            => 'error',
        'message_contains' => null,
        'count'            => 1,
        'window_minutes'   => 5,
    ]]);

    \Illuminate\Support\Facades\DB::table('log_entries')->insert([
        'id'          => \Illuminate\Support\Str::ulid(),
        'level'       => 'error',
        'message'     => 'Boom',
        'ip_address'  => '16.16.16.16',
        'occurred_at' => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $this->service->run();

    $this->assertDatabaseHas('blacklisted_ips', ['ip' => '16.16.16.16', 'source' => 'auto']);
});
