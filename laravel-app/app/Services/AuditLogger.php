<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Keys that must never be recorded in audit log metadata.
     */
    protected static array $sensitivePatterns = [
        '/password/i',
        '/secret/i',
        '/token/i',
        '/api_key/i',
        '/authorization/i',
        '/prompt/i',
        '/question/i',
        '/content/i',
        '/raw_text/i',
        '/ocr_text/i',
    ];

    /**
     * Record a sanitized audit event.
     */
    public static function log(string $action, ?Model $subject = null, array $metadata = []): AuditLog
    {
        $sanitizedMetadata = static::sanitizeMetadata($metadata);

        $user = Auth::user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'organization_id' => $user?->organization_id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => Request::ip(),
            'metadata' => empty($sanitizedMetadata) ? null : $sanitizedMetadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively strip sensitive keys and high-volume text from metadata.
     */
    public static function sanitizeMetadata(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            // Check if key matches sensitive patterns
            $isSensitive = false;
            foreach (static::$sensitivePatterns as $pattern) {
                if (preg_match($pattern, (string) $key)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                continue; // Strip entirely
            }

            if (is_array($value)) {
                $clean[$key] = static::sanitizeMetadata($value);
            } elseif (is_string($value)) {
                // Truncate strings to prevent log bloat
                $clean[$key] = mb_substr($value, 0, 255);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
