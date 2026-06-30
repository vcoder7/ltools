<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The application-wide audit trail. Append-only and immutable: rows are never
     * updated or soft-deleted; the only post-insert write is the retention prune
     * (hard delete). Each row is hash-chained to the previous one for tamper-evidence.
     */
    public function up(): void
    {
        Schema::create(config('ltools.audit.table_name', 'audit_logs'), function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique('IDX_audit_uuid');

            // What happened — an AuditEventEnum value (created|updated|deleted|restored|
            // login|logout|login_failed|otp_requested|export|download|permission_changed).
            $table->string('event');

            // Subject of the event (the audited model). Nullable for non-model events
            // such as login/export. auditable_id is a string so it can hold int or uuid keys
            // (bounded to 64 so the composite index stays within MySQL key-length limits).
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id', 64)->nullable();
            // Human label of the subject frozen at event time (stays readable after the
            // referenced record is renamed or deleted).
            $table->string('auditable_label')->nullable();

            // Field-level change payloads: { field: value, ... }. Secrets are redacted
            // before storage (see config ltools.audit.redacted_fields).
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Who did it. Nullable actor = system / console / unauthenticated.
            $table->string('actor_type')->nullable();
            $table->bigInteger('actor_id')->nullable();
            // Actor name/email frozen at event time (survives later user deletion).
            $table->string('actor_label')->nullable();

            // Request context (null for console/cron). ip sized for IPv6.
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('url')->nullable();
            $table->string('http_method', 16)->nullable();
            // Correlation id tying together multiple rows produced by one request.
            $table->string('request_id', 64)->nullable();

            // Free-form string[] for grouping/filtering (e.g. ["payroll","security"]).
            $table->json('tags')->nullable();
            // Event-specific extras (export format, file uuid, OTP channel, reason, ...).
            $table->json('meta')->nullable();

            // High-sensitivity marker (permission/payroll/export) for UI emphasis.
            $table->boolean('is_sensitive')->default(false);

            // Tamper-evidence hash chain. hash = sha256(canonical immutable content || prev_hash).
            // prev_hash = hash of the immediately preceding row in this tenant's chain (NULL = genesis).
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64);

            // Append-only: event time only, no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'IDX_audit_created_at');
            $table->index(['auditable_type', 'auditable_id'], 'IDX_audit_auditable');
            $table->index(['actor_type', 'actor_id'], 'IDX_audit_actor');
            $table->index('event', 'IDX_audit_event');
            $table->index('request_id', 'IDX_audit_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ltools.audit.table_name', 'audit_logs'));
    }
};
