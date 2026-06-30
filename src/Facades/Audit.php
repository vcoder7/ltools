<?php

namespace Vcoder7\Ltools\Facades;

use Illuminate\Support\Facades\Facade;
use Vcoder7\Ltools\Services\Audit\AuditService;

/**
 * @method static ?\Vcoder7\Ltools\Models\AuditLog record(\Vcoder7\Ltools\Enums\AuditEventEnum|string $event, \Illuminate\Database\Eloquent\Model $model, array $options = [])
 * @method static ?\Vcoder7\Ltools\Models\AuditLog log(\Vcoder7\Ltools\Enums\AuditEventEnum|string $event, array $options = [])
 * @method static ?\Vcoder7\Ltools\Models\AuditLog auth(\Vcoder7\Ltools\Enums\AuditEventEnum|string $event, array $options = [])
 * @method static ?\Vcoder7\Ltools\Models\AuditLog export(array $options = [])
 * @method static ?\Vcoder7\Ltools\Models\AuditLog download(array $options = [])
 * @method static ?\Vcoder7\Ltools\Models\AuditLog permission(array $options = [])
 * @method static mixed withoutAuditing(\Closure $callback)
 * @method static bool isSuppressed()
 *
 * @see \Vcoder7\Ltools\Services\Audit\AuditService
 */
class Audit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuditService::class;
    }
}
