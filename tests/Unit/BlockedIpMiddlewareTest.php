<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use LogScopeGuard\Http\Middleware\BlockedIpMiddleware;
use LogScopeGuard\Services\BlacklistCache;

beforeEach(function () {
    $this->cache = Mockery::mock(BlacklistCache::class);
    $this->middleware = new BlockedIpMiddleware($this->cache);
    $this->next = fn ($req) => response('ok', 200);
});

it('returns 403 for a blocked IP', function () {
    $this->cache->shouldReceive('isBlocked')->with('1.2.3.4')->andReturn(true);

    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '1.2.3.4');

    $response = $this->middleware->handle($request, $this->next);

    expect($response->getStatusCode())->toBe(403);
});

it('passes through for an unblocked IP', function () {
    $this->cache->shouldReceive('isBlocked')->with('5.5.5.5')->andReturn(false);

    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '5.5.5.5');

    $response = $this->middleware->handle($request, $this->next);

    expect($response->getStatusCode())->toBe(200);
});

it('passes through for a whitelisted IP even if Redis says blocked', function () {
    config()->set('logscope-guard.never_block', ['127.0.0.1']);
    $this->cache->shouldNotReceive('isBlocked');

    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $response = $this->middleware->handle($request, $this->next);

    expect($response->getStatusCode())->toBe(200);
});

it('passes through all requests when Guard is disabled', function () {
    config()->set('logscope-guard.enabled', false);
    $this->cache->shouldNotReceive('isBlocked');

    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '1.2.3.4');

    $response = $this->middleware->handle($request, $this->next);

    expect($response->getStatusCode())->toBe(200);
});
