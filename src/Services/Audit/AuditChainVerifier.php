<?php

namespace Vcoder7\Ltools\Services\Audit;

use Vcoder7\Ltools\Models\AuditLog;

/**
 * Verifies the tamper-evidence hash chain: walks rows in id order and checks both
 * each row's own hash (recomputed from stored content) and its linkage to the
 * previous row. Verification runs from the earliest surviving row forward, so
 * retention pruning of the genesis end does not produce a false positive.
 */
class AuditChainVerifier
{
    /**
     * @return array{intact: bool, checked: int, broken_at_id: int|null, broken_at_uuid: string|null}
     */
    public function verify(?int $fromId = null, ?int $toId = null): array
    {
        $checked = 0;
        $prevHash = null;
        $first = true;

        $query = AuditLog::query()->orderBy('id');

        if ($fromId !== null) {
            $query->where('id', '>=', $fromId);
        }

        if ($toId !== null) {
            $query->where('id', '<=', $toId);
        }

        foreach ($query->cursor() as $row) {
            // Linkage: each row must point at the previous row's hash. The first row
            // examined is the chain anchor (its predecessor may have been pruned).
            if (! $first && $row->prev_hash !== $prevHash) {
                return $this->broken($row, $checked);
            }

            // Integrity: recompute this row's hash from its stored, immutable content.
            if (! $row->verifyHash()) {
                return $this->broken($row, $checked);
            }

            $prevHash = $row->hash;
            $first = false;
            $checked++;
        }

        return [
            'intact' => true,
            'checked' => $checked,
            'broken_at_id' => null,
            'broken_at_uuid' => null,
        ];
    }

    private function broken(AuditLog $row, int $checked): array
    {
        return [
            'intact' => false,
            'checked' => $checked,
            'broken_at_id' => $row->id,
            'broken_at_uuid' => $row->uuid,
        ];
    }
}
