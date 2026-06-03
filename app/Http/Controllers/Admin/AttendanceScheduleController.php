<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceScheduleController extends Controller
{
    public function index(): Response
    {
        $schedules = AttendanceSchedule::forSchool()
            ->with('classroom:id,name')
            ->orderBy('day_of_week')
            ->orderByRaw('classroom_id IS NOT NULL')
            ->get();

        $classrooms = Classroom::forSchool()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/jadwal-absensi/index', [
            'schedules' => $schedules,
            'classrooms' => $classrooms,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'check_in_start' => ['required', 'date_format:H:i'],
            'check_in_end' => ['required', 'date_format:H:i'],
            'late_threshold' => ['required', 'date_format:H:i'],
            'check_out_start' => ['required', 'date_format:H:i'],
            'check_out_end' => ['required', 'date_format:H:i'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        AttendanceSchedule::create($validated);

        return back()->with('success', 'Jadwal berhasil ditambahkan.');
    }

    public function update(Request $request, AttendanceSchedule $attendanceSchedule): RedirectResponse
    {
        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'check_in_start' => ['required', 'date_format:H:i'],
            'check_in_end' => ['required', 'date_format:H:i'],
            'late_threshold' => ['required', 'date_format:H:i'],
            'check_out_start' => ['required', 'date_format:H:i'],
            'check_out_end' => ['required', 'date_format:H:i'],
            'is_active' => ['boolean'],
        ]);

        $attendanceSchedule->update($validated);

        return back()->with('success', 'Jadwal berhasil diperbarui.');
    }

    public function destroy(AttendanceSchedule $attendanceSchedule): RedirectResponse
    {
        $attendanceSchedule->delete();

        return back()->with('success', 'Jadwal berhasil dihapus.');
    }
}
