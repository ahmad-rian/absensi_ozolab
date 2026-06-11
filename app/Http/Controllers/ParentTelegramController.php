<?php

namespace App\Http\Controllers;

use App\Enums\SchoolChannelType;
use App\Models\School;
use App\Models\Student;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParentTelegramController extends Controller
{
    /**
     * Public page where a parent links their Telegram chat_id to a student.
     *
     * Only schools with an active Telegram channel are selectable.
     */
    public function index(): Response
    {
        $schools = School::query()
            ->where('is_active', true)
            ->whereHas('notificationChannels', function ($query) {
                $query->where('channel', SchoolChannelType::Telegram->value)
                    ->where('is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'city']);

        return Inertia::render('parent-telegram', [
            'schools' => $schools,
        ]);
    }

    /**
     * Store the Telegram chat_id on the student's parent profile.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'student_id' => ['required', 'exists:students,id'],
            'whatsapp_number' => ['required', 'string', 'max:20'],
            'telegram_chat_id' => ['required', 'string', 'max:50', 'regex:/^-?\d+$/'],
        ], [
            'school_id.required' => 'Pilih sekolah terlebih dahulu.',
            'school_id.exists' => 'Sekolah tidak ditemukan.',
            'student_id.required' => 'Pilih nama siswa terlebih dahulu.',
            'student_id.exists' => 'Siswa tidak ditemukan.',
            'whatsapp_number.required' => 'Nomor WhatsApp wajib diisi.',
            'telegram_chat_id.required' => 'Chat ID Telegram wajib diisi.',
            'telegram_chat_id.regex' => 'Chat ID Telegram harus berupa angka.',
        ]);

        $school = School::with(['notificationChannels' => function ($query) {
            $query->where('channel', SchoolChannelType::Telegram->value)->where('is_active', true);
        }])->findOrFail($validated['school_id']);

        if ($school->notificationChannels->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Sekolah ini belum mengaktifkan notifikasi Telegram.',
            ], 422);
        }

        $student = Student::with('parentProfile')
            ->where('school_id', $school->id)
            ->find($validated['student_id']);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan di sekolah yang dipilih.',
            ], 422);
        }

        $parent = $student->parentProfile;

        if (! $parent) {
            return response()->json([
                'success' => false,
                'message' => 'Data orang tua untuk siswa ini belum terdaftar. Hubungi admin sekolah.',
            ], 422);
        }

        if (PhoneNumber::normalize($parent->whatsapp_number) !== PhoneNumber::normalize($validated['whatsapp_number'])) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor WhatsApp tidak cocok dengan data orang tua siswa ini.',
            ], 422);
        }

        $parent->update(['telegram_chat_id' => $validated['telegram_chat_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Chat ID Telegram berhasil disimpan. Notifikasi kehadiran akan dikirim ke Telegram Anda.',
            'student' => [
                'full_name' => $student->full_name,
                'classroom' => $student->classroom?->name,
            ],
        ]);
    }
}
