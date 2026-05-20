<?php

use App\Http\Controllers\Admin\AbsensiController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\LaporanController;
use App\Http\Controllers\Admin\NotifikasiController;
use App\Http\Controllers\Admin\OrangTuaController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\ScannerController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SiswaController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicScannerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::get('scan', [PublicScannerController::class, 'index'])->name('public.scanner');
Route::post('scan', [PublicScannerController::class, 'scan'])->name('public.scanner.scan');

Route::middleware(['auth'])->post('admin/switch-school', function (Request $request) {
    $request->validate(['school_id' => ['required', 'exists:schools,id']]);

    $schoolId = (int) $request->school_id;
    session(['current_school_id' => $schoolId]);
    $request->user()->update(['school_id' => $schoolId]);

    // Force full Inertia visit to dashboard (not back()) to ensure all shared props refresh
    return Inertia\Inertia::location(route('dashboard'));
})->name('admin.switch-school');

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('siswa', SiswaController::class)->names('admin.siswa');
    Route::get('siswa/{siswa}/qr', [SiswaController::class, 'qrCode'])->name('admin.siswa.qr');
    Route::resource('orang-tua', OrangTuaController::class)->parameter('orang-tua', 'parentProfile')->names('admin.orang-tua');
    Route::resource('kelas', KelasController::class)->except(['show', 'create', 'edit'])->parameter('kelas', 'classroom');
    Route::get('absensi', [AbsensiController::class, 'index'])->name('admin.absensi');
    Route::post('absensi', [AbsensiController::class, 'store'])->name('admin.absensi.store');
    Route::get('scanner', [ScannerController::class, 'index'])->name('admin.scanner');
    Route::post('scanner/scan', [ScannerController::class, 'scan'])->name('admin.scanner.scan');
    Route::get('laporan/export-pdf', [LaporanController::class, 'exportPdf'])->name('admin.laporan.export-pdf');
    Route::get('laporan/export', [LaporanController::class, 'export'])->name('admin.laporan.export');
    Route::get('laporan', [LaporanController::class, 'index'])->name('admin.laporan');
    Route::get('notifikasi', [NotifikasiController::class, 'index'])->name('admin.notifikasi');
    Route::get('pengaturan', [PengaturanController::class, 'index'])->name('admin.pengaturan');
    Route::put('pengaturan', [PengaturanController::class, 'update'])->name('admin.pengaturan.update');
    Route::post('pengaturan/upload-logo', [PengaturanController::class, 'uploadLogo'])->name('admin.pengaturan.upload-logo');
    Route::post('pengaturan/upload-favicon', [PengaturanController::class, 'uploadFavicon'])->name('admin.pengaturan.upload-favicon');
    Route::resource('users', UserManagementController::class)->except(['show'])->names('admin.users');
    Route::resource('schools', SchoolController::class)->except(['show'])->names('admin.schools');
    Route::get('roles', [RolePermissionController::class, 'index'])->name('admin.roles');
    Route::post('roles', [RolePermissionController::class, 'store'])->name('admin.roles.store');
    Route::put('roles/{role}', [RolePermissionController::class, 'update'])->name('admin.roles.update');
    Route::delete('roles/{role}', [RolePermissionController::class, 'destroy'])->name('admin.roles.destroy');
});

require __DIR__.'/settings.php';
