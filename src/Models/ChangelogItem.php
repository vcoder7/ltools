<?php

namespace Vcoder7\Ltools\Models;

use Illuminate\Database\Eloquent\Model;
use Vcoder7\Ltools\Http\Traits\CreateUuidTrait;

class ChangelogItem extends Model
{
    use CreateUuidTrait;

    protected $table = 'ltools_changelog_items';

    protected $fillable = ['model_id', 'model', 'changes', 'user_id'];
}
