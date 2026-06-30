<?php

namespace Vcoder7\Ltools\Services\Audit;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Vcoder7\Ltools\Enums\AuditEventEnum;
use Vcoder7\Ltools\Models\AuditLog;
use Vcoder7\Ltools\Services\Audit\Drivers\AuditDriver;

/**
 * Application-wide audit recorder.
 *
 * record()  — model lifecycle (created/updated/deleted/restored) with field-level diffs;
 *             driven automatically by the Auditable trait.
 * log()     — generic non-model event.
 * auth()/export()/download()/permission() — convenience wrappers for the common
 *             explicit events, pre-tagged and sensitivity-flagged.
 * withoutAuditing() — suppress auditing for a block (seeders, bulk tools, migrations).
 *
 * Actor + request context are attached centrally; secrets are redacted here, never at
 * the call site, so a mis-configured model can never leak a raw secret into the trail.
 */
class AuditService
{
    private bool $suppressed = false;

    public function __construct(
        private AuditContext $context,
        private AuditDriver $driver,
    ) {
    }

    public function record(AuditEventEnum|string $event, Model $model, array $options = []): ?AuditLog
    {
        if (! $this->enabled()) {
            return null;
        }

        $event = $this->eventValue($event);
        [$old, $new] = $this->resolveValues($event, $model);

        // Nothing actually changed on an update → no row (mirrors ChangelogService).
        if ($event === AuditEventEnum::Updated->value && ! $old && ! $new) {
            return null;
        }

        return $this->write($event, [
            'auditable_type' => $model::class,
            'auditable_id' => (string) $model->getKey(),
            'auditable_label' => $this->label($model),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'tags' => $this->mergeTags($model, $options['tags'] ?? []),
            'is_sensitive' => (bool) ($options['is_sensitive'] ?? $this->modelSensitive($model)),
            'meta' => $options['meta'] ?? null,
        ]);
    }

    public function log(AuditEventEnum|string $event, array $options = []): ?AuditLog
    {
        if (! $this->enabled()) {
            return null;
        }

        return $this->write($this->eventValue($event), [
            'auditable_type' => $options['auditable_type'] ?? null,
            'auditable_id' => isset($options['auditable_id']) ? (string) $options['auditable_id'] : null,
            'auditable_label' => $options['auditable_label'] ?? null,
            'old_values' => $options['old_values'] ?? null,
            'new_values' => $options['new_values'] ?? null,
            'tags' => $this->normalizeTags($options['tags'] ?? []),
            'is_sensitive' => (bool) ($options['is_sensitive'] ?? false),
            'meta' => $options['meta'] ?? null,
        ]);
    }

    public function auth(AuditEventEnum|string $event, array $options = []): ?AuditLog
    {
        return $this->log($event, $this->withDefaults($options, tags: ['security']));
    }

    public function export(array $options = []): ?AuditLog
    {
        return $this->log(AuditEventEnum::Export, $this->withDefaults($options, sensitive: true));
    }

    public function download(array $options = []): ?AuditLog
    {
        return $this->log(AuditEventEnum::Download, $this->withDefaults($options, sensitive: true));
    }

    public function permission(array $options = []): ?AuditLog
    {
        return $this->log(AuditEventEnum::PermissionChanged, $this->withDefaults($options, sensitive: true, tags: ['security']));
    }

    /**
     * Run a callback with auditing suppressed (e.g. seeders, bulk operations, data migrations).
     */
    public function withoutAuditing(Closure $callback): mixed
    {
        $previous = $this->suppressed;
        $this->suppressed = true;

        try {
            return $callback();
        } finally {
            $this->suppressed = $previous;
        }
    }

    public function isSuppressed(): bool
    {
        return $this->suppressed;
    }

    // Internals

    private function enabled(): bool
    {
        return ! $this->suppressed && (bool) config('ltools.audit.enabled', true);
    }

    private function eventValue(AuditEventEnum|string $event): string
    {
        return $event instanceof AuditEventEnum ? $event->value : $event;
    }

    private function write(string $event, array $payload): AuditLog
    {
        $attributes = array_merge(
            ['event' => $event],
            $payload,
            $this->actor(),
            $this->context->capture(),
            ['created_at' => now()->format('Y-m-d H:i:s')],
        );

        return $this->driver->persist($attributes);
    }

