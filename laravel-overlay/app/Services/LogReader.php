<?php

namespace App\Services;

/**
 * Reads recent ERROR-level lines from the Laravel log for the platform health page.
 * Privacy: only the first line of each entry is kept (no stack traces), SQL details and e-mail addresses are removed,
 * and long messages are cut, so organization data that might appear inside an exception cannot be browsed here.
 */
class LogReader
{
    public function recent(int $limit = 25, ?string $path = null): array
    {
        $path ??= storage_path('logs/laravel.log');
        if (!is_file($path)) return [];

        $handle = fopen($path, 'rb');
        fseek($handle, max(0, filesize($path) - 300000)); // only the last ~300 KB
        $text = (string) stream_get_contents($handle);
        fclose($handle);

        preg_match_all('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] [\w.-]+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $text, $matches, PREG_SET_ORDER);

        return collect($matches)->take(-$limit)->reverse()->values()
            ->map(fn ($m) => ['time' => $m[1], 'level' => $m[2], 'message' => $this->clean($m[3])])->all();
    }

    private function clean(string $message): string
    {
        $message = preg_replace('/\(Connection:.*$/', '[SQL details omitted]', $message);
        $message = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '[email]', $message);
        return mb_substr($message, 0, 300);
    }
}
