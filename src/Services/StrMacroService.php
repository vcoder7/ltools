<?php

namespace Vcoder7\Ltools\Services;

use Illuminate\Support\{Arr, Str};

class StrMacroService
{
    public function initials(string $name): string
    {
        if (empty($name)) {
            return '';
        }

        $parts = preg_split('/\s+/', Str::trim($name));
        $initials = Arr::map($parts, fn ($part) => Str::substr($part, 0, 1));

        return Str::upper(strtoupper(implode('', $initials)));
    }
}
