<?php

namespace Vcoder7\Ltools;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\{Facades\DB, ServiceProvider, Str};
use Vcoder7\Ltools\Services\StrMacroService;

class MacroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Str::macro('initials', function (string $name): string {
            $strService = app(StrMacroService::class);
            return $strService->initials($name);
        });

        Blueprint::macro('isPgsqlDriver', function (callable $callback) {
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';

            if ($isPgsql) {
                $callback($this);
            }

            return new class ($this, $isPgsql) {
                public function __construct(
                    private Blueprint $table,
                    private readonly bool $conditionMet,
                ) {
                }

                public function else(callable $callback): Blueprint
                {
                    if (!$this->conditionMet) {
                        $callback($this->table);
                    }

                    return $this->table;
                }
            };
        });
    }

    public function register(): void
    {
        //
    }
}
