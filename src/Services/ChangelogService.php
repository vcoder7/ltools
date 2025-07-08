<?php

namespace Vcoder7\Ltools\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Vcoder7\Ltools\Models\ChangelogItem;

class ChangelogService
{
    public function create(Model $model): ChangelogItem
    {
        return ChangelogItem::create([
            'model_id' => $model->id,
            'model' => $model::class,
            'changes' => $model->toArray(),
            'user_id' => auth()->user()?->id,
        ]);
    }

    public function update(Model $model): ?ChangelogItem
    {
        $diff = [];
        $original = $model->getOriginal();
        $excludedFields = array_values(array_unique(array_merge(
            $model->excludedChangelogFields ?? [],
            config('ltools.global_excluded_changelog_fields', [])
        )));

        $changes = Arr::except($model->getChanges(), $excludedFields);

        if (count($changes) < 1) {
            $changes = [];

            foreach (Arr::except($model->getAttributes(), $excludedFields) as $key => $value) {
                $originalValue = $original[$key] ?? null;

                if ($value !== $originalValue) {
                    $changes[$key] = $value;
                }
            }

            if (count($changes) < 1) {
                return null;
            }
        }

        foreach ($changes as $fieldName => $value) {
            $oldValue = $original[$fieldName] ?? null;
            $newValue = $model->{$fieldName};

            if ($oldValue === $newValue) {
                continue;
            }
            $diff[$fieldName] = [
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ];
        }

        if (count($diff) < 1) {
            return null;
        }

        return ChangelogItem::create([
            'model_id' => $model->id,
            'model' => $model::class,
            'user_id' => auth()->user()?->id,
            'changes' => $diff,
        ]);
    }
}
