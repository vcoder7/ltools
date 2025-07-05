<?php

namespace Vcoder7\Ltools;

use Illuminate\Support\ServiceProvider;
use Vcoder7\Ltools\Console\Commands\CacheClearCommand;
use Vcoder7\Ltools\Console\Commands\CacheFullClearCommand;

class PackageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheFullClearCommand::class,
                CacheClearCommand::class,
            ]);

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }
}
