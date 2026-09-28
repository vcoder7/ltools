<?php

namespace Vcoder7\Ltools\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Vcoder7\Ltools\Http\Traits\CreateUuidTrait;

/**
 * A single immutable audit trail row.
 *
 * Rows are append-only. The hash chain (prev_hash + hash) makes silent edits or
 * deletions detectable: verifyHash() recomputes the row hash from its stored content
 * and its stored prev_hash, so any later mutation of an immutable field breaks the link.
 */
class AuditLog extends Model
{
    use CreateUuidTrait;

    const DEFAULT_TABLE_NAME = 'audit_logs';

    /**
     * Immutable fields that participate in the tamper-evidence hash, in a fixed order.
     * uuid/prev_hash/hash are intentionally excluded from the hashed payload (prev_hash
     * is folded in separately).
     */
    const HASHED_FIELDS = [
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'old_values',
        'new_values',
        'actor_type',
        'actor_id',
        'actor_label',
        'ip',
        'user_agent',
        'url',
        'http_method',
        'request_id',
        'tags',
        'meta',
        'is_sensitive',
        'created_at',
    ];

    protected $table;

    protected $fillable = [
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'old_values',
        'new_values',
        'actor_type',
        'actor_id',
        'actor_label',
        'ip',
        'user_agent',
        'url',
        'http_method',
        'request_id',
        'tags',
        'meta',
        'is_sensitive',
        'prev_hash',
        'hash',
        'created_at',
    ];

    public $timestamps = false;

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'tags' => 'array',
        'meta' => 'array',
        'is_sensitive' => 'boolean',
        'actor_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('ltools.audit.table_name', self::DEFAULT_TABLE_NAME));
    }

    // Relations

    public function actor(): BelongsTo
    {
        return $this->belongsTo(config('ltools.user_model_class'), 'actor_id');
    }

    // Hash chain

    /**
     * Compute the tamper-evidence hash for a row's immutable content + the previous row's hash.
     * Deterministic: arrays are recursively key-sorted and dates normalised to second precision.
     */
    public static function hashPayload(array $attributes, ?string $prevHash, bool $legacyDateTimeOrder = false): string
    {
        $canonical = [];

        foreach (self::HASHED_FIELDS as $field) {
            $value = $attributes[$field] ?? null;

            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value) || is_object($value)) {
                // Hash what the row will READ BACK as, not the in-memory PHP value. JSON
                // columns store the json_encode() of the value, and an object inside it
                // (a plain DateTime, stdClass, JsonSerializable) comes back as an array —
                // which normalize() then key-sorts, while the object itself was not sorted
                // at write time. Round-tripping here makes write and verify hash the same thing.
                $value = self::jsonRoundTrip($value);
            }

            $canonical[$field] = self::normalize($value, $legacyDateTimeOrder);
        }

        $canonical['prev_hash'] = $prevHash;

        return hash('sha256', json_encode(
            $canonical,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }

    /**
     * Recompute this row's hash from its persisted content and compare it to the stored hash.
     * Returns false if the row (or its chain link) has been tampered with.
     */
    public function verifyHash(): bool
    {
        $attributes = [];

        foreach (self::HASHED_FIELDS as $field) {
            $attributes[$field] = $field === 'created_at'
                ? $this->created_at?->format('Y-m-d H:i:s')
                : $this->{$field};
        }

        if (hash_equals($this->hash, self::hashPayload($attributes, $this->prev_hash))) {
            return true;
        }

        // Rows written before v1.1.1 hashed a plain PHP DateTime inside old/new values or meta
        // in its native json order (date, timezone_type, timezone) while verification sorted
        // it. That order is known, so those rows can still be proven unchanged. Only the key
        // ORDER of that one exact shape differs — the content must still match to the byte.
        return hash_equals($this->hash, self::hashPayload($attributes, $this->prev_hash, legacyDateTimeOrder: true));
    }

    private static function jsonRoundTrip(array|object $value): mixed
    {
        $json = json_encode($value);

        return $json === false ? $value : json_decode($json, true);
    }

    private static function normalize($value, bool $legacyDateTimeOrder = false)
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($legacyDateTimeOrder && self::isSerializedDateTime($value)) {
            return [
                'date' => $value['date'],
                'timezone_type' => $value['timezone_type'],
                'timezone' => $value['timezone'],
            ];
        }

        ksort($value);

        return array_map(fn ($v) => self::normalize($v, $legacyDateTimeOrder), $value);
    }

    /**
     * The exact shape json_encode() gives a plain DateTime / DateTimeImmutable.
     */
    private static function isSerializedDateTime(array $value): bool
    {
        $keys = array_keys($value);
        sort($keys);

        return $keys === ['date', 'timezone', 'timezone_type'];
    }
}
