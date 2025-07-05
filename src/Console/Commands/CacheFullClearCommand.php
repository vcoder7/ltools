<?php

namespace Vcoder7\Ltools\Console\Commands;

use Illuminate\Console\Command;

/**
 * @deprecated use CacheClearCommand
 */
class CacheFullClearCommand extends Command
{
    protected $signature = 'ltools:cache_full_clear';

    protected $description = 'Clear application, route, config and view cache';

    public function handle(): void
    {
        $this->warn('This command is deprecated, please use "ltools:cache-clear" command');

        $this->call('ltools:cache-clear');
    }
}
