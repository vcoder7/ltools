<?php

namespace Vcoder7\Ltools\Http\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Vcoder7\Ltools\Facades\Changelog;
use Vcoder7\Ltools\Models\ChangelogItem;

trait RecordChangesTrait
{
    public static function bootRecordChangesTrait(): void
    {
        static::created(function (Model $model) {
            Changelog::create($model);
        });

        static::updated(function (Model $model) {
            Changelog::update($model);
        });
    }

    public function changelogs(): MorphMany
    {
        return $this->morphMany(ChangelogItem::class, static::class, 'model', 'model_id');
    }
}
