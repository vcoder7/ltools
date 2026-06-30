<?php

namespace Vcoder7\Ltools\Services\Audit\Drivers;

use Vcoder7\Ltools\Models\AuditLog;

/**
 * Persistence seam for audit rows. The default SyncDriver writes inline within the
 * request transaction; a future QueueDriver can persist asynchronously without any
 * change at the call sites. Whichever driver performs the insert is responsible for
 * computing the hash-chain link (prev_hash + hash) under a per-tenant lock.
 */
interface AuditDriver
{
    public function persist(array $attributes): AuditLog;
}
