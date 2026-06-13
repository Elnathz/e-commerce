<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()->latest('created_at');

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', "%{$term}%")
                  ->orWhere('subject_label', 'like', "%{$term}%")
                  ->orWhere('actor_name', 'like', "%{$term}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', 'like', $request->type . '.%');
        }

        if ($request->filled('actor')) {
            $query->where('actor_type', $request->actor);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/ActivityLog/Index', [
            'logs' => $logs,
            'filters' => $request->only(['q', 'type', 'actor', 'start_date', 'end_date']),
        ]);
    }
}
