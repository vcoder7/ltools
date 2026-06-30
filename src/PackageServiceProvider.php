<?php

namespace Vcoder7\Ltools;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Vcoder7\Ltools\Console\Commands\{CacheClearCommand, CacheFullClearCommand};
use Vcoder7\Ltools\Services\Audit\AuditContext;
use Vcoder7\Ltools\Services\Audit\AuditService;
use Vcoder7\Ltools\Services\Audit\Drivers\{AuditDriver, SyncDriver};
use Vcoder7\Ltools\Services\{ChangelogService, StrMacroService};

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

        $this->app->register(MacroServiceProvider::class);

        $this->app->singleton(StrMacroService::class, fn () => new StrMacroService());
        $this->app->singleton(ChangelogService::class, fn () => new ChangelogService());

        $this->app->singleton(AuditContext::class, fn () => new AuditContext());
        $this->app->singleton(AuditService::class, function (Application $app) {
            return new AuditService($app->make(AuditContext::class), $this->resolveAuditDriver($app));
        });
    }

    private function resolveAuditDriver(Application $app): AuditDriver
    {
        $driver = $app['config']->get('ltools.audit.driver', 'sync');

        return match ($driver) {
            'sync' => new SyncDriver(),
            // 'queue' => new QueueDriver(),  // reserved async seam — see docs/audit-log-plan.md
            default => throw new InvalidArgumentException("Audit driver [{$driver}] is not registered."),
        };
    }
}
