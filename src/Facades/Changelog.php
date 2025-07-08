<?php

namespace Vcoder7\Ltools\Facades;

use Illuminate\Support\Facades\Facade;
use Vcoder7\Ltools\Services\ChangelogService;

/**
 * @method static \Vcoder7\Ltools\Models\ChangelogItem create(\Illuminate\Database\Eloquent\Model $model)
 * @method static ?\Vcoder7\Ltools\Models\ChangelogItem update(\Illuminate\Database\Eloquent\Model $model)
 *
 * @see \Vcoder7\Ltools\Services\ChangelogService
 */
class Changelog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChangelogService::class;
    }
}
