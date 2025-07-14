<?php

namespace Vcoder7\Ltools;

use Illuminate\Support\{ServiceProvider, Str};
use Vcoder7\Ltools\Services\StrMacroService;

class MacroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Str::macro('initials', function (string $name): string {
            $strService = app(StrMacroService::class);
            return $strService->initials($name);
        });
    }

    public function register(): void
    {
        //
    }
}
