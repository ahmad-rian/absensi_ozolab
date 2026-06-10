<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParentTelegramController extends Controller
{
    /**
     * Public page where a parent links their Telegram chat_id to a student.
     */
    public function index(): Response
    {
        return Inertia::render('parent-telegram');
    }

    /**
     * Store the Telegram chat_id on the student's parent profile.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'whatsapp_number' => ['required', 'string', 'max:20'],
            'telegram_chat_id' => ['required', 'string', 'max:50', 'regex:/^-?\d+$/'],
        ], [
            'student_id.required' => 'Pilih nama siswa terlebih dahulu.',
            'student_id.exists' => 'Siswa tidak ditemukan.',
            'whatsapp_number.required' => 'Nomor WhatsApp wajib diisi.',
            'telegram_chat_id.required' => 'Chat ID Telegram wajib diisi.',
            'telegram_chat_id.regex' => 'Chat ID Telegram harus berupa angka.',
        ]);

        $student = Student::with('parentProfile')->findOrFail($validated['student_id']);
        $parent = $student->parentProfile;

        if (! $parent) {
            return response()->json([
                'success' => false,
                'message' => 'Data orang tua untuk siswa ini belum terdaftar. Hubungi admin sekolah.',
            ], 422);
        }

        if ($this->normalizePhone($parent->whatsapp_number) !== $this->normalizePhone($validated['whatsapp_number'])) {
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

    /**
     * Strip non-digits and normalize a leading 62 country code to 0 for comparison.
     */
    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
