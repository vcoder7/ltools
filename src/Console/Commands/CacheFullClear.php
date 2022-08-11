<?php

namespace Vcoder7\Ltools\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;

class CacheFullClear extends Command
{
    protected $signature = 'ltools:cache_full_clear';
    protected $description = 'Clear application, route, config and view cache';

    public function handle(): void
    {
        Artisan::call('cache:clear');
        $this->info('Application cache cleared!');

        Artisan::call('route:clear');
        $this->info('Route cache cleared!');

        Artisan::call('config:clear');
        $this->info('Configuration cache cleared!');

        Artisan::call('view:clear');
        $this->info('Compiled views cleared!');
    }
}
