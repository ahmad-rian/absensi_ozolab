<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotifikasiController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolId = auth()->user()->school_id;

        $query = NotificationLog::with(['student', 'parentProfile'])
            ->whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $notifications = $query->paginate(20)->withQueryString();

        return Inertia::render('admin/notifikasi/index', [
            'notifications' => $notifications,
            'filters' => [
                'status' => $request->query('status', ''),
                'date_from' => $request->query('date_from', ''),
                'date_to' => $request->query('date_to', ''),
            ],
        ]);
    }
}
