<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user->hasPermission('audit.view')) {
            abort(403, 'You do not have permission to view audit logs.');
        }

        $query = AuditLog::with(['user', 'organization']);

        // Scope: Super Admin sees all; Org Admin sees own org
        if ($user->isSuperAdmin()) {
            // all orgs
            if ($orgId = $request->input('organization_id')) {
                $query->where('organization_id', $orgId);
            }
        } else {
            $query->where('organization_id', $user->organization_id);
        }

        // Action prefix filter (e.g. document, auth, user, admin)
        if ($action = $request->input('action')) {
            $query->where('action', 'like', "{$action}%");
        }

        $logs = $query->latest('created_at')->paginate(20)->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
