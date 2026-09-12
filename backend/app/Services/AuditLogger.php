<?php

namespace Savv\Services;

use Illuminate\Support\Facades\Log;
use Savv\Models\AuditLog;

/**
 * Writes redacted audit events: identifiers, action types, and timestamps
 * only. $metadata must never contain product titles, order numbers,
 * tracking numbers, addresses, or auth material - callers are responsible
 * for only passing safe scalars/counts.
 */
final class AuditLogger
{
    public static function record(
        string $action,
        ?int $userId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
        ?string $ipAddress = null,
    ): void {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip_address' => $ipAddress,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);

        Log::channel('audit')->info($action, [
            'user_id' => $userId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
    }
}
