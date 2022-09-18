<?php

namespace Vcoder7\Ltools\Http\Traits;

use Illuminate\Support\Str;

trait CreateUuidTrait
{
    public static function bootCreateUuidTrait()
    {
        static::creating(function ($model) {
            $model->uuid = (string)Str::uuid();
        });
    }
}
