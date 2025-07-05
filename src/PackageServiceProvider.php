<?php

namespace Vcoder7\Ltools;

use Illuminate\Support\ServiceProvider;
use Vcoder7\Ltools\Console\Commands\{CacheClearCommand, CacheFullClearCommand};

class PackageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheFullClearCommand::class,
                CacheClearCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/ltools.php' => config_path('ltools.php'),
        ], 'ltools-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'ltools-migrations');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ltools.php', 'ltools');
    }
}
