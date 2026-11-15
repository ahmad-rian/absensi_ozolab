<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Menambah tahun ajaran dari Pengaturan tab Umum.
 *
 * Admin hanya mengisi tahun mulai; nama ("2027/2028") dan tanggalnya
 * diturunkan supaya formatnya seragam dengan tahun ajaran yang sudah ada.
 * Menambah tidak mengaktifkan: tahun aktif tetap dipilih lalu disimpan lewat
 * form Pengaturan Umum, karena mengaktifkan mengubah kelas yang tampil di
 * seluruh aplikasi.
 */
class TahunAjaranController extends Controller
{
    private const TAHUN_MIN = 2000;

    private const TAHUN_MAKS = 2100;

    /** Awal dan akhir tahun ajaran, mengikuti kalender sekolah Indonesia. */
    private const AWAL = '07-15';

    private const AKHIR = '06-30';

    public function store(Request $request): RedirectResponse
    {
        $schoolId = $request->user()->school_id;
        abort_unless($schoolId, 422, 'Pilih sekolah dulu sebelum menambah tahun ajaran.');

        $mulai = (int) $request->validate([
            'tahun_mulai' => ['required', 'integer', 'min:'.self::TAHUN_MIN, 'max:'.self::TAHUN_MAKS],
        ], [
            'tahun_mulai.required' => 'Isi tahun mulai, misalnya 2027.',
            'tahun_mulai.integer' => 'Tahun mulai harus berupa angka tahun, misalnya 2027.',
            'tahun_mulai.min' => 'Tahun mulai paling kecil '.self::TAHUN_MIN.'.',
            'tahun_mulai.max' => 'Tahun mulai paling besar '.self::TAHUN_MAKS.'.',
        ])['tahun_mulai'];

        $nama = $mulai.'/'.($mulai + 1);

        if (AcademicYear::forSchool($schoolId)->where('name', $nama)->exists()) {
            throw ValidationException::withMessages([
                'tahun_mulai' => "Tahun ajaran {$nama} sudah ada. Pilih di daftar Tahun Ajaran lalu simpan.",
            ]);
        }

        AcademicYear::create([
            'school_id' => $schoolId,
            'name' => $nama,
            'start_date' => $mulai.'-'.self::AWAL,
            'end_date' => ($mulai + 1).'-'.self::AKHIR,
            'is_active' => false,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Tahun ajaran {$nama} ditambahkan. Pilih di daftar lalu simpan untuk mengaktifkannya.",
        ]);

        return back();
    }
}
