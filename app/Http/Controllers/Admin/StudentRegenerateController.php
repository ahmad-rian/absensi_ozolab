<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RegisterStudentCardsJob;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Menghasilkan ulang satu keluaran milik seorang siswa.
 *
 * Sampai sekarang satu-satunya cara memperbaiki kartu yang salah adalah lewat
 * layar generate massal, yang meminta layout dan daftar siswa — canggung ketika
 * yang bermasalah cuma satu anak yang sedang dibuka.
 *
 * Dipisah per keluaran, bukan satu tombol "generate ulang semuanya": merender
 * kartu memanggil headless Chrome dan mengunduh foto memukul Drive, jadi
 * menjalankan keempatnya untuk memperbaiki satu di antaranya membuang waktu
 * antrean yang dipakai bersama seluruh sekolah.
 */
class StudentRegenerateController extends Controller
{
    /** Kartu OSIS + Perpustakaan, layout yang sama dengan hasil pendaftaran. */
    public function cards(Student $siswa): RedirectResponse
    {
        RegisterStudentCardsJob::dispatch(
            studentId: $siswa->id,
            outputs: [RegisterStudentCardsJob::OUTPUT_CARDS],
            generatedBy: 'admin',
        );

        return $this->queued('Kartu sedang dibuat ulang.');
    }

    /** Lembar pas foto 4R. */
    public function photoSheet(Student $siswa): RedirectResponse
    {
        if (! $siswa->photo_path) {
            return $this->refused('Siswa ini belum punya foto, jadi lembar pas foto tidak bisa dibuat.');
        }

        RegisterStudentCardsJob::dispatch(
            studentId: $siswa->id,
            outputs: [RegisterStudentCardsJob::OUTPUT_SHEET],
            generatedBy: 'admin',
        );

        return $this->queued('Lembar pas foto sedang dibuat ulang.');
    }

    /**
     * Ambil ulang foto dari Drive memakai nama berkas yang tersimpan saat daftar.
     *
     * Nama itu baru disimpan sejak migrasi `add_drive_identifiers_to_students_table`;
     * siswa lama belum punya, dan tidak ada gunanya menebak — katakan saja.
     */
    public function photo(Student $siswa): RedirectResponse
    {
        if (! $siswa->photo_drive_filename) {
            return $this->refused('Nama berkas foto di Drive tidak tersimpan untuk siswa ini, jadi foto tidak bisa diambil ulang.');
        }

        RegisterStudentCardsJob::dispatch(
            studentId: $siswa->id,
            photoFilename: $siswa->photo_drive_filename,
            outputs: [RegisterStudentCardsJob::OUTPUT_PHOTO],
            generatedBy: 'admin',
        );

        return $this->queued('Foto sedang diambil ulang dari Google Drive.');
    }

    private function queued(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message.' Status akan diperbarui otomatis.']);

        return back();
    }

    private function refused(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'warning', 'message' => $message]);

        return back();
    }
}
