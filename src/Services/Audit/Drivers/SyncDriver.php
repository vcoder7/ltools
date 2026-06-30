<?php

namespace Vcoder7\Ltools\Services\Audit\Drivers;

use Illuminate\Database\Connection;
use Vcoder7\Ltools\Models\AuditLog;

/**
 * Default driver: persists the audit row synchronously inside a DB transaction.
 *
 * The hash chain is ordered by id. To keep it consistent under concurrency, the
 * tail read + insert are serialized per tenant with a database advisory lock:
 *
 *   - PostgreSQL: pg_advisory_xact_lock(base, hashtext(current_schema())) acquired
 *     INSIDE the transaction, so it auto-releases on commit/rollback (no leak). The
 *     per-schema component means tenants (schema-isolated) don't block each other.
 *   - MySQL / MariaDB: GET_LOCK(name, timeout) acquired BEFORE the transaction and
 *     released in a finally (GET_LOCK is connection-scoped, not transaction-scoped).
 *     The name is namespaced by database, so database-per-tenant setups don't collide.
 *   - SQLite / others: no lock (single-writer; the test db is single-threaded). The
 *     transaction + ordered tail read still produce a correct chain.
 *
 * The hash itself is computed in PHP, so it is identical on every database engine.
 */
class SyncDriver implements AuditDriver
{
    private const LOCK_TIMEOUT_SECONDS = 10;

    public function persist(array $attributes): AuditLog
    {
        /** @var Connection $connection */
        $connection = (new AuditLog())->getConnection();
        $driver = $connection->getDriverName();

        $mysqlLock = $this->acquireMysqlLock($connection, $driver);

        try {
            return $connection->transaction(function () use ($connection, $driver, $attributes) {
                $this->acquirePostgresLock($connection, $driver);

                $prevHash = AuditLog::query()->orderByDesc('id')->value('hash');

                $attributes['prev_hash'] = $prevHash;
                $attributes['hash'] = AuditLog::hashPayload($attributes, $prevHash);

                return AuditLog::query()->create($attributes);
            });
        } finally {
            if ($mysqlLock !== null) {
                $connection->statement('SELECT RELEASE_LOCK(?)', [$mysqlLock]);
            }
        }
    }

    private function acquirePostgresLock(Connection $connection, string $driver): void
    {
        if ($driver !== 'pgsql') {
            return;
        }

        $key = (int) config('ltools.audit.lock_key', 0x4C544F47); // "LTOG"

        // Two-int transaction advisory lock: base constant + per-schema (per-tenant) component.
        $connection->statement('SELECT pg_advisory_xact_lock(?, hashtext(current_schema()))', [$key]);
    }

    private function acquireMysqlLock(Connection $connection, string $driver): ?string
    {
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        // GET_LOCK name max length is 64. Namespace by database so tenants don't share a lock.
        $name = 'ltools_audit_'.substr(sha1((string) $connection->getDatabaseName()), 0, 32);

        $connection->statement('SELECT GET_LOCK(?, ?)', [$name, self::LOCK_TIMEOUT_SECONDS]);

        return $name;
    }
}
