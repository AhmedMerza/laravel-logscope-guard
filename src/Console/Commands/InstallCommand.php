<?php

declare(strict_types=1);

namespace LogScopeGuard\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'guard:install';

    protected $description = 'Install LogScope Guard: publish config and run migration';

    public function handle(): int
    {
        $this->info('Installing LogScope Guard...');

        $this->call('vendor:publish', ['--tag' => 'logscope-guard-config', '--force' => false]);
        $this->call('vendor:publish', ['--tag' => 'logscope-guard-migrations', '--force' => false]);
        $this->call('migrate');

        $this->newLine();
        $this->info('✓ LogScope Guard installed!');
        $this->line('');
        $this->line('Add these to your <comment>.env</comment>:');
        $this->line('  <comment>GUARD_ENABLED=true</comment>');
        $this->line('  <comment>GUARD_NEVER_BLOCK_IPS=127.0.0.1,::1,your.ip.here</comment>');
        $this->line('  <comment>GUARD_MASTER_URL=https://master.example.com</comment>  (for cross-env sync)');
        $this->line('  <comment>GUARD_SYNC_SECRET=a-long-random-secret</comment>');

        return self::SUCCESS;
    }
}
