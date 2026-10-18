<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Services\AiClient;
use App\Services\LogReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Platform health for the Super Admin: services, queues and error logs. Counts and codes only - no document data. */
class SystemHealthController extends Controller
{
    public function index(AiClient $ai, LogReader $logs)
    {
        return view('admin.system', [
            'checks' => [
                'ai' => $this->safely(fn () => $ai->health()),
                'index' => $this->safely(fn () => $ai->indexHealth()),
                'database' => $this->databaseOk(),
                'storage' => is_writable(Storage::disk('local')->path('')),
            ],
            'queue' => [
                'driver' => config('queue.default'),
                'pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            ],
            'processing' => DB::table('processing_jobs')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            // Failed processing per organization, without document titles.
            'recentFailures' => DB::table('processing_jobs')
                ->join('documents', 'documents.id', '=', 'processing_jobs.document_id')
                ->join('organizations', 'organizations.id', '=', 'documents.organization_id')
                ->where('processing_jobs.status', 'failed')
                ->orderByDesc('processing_jobs.id')->limit(10)
                ->get(['organizations.name as organization', 'processing_jobs.error_code', 'processing_jobs.error_message', 'processing_jobs.finished_at']),
            'errorLog' => $logs->recent(25),
            'runtime' => ['php' => PHP_VERSION, 'laravel' => app()->version(), 'env' => app()->environment(), 'debug' => (bool) config('app.debug')],
        ]);
    }

    private function safely(callable $call): array
    {
        try { return $call(); } catch (AiServiceException $e) { return ['status' => 'unreachable', 'error' => $e->getMessage()]; }
    }

    private function databaseOk(): bool
    {
        try { DB::connection()->getPdo(); return true; } catch (\Throwable $e) { return false; }
    }
}
