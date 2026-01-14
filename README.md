# lTools - Features
**Laravel helper tools**

- [Cache clear command](#cache_clear_command)
- [Automatic ``uuid`` field value generation](#automatic_uuid_value_generation)
- [Str ``initials`` helper (macro)](#str_class_macros)
- [Changelog integration](#changelog_integration)

#### Installation
```
composer require vcoder7/ltools
```

### [Clear application, route, config and view cache](#cache_clear_command)
```
php artisan ltools:cache-clear
```

### [Automatic ``uuid`` field value generation](#automatic_uuid_value_generation)

1. Add to your migration ```$table->uuid()->unique();```
2. Add to your model:

```
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Vcoder7\Ltools\Http\Traits\CreateUuidTrait;

class MyModel extends Model
{
    use HasFactory, CreateUuidTrait;
}
```
### [Str ``initials`` helper (macro)](#str_class_macros)
The `Str::initials()` macro returns the uppercase initials of a given name string. It intelligently trims whitespace and handles multi-word names.

Usage:
```
use Illuminate\Support\Str;

Str::initials('John Peter Smith'); // Returns: "JPS"
```

### [Changelog integration](#changelog_integration)
**Setup:**

Publish config:
```
php artisan vendor:publish --tag=ltools-config
```

Publish migrations:
```
php artisan vendor:publish --tag=ltools-migrations
```
**Enable changelogs for one model**, add to the model `Vcoder7\Ltools\Http\Traits\RecordChangesTrait`

Example:
```
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Vcoder7\Ltools\Http\Traits\RecordChangesTrait;

class Page extends Model
{
    use RecordChangesTrait;
}
```

**Exclude fields from change logging**

Add to your model:
```
protected array $excludedChangelogFields = ['created_at', 'updated_at', 'email', 'secret_key'];
```

**Get changelogs for model**

```
$page = Page::find(1);
$changelogs = $page->changelogs;
```
**PGSQL driver check**
```
$table->isPgsqlDriver(function (Blueprint $table) {
    $table->jsonb('options')->nullable();
})->else(function (Blueprint $table) {
    $table->json('options')->nullable();
});
```
