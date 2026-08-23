<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Buka siswa mana pun dari pandangan lintas sekolah, sekali klik.
 *
 * Halaman `semua-sekolah` menampilkan siswa dari semua sekolah, tapi seluruh
 * route siswa disaring global scope `school` yang mengunci super admin ke satu
 * sekolah yang sedang aktif di sesinya. Tautan biasa ke siswa sekolah lain
 * karena itu berakhir 404 — kelas kesalahan yang sama seperti ketika konteks
 * tenant belum ditetapkan sebelum route model binding.
 *
 * Jalan keluarnya bukan menembus scope, melainkan MEMINDAHKAN konteksnya lebih
 * dulu, lalu membuka halaman siswa seperti biasa. Jadi setelah mendarat, super
 * admin benar-benar sedang membuka sekolah itu — bukan mengubah data sekolah
 * yang tidak terlihat di pemilih sekolahnya.
 *
 * Sengaja di luar grup `permission:siswa.access` + `feature:master_siswa`:
 * super admin harus tetap bisa membuka siswa dari sekolah yang modul siswanya
 * sedang dimatikan.
 */
class StudentQuickOpenController extends Controller
{
    public function __invoke(Request $request, string $siswa): RedirectResponse
    {
        $validated = $request->validate([
            'tujuan' => ['required', 'in:show,edit'],
        ], [
            'tujuan.in' => 'Tujuan hanya boleh show atau edit.',
        ]);

        $student = Student::acrossSchools()->with('school')->findOrFail($siswa);

        // SetCurrentSchool menolak sekolah nonaktif dan membuang nilai sesinya,
        // jadi tanpa penjagaan ini redirect-nya mendarat di 404 yang tidak bisa
        // dijelaskan ke operator.
        abort_unless((bool) $student->school?->is_active, 404, 'Sekolah siswa ini tidak aktif.');

        session(['current_school_id' => $student->school_id]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Konteks sekolah dipindah ke {$student->school->name}.",
        ]);

        return to_route("admin.siswa.{$validated['tujuan']}", $student);
    }
}
