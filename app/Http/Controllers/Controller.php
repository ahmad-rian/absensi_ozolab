<?php

namespace App\Http\Controllers;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

abstract class Controller
{
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
