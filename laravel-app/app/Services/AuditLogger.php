<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    private const FORBIDDEN_KEYS = '/password|secret|token|api_key|authorization/i';

    /** Never pass passwords, secrets or document text here; forbidden keys are stripped defensively. */
    public static function log(string $action, ?Model $subject = null, array $meta = [], ?User $user = null): AuditLog
    {
        $user ??= auth()->user();
        $meta = array_filter($meta, fn ($v, $k) => !preg_match(self::FORBIDDEN_KEYS, (string) $k), ARRAY_FILTER_USE_BOTH);
        $orgId = $user?->organization_id ?? ($subject->organization_id ?? null);

        return AuditLog::create([
            'user_id' => $user?->id,
            'organization_id' => $orgId,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => request()?->ip(),
            'metadata' => $meta ?: null,
        ]);
    }
}
