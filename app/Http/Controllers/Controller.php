<?php

namespace App\Http\Controllers;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

abstract class Controller
{
    /**
     * Netralkan sel CSV yang diawali karakter rumus.
     *
     * `fputcsv` hanya meng-quote koma dan kutip; Excel tetap mengeksekusi sel
     * yang diawali `=`, `+`, `-`, atau `@`. Nama siswa berasal dari form
     * pendaftaran publik, jadi ini jalur tak-terautentikasi.
     */
    protected function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * Rule `exists` yang dikunci ke sekolah user yang sedang login.
     *
     * `exists:classrooms,id` polos memakai query builder sehingga global scope
     * sekolah tidak berlaku — id milik sekolah lain lolos validasi lalu ikut
     * tersimpan sebagai foreign key.
     */
    protected function belongsToSchool(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where('school_id', auth()->user()?->school_id);
    }
}
