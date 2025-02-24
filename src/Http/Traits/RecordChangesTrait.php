<?php

namespace Vcoder7\Ltools\Http\Traits;

use Illuminate\Database\Eloquent\Model;
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
            $changes = $model->getChanges();
            if (count($changes) < 1) {
                return;
            }

            $diff = [];
            foreach ($changes as $field => $value) {
                $diff[$field] = ['old_value' => $original[$field] ?? null, 'new_value' => $value];
            }

            ChangelogItem::create([
                'model_id' => $model->id,
                'model' => $model::class,
                'user_id' => auth()->user()?->id,
                'changes' => json_encode($diff),
            ]);
        });
    }
}
