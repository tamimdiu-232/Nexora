<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Review;
use App\Services\AiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request, AiClient $aiClient)
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('platform.dashboard');
        }

        // Compute scoped metrics
        $visibleQuery = Document::visibleTo($user)->active();

        $totalAccessible = (clone $visibleQuery)->count();
        $processedCount = (clone $visibleQuery)->where('processing_status', 'completed')->count();
        $processingCount = (clone $visibleQuery)->whereIn('processing_status', ['pending', 'processing'])->count();
        $failedCount = (clone $visibleQuery)->where('processing_status', 'failed')->count();

        // Category breakdown
        $categories = (clone $visibleQuery)
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->all();

        // Recent documents
        $recentDocuments = (clone $visibleQuery)
            ->with(['department', 'uploader'])
            ->latest()
            ->take(6)
            ->get();

        // Pending reviews in organization
        $pendingReviewsCount = 0;
        if ($user->hasPermission('reviews.view') || $user->hasPermission('reviews.decide')) {
            $pendingReviewsCount = Review::whereHas('message.session', function ($q) use ($user) {
                $q->where('organization_id', $user->organization_id);
            })->where('status', 'pending')->count();
        }

        // Recent audit activity (admins see org-wide; officers/others see own)
        $auditQuery = AuditLog::query();
        if ($user->isOrgAdmin()) {
            $auditQuery->where('organization_id', $user->organization_id);
        } else {
            $auditQuery->where('user_id', $user->id);
        }
        $recentActivity = $auditQuery->latest('created_at')->take(8)->get();

        // AI health status (for admins)
        $aiHealth = null;
        if ($user->isOrgAdmin() || $user->isDeptAdmin()) {
            $aiHealth = $aiClient->health();
        }

        return view('dashboard.index', compact(
            'user',
            'totalAccessible',
            'processedCount',
            'processingCount',
            'failedCount',
            'categories',
            'recentDocuments',
            'pendingReviewsCount',
            'recentActivity',
            'aiHealth'
        ));
    }
}
