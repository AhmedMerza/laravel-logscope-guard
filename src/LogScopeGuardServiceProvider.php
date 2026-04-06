<?php

declare(strict_types=1);

namespace LogScopeGuard;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use LogScopeGuard\Console\Commands\CleanupCommand;
use LogScopeGuard\Console\Commands\InstallCommand;
use LogScopeGuard\Console\Commands\SyncCommand;
use LogScopeGuard\Events\IpBlocked;
use LogScopeGuard\Http\Controllers\BlockController;
use LogScopeGuard\Http\Middleware\BlockedIpMiddleware;
use LogScopeGuard\Listeners\NotifyOnBlock;
use LogScopeGuard\Services\AutoBlockService;
use LogScopeGuard\Services\BlacklistCache;
use LogScopeGuard\Services\BlacklistService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LogScopeGuardServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('logscope-guard')
            ->hasConfigFile()
            ->hasMigration('create_blacklisted_ips_table')
            ->runsMigrations()
            ->hasViews()
            ->hasCommands([InstallCommand::class, SyncCommand::class, CleanupCommand::class]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(BlacklistCache::class);
        $this->app->singleton(BlacklistService::class);
        $this->app->singleton(AutoBlockService::class);
    }

    public function bootingPackage(): void
    {
        if (! config('logscope-guard.enabled', true)) {
            return;
        }

        // Must run before sessions, auth, and LogScope's CaptureRequestContext
        $this->app->make(Kernel::class)->prependMiddleware(BlockedIpMiddleware::class);

        // Warm Redis from DB on boot if the key is missing (e.g. after Redis flush)
        $this->app->booted(function () {
            $this->app->make(BlacklistCache::class)->warmOnBoot();
        });

        $this->registerGuardRoutes();

        Event::listen(IpBlocked::class, NotifyOnBlock::class);

        if (config('logscope-guard.auto_block.enabled', false)) {
            Schedule::call(fn () => $this->app->make(AutoBlockService::class)->run())
                ->everyMinute()
                ->name('logscope-guard:auto-block')
                ->withoutOverlapping();
        }

        if (config('logscope-guard.cleanup.enabled', true)) {
            Schedule::command('guard:cleanup')
                ->daily()
                ->name('logscope-guard:cleanup')
                ->withoutOverlapping();
        }
    }

    protected function registerGuardRoutes(): void
    {
        // Guard routes share LogScope's prefix and Authorize middleware
        $logscopePrefix = config('logscope.routes.prefix', 'logscope');
        $logscopeMiddleware = config('logscope.routes.middleware', ['web']);

        // Resolve LogScope's Authorize middleware class dynamically so Guard
        // doesn't hard-depend on LogScope's internal class path
        $authorizeClass = 'LogScope\\Http\\Middleware\\Authorize';

        $middleware = class_exists($authorizeClass)
            ? array_merge($logscopeMiddleware, [$authorizeClass])
            : $logscopeMiddleware;

        Route::group([
            'prefix'     => $logscopePrefix.'/guard',
            'middleware' => $middleware,
            'domain'     => config('logscope.routes.domain'),
        ], function () {
            Route::post('/api/block', [BlockController::class, 'block']);
            Route::delete('/api/block/{ip}', [BlockController::class, 'unblock'])->where('ip', '.*');
            Route::get('/api/status/{ip}', [BlockController::class, 'status'])->where('ip', '.*');
            Route::get('/api/blocks', [BlockController::class, 'index']);
        });
    }
}
