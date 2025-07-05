<?php

namespace Vcoder7\Ltools\Console\Commands;

use Illuminate\Console\Command;

class CacheClearCommand extends Command
{
    protected $signature = 'ltools:cache-clear';

    protected $description = 'Clear application, route, config and view cache';

    public function handle(): void
    {
        $this->call('cache:clear');
        $this->call('route:clear');
        $this->call('config:clear');
        $this->call('view:clear');
        $this->newLine(2);

        $this->info('✅ Cache cleared successfully.');
    }
}
