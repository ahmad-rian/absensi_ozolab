<?php

use App\Http\Controllers\Admin\AbsensiController;
use App\Http\Controllers\Admin\AlbumGenerationController;
use App\Http\Controllers\Admin\AlbumLayoutController;
use App\Http\Controllers\Admin\CardGenerationController;
use App\Http\Controllers\Admin\CardLayoutController;
use App\Http\Controllers\Admin\DriveConfigController;
use App\Http\Controllers\Admin\FrameController;
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
use App\Http\Controllers\StudentRegistrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::get('scan', [PublicScannerController::class, 'index'])->name('public.scanner');
Route::post('scan', [PublicScannerController::class, 'scan'])->name('public.scanner.scan');

Route::get('daftar', [StudentRegistrationController::class, 'index'])->name('student.register');
Route::post('daftar', [StudentRegistrationController::class, 'store'])->name('student.register.store');

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

    // Drive Config
    Route::get('drive-config', [DriveConfigController::class, 'index'])->name('admin.drive-config');
    Route::post('drive-config', [DriveConfigController::class, 'update'])->name('admin.drive-config.update');
    Route::post('drive-config/test', [DriveConfigController::class, 'test'])->name('admin.drive-config.test');

    // Frames
    Route::get('frames', [FrameController::class, 'index'])->name('admin.frames');
    Route::post('frames', [FrameController::class, 'store'])->name('admin.frames.store');
    Route::put('frames/{frame}', [FrameController::class, 'update'])->name('admin.frames.update');
    Route::delete('frames/{frame}', [FrameController::class, 'destroy'])->name('admin.frames.destroy');

    // Card Layouts
    Route::get('card-layouts', [CardLayoutController::class, 'index'])->name('admin.card-layouts');
    Route::get('card-layouts/create', [CardLayoutController::class, 'create'])->name('admin.card-layouts.create');
    Route::post('card-layouts', [CardLayoutController::class, 'store'])->name('admin.card-layouts.store');
    Route::get('card-layouts/{cardLayout}/edit', [CardLayoutController::class, 'edit'])->name('admin.card-layouts.edit');
    Route::put('card-layouts/{cardLayout}', [CardLayoutController::class, 'update'])->name('admin.card-layouts.update');
    Route::delete('card-layouts/{cardLayout}', [CardLayoutController::class, 'destroy'])->name('admin.card-layouts.destroy');

    // Card Generation
    Route::get('card-generation', [CardGenerationController::class, 'index'])->name('admin.card-generation');
    Route::post('card-generation/generate', [CardGenerationController::class, 'generate'])->name('admin.card-generation.generate');
    Route::get('card-generation/preview', [CardGenerationController::class, 'preview'])->name('admin.card-generation.preview');

    // Album Layouts
    Route::get('album-layouts', [AlbumLayoutController::class, 'index'])->name('admin.album-layouts');
    Route::post('album-layouts', [AlbumLayoutController::class, 'store'])->name('admin.album-layouts.store');
    Route::put('album-layouts/{albumLayout}', [AlbumLayoutController::class, 'update'])->name('admin.album-layouts.update');
    Route::delete('album-layouts/{albumLayout}', [AlbumLayoutController::class, 'destroy'])->name('admin.album-layouts.destroy');

    // Album Generation
    Route::get('album-generation', [AlbumGenerationController::class, 'index'])->name('admin.album-generation');
    Route::post('album-generation/generate', [AlbumGenerationController::class, 'generate'])->name('admin.album-generation.generate');
});

require __DIR__.'/settings.php';
