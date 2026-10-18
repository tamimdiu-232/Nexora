<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Organization;
use App\Models\ProcessingJob;
use App\Models\Review;
use App\Models\SecurityIncident;
use App\Models\User;
use App\Services\AiClient;

class DashboardController extends Controller
{
    public function index(AiClient $ai)
    {
        $user = auth()->user();
        return $user->isSuper() ? $this->platformOverview($ai) : $this->organizationDashboard($user, $ai);
    }

    /** Everyday dashboard: numbers are limited to the documents this user may see. */
    private function organizationDashboard(User $user, AiClient $ai)
    {
        $documents = fn () => Document::visibleTo($user)->active();

        $reviewCounts = [];
        if ($user->hasPermission('reviews.view')) {
            $reviewCounts = Review::whereHas('requester', fn ($q) => $q->where('organization_id', $user->organization_id))
                ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        }

        // Admins see their organization's activity; everyone else sees their own.
        $activity = AuditLog::with('user')->latest('created_at')->limit(8)
            ->when($user->hasPermission('audit.view'),
                fn ($q) => $q->where('organization_id', $user->organization_id),
                fn ($q) => $q->where('user_id', $user->id))
            ->get();

        return view('dashboard', [
            'total' => $documents()->count(),
            'byCategory' => $documents()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category'),
            'byStatus' => $documents()->selectRaw('processing_status, count(*) as n')->groupBy('processing_status')->pluck('n', 'processing_status'),
            'failed' => $documents()->where('processing_status', 'failed')->latest()->limit(5)->get(),
            'activity' => $activity,
            'reviewCounts' => $reviewCounts,
            'aiHealth' => $user->hasPermission('users.manage') ? $this->aiHealth($ai) : null,
        ]);
    }

    /**
     * Platform operator overview. Only counts and health - never document titles, text, search results or chats.
     */
    private function platformOverview(AiClient $ai)
    {
        return view('dashboard-admin', [
            'organizations' => ['total' => Organization::count(), 'active' => Organization::where('is_active', true)->count()],
            'activeUsers' => User::where('is_active', true)->count(),
            'documentCount' => Document::count(),
            'processingFailures24h' => ProcessingJob::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            'openIncidents' => SecurityIncident::where('status', '!=', 'resolved')->count(),
            'failedLogins24h' => AuditLog::where('action', 'auth.login_failed')->where('created_at', '>=', now()->subDay())->count(),
            'aiHealth' => $this->aiHealth($ai),
            'activity' => AuditLog::with('user')->latest('created_at')
                ->where(fn ($q) => $q->where('action', 'like', 'organization.%')->orWhere('action', 'like', 'admin.%')
                    ->orWhere('action', 'like', 'incident.%')->orWhere('action', 'like', 'user.%'))
                ->limit(8)->get(),
        ]);
    }

    private function aiHealth(AiClient $ai): array
    {
        try { return $ai->health(); } catch (AiServiceException $e) { return ['status' => 'unreachable']; }
    }
}
