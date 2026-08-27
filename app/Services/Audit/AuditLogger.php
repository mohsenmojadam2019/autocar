<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /** Writes an append-only audit record for sensitive state changes. */
    public function log(string $event, ?Model $subject = null, array $before = [], array $after = [], array $meta = [], ?Request $request = null): ActivityLog
    {
        $request ??= request();
        return ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'meta' => $meta ?: null,
        ]);
    }
}
