<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman panduan pemakaian aplikasi.
 *
 * Sengaja tanpa permission modul: panduan bukan modul, dan setiap pengguna yang
 * bisa masuk berhak tahu cara memakai bagian yang boleh dia buka. Penyaringan
 * isinya terjadi di klien memakai shared prop `auth.user.permissions` dan
 * `features` yang sudah dibagikan HandleInertiaRequests — sumber yang sama
 * dengan sidebar, jadi panduan tidak pernah menjelaskan menu yang tidak ada.
 */
class PanduanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/panduan/index');
    }
}
