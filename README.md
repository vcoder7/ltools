# lTools - Features
**Laravel helper tools**

- [Cache clear command](#cache_clear_command)
- [Automatic ``uuid`` field value generation](#automatic_uuid_value_generation)
- [Str ``initials`` helper (macro)](#str_class_macros)
- [Changelog integration](#changelog_integration)
- [Audit log (application-wide, tamper-evident)](#audit_log)

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

---

### [Audit log (application-wide, tamper-evident)](#audit_log)

An **append-only audit trail** for your whole Laravel app. Every recorded event captures
**who** did **what**, **when**, **from where** (IP / user-agent / URL / request id), and — for
model changes — the **field-level before/after diff**. Each row is **hash-chained** to the
previous one, so silent edits or deletions are detectable.

This is independent of the [Changelog](#changelog_integration) above. Changelog is a simple
per-model change list; the Audit log adds delete/restore capture, actor + request context,
secret redaction, a tamper-evident hash chain, retention pruning, and non-model events
(logins, exports, downloads). You can use either or both.

> Works on **PostgreSQL and MySQL/MariaDB** identically (the hash is computed in PHP; the
> per-tenant serialization lock uses PG advisory locks or MySQL `GET_LOCK`).

#### 1. Setup

Publish the config (optional — sensible defaults are merged automatically):
```
php artisan vendor:publish --tag=ltools-config
```

Run migrations to create the `audit_logs` table:
```
php artisan migrate
```
The package ships the migration; on a standard app `php artisan migrate` creates the table.
**Multi-tenant apps** (e.g. stancl/tenancy with a per-tenant schema) should instead place a
copy of the migration in their tenant migration path so the table is created per tenant —
the same way you would for any package table.

#### 2. Audit a model — the `Auditable` trait

Add the trait to any Eloquent model. It records `created`, `updated`, `deleted` and (for
SoftDeletes models) `restored` with a normalized old/new diff:

```php
use Illuminate\Database\Eloquent\Model;
use Vcoder7\Ltools\Http\Traits\Auditable;

class Contract extends Model
{
    use Auditable;

    // All optional — declare only what you need:
    public array $auditExclude = ['updated_at'];   // never record these fields
    public array $auditOnly    = ['status', 'amount']; // record ONLY these (whitelist)
    public array $auditTags    = ['billing'];       // tags applied to every row (for filtering)
    public bool  $auditIsSensitive = false;          // flag every row as high-sensitivity

    // Optional: customise the human label frozen onto each row
    public function auditLabel(): string
    {
        return 'Contract #'.$this->number;
    }
}
```

Read a record's history via the morph relation:
```php
$contract->audits()->latest('id')->get();
```

#### 3. Non-model events — the `Audit` facade

For things that aren't an Eloquent write (logins, exports, downloads, permission changes):

```php
use Vcoder7\Ltools\Enums\AuditEventEnum;
use Vcoder7\Ltools\Facades\Audit;

// Auth (tagged "security"); pass the subject user as the auditable
Audit::auth(AuditEventEnum::Login, [
    'auditable_type' => $user::class,
    'auditable_id'   => $user->id,
    'auditable_label'=> $user->name,
    'meta'           => ['source' => 'login_web'],
]);

// Data egress (flagged sensitive automatically)
Audit::export([
    'auditable_label' => 'Payroll June',
    'meta'            => ['format' => 'csv'],
]);
Audit::download([
    'auditable_type' => $file::class,
    'auditable_id'   => $file->id,
    'auditable_label'=> $file->name,
]);
Audit::permission([
    'auditable_type' => $group::class,
    'auditable_id'   => $group->id,
    'auditable_label'=> $group->name,
    'meta'           => ['permissions' => $permissions],
]);

// Generic
Audit::log('custom_event', ['auditable_label' => '…', 'meta' => [...]]);
```

Suppress auditing for a block (seeders, bulk tools, data migrations):
```php
Audit::withoutAuditing(fn () => $seeder->run());
```

#### 4. Configuration (`config/ltools.php` → `audit`)

```php
'audit' => [
    'table_name'             => 'audit_logs',
    'enabled'                => true,          // global kill-switch
    'driver'                 => 'sync',        // 'sync' (default). 'queue' reserved for async.
    'capture_context'        => true,          // IP / user-agent / URL / method / request id
    'console_actor'          => 'system',      // actor label for console/cron events
    'lock_key'               => 0x4C544F47,    // base key for the per-tenant chain lock
    'redacted_fields'        => ['password', 'remember_token', 'api_token', 'secret', 'code_hash'],
    'global_excluded_fields' => ['created_at', 'updated_at', 'password', 'remember_token'],
],
```
`redacted_fields` are stored as `[REDACTED]` (you see that they changed, never the value);
`global_excluded_fields` are dropped entirely. Set `user_model_class` (top of the config) so
the `AuditLog::actor()` relation resolves to your user model.

#### 5. Tamper-evidence — verify the chain

Each row stores `prev_hash` + `hash` = `sha256(immutable content || prev_hash)`. To check a
record hasn't been altered:
```php
$row->verifyHash(); // true = intact
```
To verify the whole chain (walks from the earliest surviving row forward, so retention
pruning of old rows is not a false positive):
```php
use Vcoder7\Ltools\Services\Audit\AuditChainVerifier;

$result = app(AuditChainVerifier::class)->verify();
// ['intact' => bool, 'checked' => int, 'broken_at_id' => ?int, 'broken_at_uuid' => ?string]
```

#### 6. Retention

The package does **not** delete anything on its own. Drive retention from your app: pick
rows older than your window and hard-delete them (chunked on PostgreSQL, which has no
`DELETE … LIMIT`):
```php
use Vcoder7\Ltools\Models\AuditLog;

AuditLog::query()->where('created_at', '<', now()->subYears(8))->limit(1000)->get()
    ->each->delete(); // or delete by id chunks in a loop
```
Pruning the oldest rows breaks the genesis end of the chain — that is expected and the
verifier handles it (it verifies from the earliest *surviving* row forward).

#### 7. Async (reserved)

Capture runs through a pluggable `AuditDriver` (`SyncDriver` is the default and writes inline
in the request transaction). A `queue` driver can be wired later via `config('ltools.audit.driver')`
with no call-site changes; the per-tenant hash-chain lock must hold inside whichever driver
performs the insert.

#### Key classes
| Purpose | Class |
|---|---|
| Trait | `Vcoder7\Ltools\Http\Traits\Auditable` |
| Facade | `Vcoder7\Ltools\Facades\Audit` |
| Service | `Vcoder7\Ltools\Services\Audit\AuditService` |
| Model | `Vcoder7\Ltools\Models\AuditLog` |
| Events | `Vcoder7\Ltools\Enums\AuditEventEnum` |
| Verifier | `Vcoder7\Ltools\Services\Audit\AuditChainVerifier` |
| Driver seam | `Vcoder7\Ltools\Services\Audit\Drivers\{AuditDriver, SyncDriver}` |
