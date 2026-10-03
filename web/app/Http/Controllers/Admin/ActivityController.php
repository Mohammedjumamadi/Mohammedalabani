<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $query = ActivityLog::query()->with('user')->latest('created_at')->latest('id');

        if ($action = $request->query('action')) {
            $query->where('action', 'like', addcslashes((string) $action, '%_\\').'%');
        }
        if ($user = trim((string) $request->query('user'))) {
            $query->whereHas('user', fn ($q) => $q->where('email', 'like', '%'.addcslashes($user, '%_\\').'%'));
        }
        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return view('admin.activity', [
            'logs' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only(['action', 'user', 'from', 'to']),
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
