### lTools
Laravel helper tools
***
### Composer
```
composer require vcoder7/ltools
```

# Commands
##### Clear application, route, config and view cache

```
php artisan ltools:cache-clear
```

# Traits
##### Unique UUID

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

# Changelogs
### Setup:

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

class User extends Model
{
    use RecordChangesTrait;
}
```

**Exclude fields from change logging**

Add to your model:
```
protected array $excludedChangelogFields = ['created_at', 'updated_at', 'email', 'credit_card_number'];
```
