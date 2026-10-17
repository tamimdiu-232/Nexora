<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Review;
use App\Services\AiClient;
use App\Exceptions\AiServiceException;

class DashboardController extends Controller
{
    public function index(AiClient $ai)
    {
        $u = auth()->user();
        $base = fn () => Document::visibleTo($u)->active();

        $reviewCounts = [];
        if ($u->hasPermission('reviews.view')) {
            $reviewCounts = Review::whereHas('requester', fn ($q) => $u->isSuper() ? $q : $q->where('organization_id', $u->organization_id))
                ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        }

        $activity = AuditLog::with('user')->latest('created_at')->limit(10)
            ->when($u->hasPermission('audit.view'),
                fn ($q) => $u->isSuper() ? $q : $q->where('organization_id', $u->organization_id),
                fn ($q) => $q->where('user_id', $u->id))
            ->get();

        $aiHealth = null;
        if ($u->hasPermission('users.manage')) {
            try { $aiHealth = $ai->health(); } catch (AiServiceException $e) { $aiHealth = ['status' => 'unreachable']; }
        }

        return view('dashboard', [
            'total' => $base()->count(),
            'byCategory' => $base()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category'),
            'byStatus' => $base()->selectRaw('processing_status, count(*) as n')->groupBy('processing_status')->pluck('n', 'processing_status'),
            'failed' => $base()->where('processing_status', 'failed')->latest()->limit(5)->get(),
            'activity' => $activity,
            'reviewCounts' => $reviewCounts,
            'aiHealth' => $aiHealth,
        ]);
    }
}
