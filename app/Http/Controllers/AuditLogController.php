<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Audit log viewer (M09). Hanya Owner.
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')
            ->orderBy('created_at', 'desc');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('entity_label', 'like', "%{$term}%")
                  ->orWhere('notes', 'like', "%{$term}%")
                  ->orWhere('user_name', 'like', "%{$term}%");
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        $entityTypes = AuditLog::select('entity_type')
            ->whereNotNull('entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type');

        $actions = AuditLog::ACTION_LABELS;

        return view('audit-logs.index', compact('logs', 'entityTypes', 'actions'));
    }
}
