<?php

namespace Vcoder7\Ltools\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Vcoder7\Ltools\Http\Traits\CreateUuidTrait;

class ChangelogItem extends Model
{
    use CreateUuidTrait;

    protected $table;

    protected $fillable = ['model_id', 'model', 'changes', 'user_id'];

    public $timestamps = false;

    protected $casts = [
        'changes' => 'json',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ltools.table_name_changelog_items', 'ltools_changelog_items'));
    }



    // Relations

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('ltools.user_model_class'));
    }
}
