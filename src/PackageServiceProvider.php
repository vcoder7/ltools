<?php

namespace Vcoder7\Ltools;

use Illuminate\Support\ServiceProvider;
use Vcoder7\Ltools\Console\Commands\CacheFullClear;

class PackageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheFullClear::class,
            ]);

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }
}
