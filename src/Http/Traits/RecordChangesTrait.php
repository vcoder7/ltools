<?php

namespace Vcoder7\Ltools\Http\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Vcoder7\Ltools\Models\ChangelogItem;

trait RecordChangesTrait
{
    public static function bootRecordChangesTrait(): void
    {
        static::created(function (Model $model) {
            ChangelogItem::create([
                'model_id' => $model->id,
                'model' => $model::class,
                'changes' => json_encode($model->toArray()),
            ]);
        });

        static::updated(function (Model $model) {
            $original = $model->getOriginal();
            $excludedFields = \array_values(\array_unique(\array_merge($model->excludedChangelogFields ?? [], config('ltools.global_excluded_changelog_fields'))));
            $changes = Arr::except($model->getChanges(), $excludedFields);

            if (count($changes) < 1) {
                return;
            }

            $diff = [];
            foreach ($changes as $fieldName => $value) {
                $oldValue = $original[$fieldName] ?? null;
                $newValue = $model->{$fieldName};
                if ($newValue === $oldValue) {
                    continue;
                }

                $diff[$fieldName] = ['old_value' => $original[$fieldName] ?? null, 'new_value' => $model->{$fieldName}];
            }

            ChangelogItem::create([
                'model_id' => $model->id,
                'model' => $model::class,
                'user_id' => auth()->user()?->id,
                'changes' => $diff,
            ]);
        });
    }

    public function changelogs(): MorphMany
    {
        return $this->morphMany(ChangelogItem::class, static::class, 'model', 'model_id');
    }
}