    /**
     * @return array{0: array, 1: array} [old_values, new_values]
     */
    private function resolveValues(string $event, Model $model): array
    {
        return match ($event) {
            AuditEventEnum::Created->value,
            AuditEventEnum::Restored->value => [[], $this->filterValues($model, $model->getAttributes())],
            AuditEventEnum::Deleted->value => [$this->filterValues($model, $model->getAttributes()), []],
            AuditEventEnum::Updated->value => $this->updatedValues($model),
            default => [[], $this->filterValues($model, $model->getAttributes())],
        };
    }

    private function updatedValues(Model $model): array
    {
        $new = $this->filterValues($model, $model->getChanges());

        if (! $new) {
            return [[], []];
        }

        $original = $model->getRawOriginal();
        $old = [];

        foreach (array_keys($new) as $key) {
            $old[$key] = $this->redactValue($key, $original[$key] ?? null);
        }

        return [$old, $new];
    }

    private function filterValues(Model $model, array $values): array
    {
        $only = $this->onlyFields($model);

        if ($only) {
            $values = Arr::only($values, $only);
        }

        $values = Arr::except($values, $this->excludedFields($model));

        return $this->redact($values);
    }

    private function redact(array $values): array
    {
        foreach ($this->redactedFields() as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '[REDACTED]';
            }
        }

        return $values;
    }

    private function redactValue(string $key, mixed $value): mixed
    {
        return in_array($key, $this->redactedFields(), true) ? '[REDACTED]' : $value;
    }

    private function excludedFields(Model $model): array
    {
        return array_values(array_unique(array_merge(
            (array) config('ltools.audit.global_excluded_fields', ['created_at', 'updated_at']),
            $this->modelArray($model, 'auditExclude'),
        )));
    }

    private function onlyFields(Model $model): array
    {
        return $this->modelArray($model, 'auditOnly');
    }

    private function redactedFields(): array
    {
        return (array) config('ltools.audit.redacted_fields', []);
    }

    private function mergeTags(Model $model, array $extra): ?array
    {
        return $this->normalizeTags(array_merge($this->modelArray($model, 'auditTags'), $extra));
    }

    private function normalizeTags(array $tags): ?array
    {
        $tags = array_values(array_unique(array_filter($tags, fn ($t) => $t !== null && $t !== '')));

        return $tags ?: null;
    }

    private function label(Model $model): string
    {
        if (method_exists($model, 'auditLabel')) {
            return $model->auditLabel();
        }

        return class_basename($model).' #'.$model->getKey();
    }

    private function actor(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [
                'actor_type' => null,
                'actor_id' => null,
                'actor_label' => config('ltools.audit.console_actor'),
            ];
        }

        $id = method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : ($user->id ?? null);

        return [
            'actor_type' => $user::class,
            'actor_id' => $id,
            'actor_label' => $this->actorLabel($user, $id),
        ];
    }

    private function actorLabel($user, $id): ?string
    {
        foreach (['name', 'full_name', 'email'] as $attr) {
            // Read via __get directly (assigning to a local) instead of empty($user->$attr),
            // because empty()/isset() consult __isset, which not every user object implements.
            $value = $user->{$attr};

            if (! empty($value)) {
                return (string) $value;
            }
        }

        return class_basename($user).($id !== null ? ' #'.$id : '');
    }

    private function modelArray(Model $model, string $property): array
    {
        return property_exists($model, $property) && is_array($model->{$property})
            ? $model->{$property}
            : [];
    }

    /**
     * A model may declare `public bool $auditIsSensitive = true;` to flag all of its
     * audit rows as high-sensitivity (e.g. permission/role-bearing models).
     */
    private function modelSensitive(Model $model): bool
    {
        return property_exists($model, 'auditIsSensitive') && (bool) $model->auditIsSensitive;
    }

    private function withDefaults(array $options, bool $sensitive = false, array $tags = []): array
    {
        if ($sensitive && ! isset($options['is_sensitive'])) {
            $options['is_sensitive'] = true;
        }

        if ($tags) {
            $options['tags'] = array_merge($tags, $options['tags'] ?? []);
        }

        return $options;
    }
}
