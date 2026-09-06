<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudioToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Jembatan antara `AuthenticateStudioToken` dan controller Studio.
 *
 * Ada supaya penyaringan sekolah punya SATU bentuk yang bisa dicari dengan
 * grep. Global scope `school` mati di jalur bertoken, jadi penyaringan yang
 * terlewat tidak akan menghasilkan galat — ia menghasilkan kebocoran yang diam.
 */
class StudioRequest
{
    public static function token(Request $request): StudioToken
    {
        $token = $request->attributes->get('studio_token');

        if (! $token instanceof StudioToken) {
            // Hanya bisa terjadi kalau ada rute Studio yang lupa dipasangi
            // middleware-nya. Lebih baik meledak keras di sini daripada
            // melayani data lintas sekolah tanpa suara.
            throw new RuntimeException('Rute Studio dipanggil tanpa middleware studio-token.');
        }

        return $token;
    }

    /**
     * Query siswa yang sudah dibatasi ke sekolah milik token.
     *
     * Titik masuk yang dimaksud untuk SEMUA pembacaan siswa di jalur Studio.
     *
     * @return Builder<Student>
     */
    public static function students(Request $request): Builder
    {
        return self::token($request)->batasi(Student::query());
    }

    /**
     * Siswa milik sekolah token ini, atau 404.
     *
     * 404 dan bukan 403: memberi tahu bahwa siswanya ADA tapi milik sekolah
     * lain sudah merupakan kebocoran tersendiri.
     */
    public static function student(Request $request, string $studentId): Student
    {
        return self::students($request)->findOrFail($studentId);
    }
}
