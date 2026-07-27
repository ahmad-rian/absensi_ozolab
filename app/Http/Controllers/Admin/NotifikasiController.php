<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotifikasiController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $this->scoped()
            ->with(['student', 'parentProfile'])
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

        if ($request->query('unread') === '1') {
            $query->unread();
        }

        $notifications = $query->paginate(20)->withQueryString();

        return Inertia::render('admin/notifikasi/index', [
            'notifications' => $notifications,
            'unreadCount' => $this->scoped()->unread()->count(),
            'filters' => [
                'status' => $request->query('status', ''),
                'date_from' => $request->query('date_from', ''),
                'date_to' => $request->query('date_to', ''),
                'unread' => $request->query('unread', ''),
            ],
        ]);
    }

    public function markAllRead(): RedirectResponse
    {
        $this->scoped()->unread()->update(['read_at' => now()]);

        return back();
    }

    public function markRead(NotificationLog $notifikasi): RedirectResponse
    {
        $this->authorizeSchool($notifikasi);

        if (! $notifikasi->read_at) {
            $notifikasi->update(['read_at' => now()]);
        }

        return back();
    }

    public function destroy(NotificationLog $notifikasi): RedirectResponse
    {
        $this->authorizeSchool($notifikasi);

        $notifikasi->delete();

        return back();
    }

    public function destroyRead(): RedirectResponse
    {
        $this->scoped()->whereNotNull('read_at')->delete();

        return back();
    }

    /**
     * Selalu lewat kolom school_id, bukan whereHas('student'), supaya aksi
     * tulis tidak bisa menyentuh log sekolah lain.
     *
     * @return Builder<NotificationLog>
     */
    private function scoped()
    {
        return NotificationLog::where('school_id', auth()->user()->school_id);
    }

    private function authorizeSchool(NotificationLog $log): void
    {
        abort_unless($log->school_id === auth()->user()->school_id, 404);
    }
}
