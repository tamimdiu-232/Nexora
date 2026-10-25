<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $logs = AuditLog::with('user')
            ->when(!$u->isSuper(), fn ($q) => $q->where('organization_id', $u->organization_id))
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', str_replace(['%', '_'], '', $request->input('action')) . '%'))
            ->latest('created_at')->latest('id')->paginate(30)->withQueryString();
        return view('audit.index', ['logs' => $logs]);
    }
}
