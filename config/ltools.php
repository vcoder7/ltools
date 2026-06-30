<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Changelog
    |--------------------------------------------------------------------------
    |
    | ChangelogItem table name
    |
    */
    'table_name_changelog_items' => 'ltools_changelog_items',

    /*
    |--------------------------------------------------------------------------
    | Fields excluded from changelogs for all entities throughout the project.
    |--------------------------------------------------------------------------
    */
    'global_excluded_changelog_fields' => ['created_at', 'updated_at'],

    /*
    |--------------------------------------------------------------------------
    | User model class for correct user relation
    |--------------------------------------------------------------------------
    */
    'user_model_class' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Audit log (application-wide audit trail)
    |--------------------------------------------------------------------------
    |
    | Append-only, hash-chained audit trail produced by the Auditable trait and
    | the Audit facade. Fully additive — does not affect the legacy changelog above.
    */
    'audit' => [

        // Table backing the AuditLog model.
        'table_name' => 'audit_logs',

        // Global kill-switch. When false, nothing is recorded.
        'enabled' => true,

        // Persistence driver. 'sync' writes inline in the request transaction.
        // 'queue' is reserved for a future async driver (the seam exists; not yet shipped).
        'driver' => 'sync',

        // Attach request context (IP, user-agent, URL, method, request id) to each row.
        'capture_context' => true,

        // Label used as the actor when an event happens in console/cron (no authenticated user).
        'console_actor' => 'system',

        // Base key for the PostgreSQL advisory lock that serializes the hash chain per tenant.
        'lock_key' => 0x4C544F47, // "LTOG"

        // Fields whose VALUE must never be stored — replaced with [REDACTED] in old/new values.
        'redacted_fields' => ['password', 'remember_token', 'api_token', 'secret', 'code_hash'],

        // Fields omitted entirely from old/new values for every audited model.
        'global_excluded_fields' => ['created_at', 'updated_at', 'password', 'remember_token'],

    ],

];
