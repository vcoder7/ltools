<?php

namespace Vcoder7\Ltools\Http\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Vcoder7\Ltools\Enums\AuditEventEnum;
use Vcoder7\Ltools\Facades\Audit;
use Vcoder7\Ltools\Models\AuditLog;

/**
 * Opt-in auditing for a model. Records created/updated/deleted/restored events with
 * field-level diffs into audit_logs via the Audit service.
 *
 * Optional per-model controls (declare as public properties on the model):
 *   public array $auditExclude = ['some_noisy_column'];   // never recorded
 *   public array $auditOnly    = ['status', 'price'];     // record ONLY these fields
 *   public array $auditTags    = ['payroll'];             // tags applied to every row
 * Override auditLabel() to customise the frozen human label.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => Audit::record(AuditEventEnum::Created, $model));
        static::updated(fn (Model $model) => Audit::record(AuditEventEnum::Updated, $model));
        static::deleted(fn (Model $model) => Audit::record(AuditEventEnum::Deleted, $model));

        // Fires only for models using SoftDeletes; harmless to register otherwise.
        static::registerModelEvent('restored', fn (Model $model) => Audit::record(AuditEventEnum::Restored, $model));
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Human label for this record, frozen onto the audit row at event time.
     */
    public function auditLabel(): string
    {
        foreach (['name', 'title', 'label', 'email'] as $attribute) {
            if (! empty($this->{$attribute})) {
                return class_basename($this).': '.$this->{$attribute};
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }
}
