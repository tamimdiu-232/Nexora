<?php

namespace App\Services;

class LogReader
{
    /**
     * Read and sanitize the latest ERROR-level entries from storage/logs/laravel.log.
     * Sanitization rules:
     * - Only first line of ERROR entries
     * - SQL details removed
     * - Email addresses masked
     * - No stack traces
     * - Max 300 chars per entry
     * - Only scans the last ~300 KB of log file
     */
    public static function readSanitizedErrorLogs(int $limit = 50): array
    {
        $logPath = storage_path('logs/laravel.log');
        if (!file_exists($logPath) || !is_readable($logPath)) {
            return [];
        }

        $fileSize = filesize($logPath);
        $readBytes = min($fileSize, 300 * 1024); // max 300 KB

        $fp = fopen($logPath, 'rb');
        if (!$fp) {
            return [];
        }

        // Seek to the end minus readBytes
        fseek($fp, -$readBytes, SEEK_END);
        $chunk = fread($fp, $readBytes);
        fclose($fp);

        if (!$chunk) {
            return [];
        }

        $lines = explode("\n", $chunk);
        $errorEntries = [];

        // Match log entry start: [YYYY-MM-DD HH:MM:SS] environment.ERROR: message
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[^\]]+)\]\s+[\w\.\-]+(?:\.|\s+)(ERROR|CRITICAL|ALERT|EMERGENCY):\s*(.+)$/i';

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Check if this line is an ERROR entry header
            if (preg_match($pattern, $line, $matches)) {
                $timestamp = $matches[1];
                $level = strtoupper($matches[2]);
                $rawMessage = $matches[3];

                // 1. Remove stack trace references or JSON payloads attached to first line
                $message = preg_replace('/\{.*?\}$/', '', $rawMessage);
                $message = preg_replace('/\[stacktrace\].*$/i', '', $message);

                // 2. Strip SQL queries (e.g. select ... from ...)
                $message = preg_replace('/(select|insert|update|delete)\s+.*?\s+from\s+[^\s]+/i', '[SQL QUERY REDACTED]', $message);

                // 3. Mask email addresses
                $message = preg_replace('/[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+/', '[EMAIL REDACTED]', $message);

                // 4. Truncate to 300 characters max
                if (mb_strlen($message) > 300) {
                    $message = mb_substr($message, 0, 297) . '...';
                }

                $errorEntries[] = [
                    'timestamp' => $timestamp,
                    'level' => $level,
                    'message' => $message,
                ];
            }
        }

        // Return latest entries first
        return array_slice(array_reverse($errorEntries), 0, $limit);
    }
}
