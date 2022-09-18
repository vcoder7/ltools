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
php artisan ltools:cache_full_clear
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
