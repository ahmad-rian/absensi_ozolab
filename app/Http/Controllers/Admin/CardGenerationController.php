<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Services\CardGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CardGenerationController extends Controller
{
    public function index(Request $request): Response
    {
        $layouts = SchoolCardLayout::forSchool()
            ->where('is_active', true)
            ->get(['id', 'name', 'type']);

        $classrooms = Classroom::forSchool()
            ->orderBy('name')
            ->get(['id', 'name']);

        $logs = CardGenerationLog::forSchool()
            ->with(['student:id,full_name,nis', 'cardLayout:id,name'])
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (CardGenerationLog $log) => [
                'id' => $log->id,
                'student_name' => $log->student?->full_name ?? '-',
                'student_nis' => $log->student?->nis ?? '-',
                'layout_name' => $log->cardLayout?->name ?? '-',
                'status' => $log->status,
                'file_url' => $log->file_path ? Storage::disk('public')->url($log->file_path) : null,
                'drive_url' => $log->drive_url,
                'generated_by' => $log->generated_by,
                'error_message' => $log->error_message,
                'created_at' => $log->created_at->format('d M Y H:i'),
            ]);

        $school = School::with('driveConfig')->find(auth()->user()->school_id);
        $driveConfig = $school?->driveConfig;

        return Inertia::render('admin/card-generation/index', [
            'layouts' => $layouts,
            'classrooms' => $classrooms,
            'logs' => $logs,
            'driveConfigured' => $driveConfig && $driveConfig->is_active && $driveConfig->service_account_json,
        ]);
    }

    public function preview(Request $request): Response
    {
        $request->validate([
            'layout_id' => ['required', 'exists:school_card_layouts,id'],
            'student_id' => ['required', 'exists:students,id'],
        ]);

        $layout = SchoolCardLayout::forSchool()->findOrFail($request->layout_id);
        $student = Student::forSchool()->with('classroom')->findOrFail($request->student_id);

        $service = new CardGeneratorService;
        $result = $service->generateCard($student, $layout);

        return Inertia::render('admin/card-generation/preview', [
            'html' => $result['html'],
            'imageUrl' => Storage::disk('public')->url($result['path']),
            'student' => [
                'full_name' => $student->full_name,
                'nis' => $student->nis,
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'layout_id' => ['required', 'exists:school_card_layouts,id'],
            'student_ids' => ['nullable', 'array'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
        ]);

        $layout = SchoolCardLayout::forSchool()->findOrFail($validated['layout_id']);

        // Resolve student list — always scoped by school
        $query = Student::forSchool();

        $studentIds = $validated['student_ids'] ?? [];
        $isAll = empty($studentIds) || in_array('all', $studentIds, true);

        if (! $isAll) {
            $query->whereIn('id', $studentIds);
        }

        if (! empty($validated['classroom_id'])) {
            $query->where('classroom_id', $validated['classroom_id']);
        }

        $students = $query->get();

        if ($students->isEmpty()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Tidak ada siswa yang ditemukan.']);

            return to_route('admin.card-generation');
        }

        $service = new CardGeneratorService;
        $successCount = 0;
        $failCount = 0;

        foreach ($students as $student) {
            $log = $service->generateAndLog($student, $layout, 'admin');
            if ($log->status === 'completed') {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $message = "Berhasil generate {$successCount} kartu.";
        if ($failCount > 0) {
            $message .= " {$failCount} gagal.";
        }

        Inertia::flash('toast', [
            'type' => $failCount > 0 ? 'warning' : 'success',
            'message' => $message,
        ]);

        return to_route('admin.card-generation');
    }
}
