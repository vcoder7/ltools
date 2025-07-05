<?php

namespace Vcoder7\Ltools\Models;

use Illuminate\Database\Eloquent\Model;
use Vcoder7\Ltools\Http\Traits\CreateUuidTrait;

class ChangelogItem extends Model
{
    use CreateUuidTrait;

    protected $table;

    protected $fillable = ['model_id', 'model', 'changes', 'user_id'];

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ltools.table_name_changelog_items', 'ltools_changelog_items'));
    }
}
