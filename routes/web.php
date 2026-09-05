<?php

use App\Http\Controllers\Admin\AbsensiController;
use App\Http\Controllers\Admin\AlbumGenerationController;
use App\Http\Controllers\Admin\AlbumLayoutController;
use App\Http\Controllers\Admin\AttendanceScheduleController;
use App\Http\Controllers\Admin\CardFormController as AdminCardFormController;
use App\Http\Controllers\Admin\CardGenerationController;
use App\Http\Controllers\Admin\CardLayoutController;
use App\Http\Controllers\Admin\ClassPromotionController;
use App\Http\Controllers\Admin\DriveConfigController;
use App\Http\Controllers\Admin\FrameController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\KunjunganPerpusController;
use App\Http\Controllers\Admin\LaporanController;
use App\Http\Controllers\Admin\NotificationGatewayController;
use App\Http\Controllers\Admin\NotifikasiController;
use App\Http\Controllers\Admin\OrangTuaController;
use App\Http\Controllers\Admin\PanduanController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\Admin\PhotoSheetBatchController;
use App\Http\Controllers\Admin\PhotoSheetController;
use App\Http\Controllers\Admin\RfidCardController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SemuaSekolahController;
use App\Http\Controllers\Admin\SiswaController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\StudentQuickOpenController;
use App\Http\Controllers\Admin\StudentRegenerateController;
use App\Http\Controllers\Admin\StudentReportController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WaConfigController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KartuBebas\DashboardController as KartuBebasDashboardController;
use App\Http\Controllers\KartuBebas\DatasetController;
use App\Http\Controllers\KartuBebas\FrameController as KartuBebasFrameController;
use App\Http\Controllers\KartuBebas\GenerateController;
use App\Http\Controllers\KartuBebas\LayoutController;
use App\Http\Controllers\KartuBebas\RecordController;
use App\Http\Controllers\KartuBebas\RiwayatController;
use App\Http\Controllers\LibraryScannerController;
use App\Http\Controllers\ParentTelegramController;
use App\Http\Controllers\PrayerScannerController;
use App\Http\Controllers\Public\CardFormController;
use App\Http\Controllers\PublicScannerController;
use App\Http\Controllers\StudentRegistrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::get('scan/{school:scanner_token}', [PublicScannerController::class, 'index'])->name('public.scanner');
Route::post('scan/{school:scanner_token}', [PublicScannerController::class, 'scan'])->middleware('throttle:120,1')->name('public.scanner.scan');

// Versi ringan untuk perangkat gerbang berspesifikasi rendah (box Android TV).
// Blade polos tanpa React/Inertia/Tailwind: app.css memakai oklch() yang baru
// dikenal Chrome 111+, sedangkan box semacam itu umumnya masih Chrome 80-100 dan
// gagal mem-parse seluruh variabel warnanya. POST-nya tetap ke public.scanner.scan
// supaya logika absensinya tidak punya salinan kedua.
Route::get('scan/{school:scanner_token}/ringan', [PublicScannerController::class, 'light'])->name('public.scanner.light');

// Alamat pendek menuju halaman ringan di atas. Alamatnya diketik dengan remote
// TV, jadi 40 karakter scanner_token tidak masuk akal. Throttle-nya rapat karena
// rute ini satu-satunya yang mengubah tebakan pendek jadi token penuh.
Route::get('g/{kode}', [PublicScannerController::class, 'shortLink'])->middleware('throttle:30,1')->name('public.scanner.short');
Route::post('g/{kode}', [PublicScannerController::class, 'shortScan'])->middleware('throttle:120,1')->name('public.scanner.short.scan');

// Absen sholat dzuhur — URL terpisah supaya device mushola tidak bisa salah mode.
Route::get('scan/{school:scanner_token}/sholat', [PrayerScannerController::class, 'index'])->name('public.prayer-scanner');
Route::post('scan/{school:scanner_token}/sholat', [PrayerScannerController::class, 'scan'])->middleware('throttle:120,1')->name('public.prayer-scanner.scan');

// Kunjungan perpustakaan — URL terpisah, alasan yang sama seperti sholat: tablet
// di perpustakaan tidak boleh bisa salah mode.
Route::get('scan/{school:scanner_token}/perpustakaan', [LibraryScannerController::class, 'index'])->name('public.library-scanner');
Route::post('scan/{school:scanner_token}/perpustakaan', [LibraryScannerController::class, 'scan'])->middleware('throttle:120,1')->name('public.library-scanner.scan');

Route::get('daftar', [StudentRegistrationController::class, 'index'])->name('student.register');
Route::post('daftar', [StudentRegistrationController::class, 'store'])->middleware('throttle:10,1')->name('student.register.store');
Route::post('daftar/preview-photo', [StudentRegistrationController::class, 'previewPhoto'])->middleware('throttle:20,1')->name('student.register.preview-photo');
Route::post('daftar/crop-preview', [StudentRegistrationController::class, 'cropPreview'])->middleware('throttle:20,1')->name('student.register.crop-preview');
Route::get('daftar/preview/{key}', [StudentRegistrationController::class, 'previewFile'])
    ->middleware('signed')
    ->name('student.register.preview-file');
Route::get('daftar/status/{student}', [StudentRegistrationController::class, 'status'])->middleware('throttle:120,1')->name('student.register.status');
Route::get('daftar/{student}/hasil', [StudentRegistrationController::class, 'result'])->name('student.register.result');

// Form pendek untuk sesi foto sekolah: nama, kelas, nomor absen, nomor foto.
// Rute terpisah, bukan mode di /daftar — operator membagikan tautannya apa adanya
// dan tidak boleh bisa salah membuka versi panjang.
Route::get('quick-regis', [StudentRegistrationController::class, 'quick'])->name('student.register.quick');
Route::post('quick-regis', [StudentRegistrationController::class, 'storeQuick'])->middleware('throttle:10,1')->name('student.register.quick.store');

Route::get('daftar-telegram', [ParentTelegramController::class, 'index'])->name('parent.telegram');
Route::post('daftar-telegram', [ParentTelegramController::class, 'store'])->middleware('throttle:10,1')->name('parent.telegram.store');

// Public dynamic card form (encrypted link)
Route::get('f/{token}', [CardFormController::class, 'show'])->name('public.card-forms.show');
Route::post('f/{token}', [CardFormController::class, 'submit'])->middleware('throttle:10,1')->name('public.card-forms.submit');
Route::get('f/{token}/status/{submission}', [CardFormController::class, 'status'])->middleware('throttle:120,1')->name('public.card-forms.status');

// Berpindah tenant hanya untuk SUPER_ADMIN — role lain terkunci di sekolahnya.
Route::middleware(['auth'])->post('admin/switch-school', function (Request $request) {
    $request->validate(['school_id' => ['required', 'exists:schools,id']]);

    $user = $request->user();

    abort_unless($user->isSuperAdmin(), 403, 'Anda tidak memiliki akses ke sekolah ini.');

    // Hanya ke session. Kolom users.school_id sengaja tidak disentuh: ia milik
    // akun dan dipakai bersama semua perangkat, sedangkan sekolah yang sedang
    // dibuka adalah pilihan per-browser. Lihat SetCurrentSchool.
    session(['current_school_id' => $request->school_id]);

    return Inertia\Inertia::location(route('dashboard'));
})->name('admin.switch-school');

// Buka cepat siswa dari pandangan lintas sekolah: pindah tenant lalu langsung ke
// halamannya. Di luar grup `permission:siswa.access` + `feature:master_siswa`
// dengan sengaja — super admin harus tetap bisa membukanya walau modul siswa
// sedang dimatikan untuk sekolah itu. Juga di luar prefix `admin/semua-sekolah`,
// supaya modul itu tetap benar-benar hanya-baca.
Route::middleware(['auth', 'verified', 'super-admin'])
    ->post('admin/siswa/{siswa}/buka', StudentQuickOpenController::class)
    ->name('admin.siswa.buka');

// Admin routes — akses ditentukan per modul lewat permission `<modul>.access`.
// SUPER_ADMIN lolos semua lewat Gate::before (AppServiceProvider).
Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    // Panduan sengaja tanpa `permission:` — ia bukan modul, dan setiap pengguna
    // yang bisa masuk berhak tahu cara memakai bagian yang boleh dia buka.
    // Penyaringan isinya terjadi di klien memakai permission dan fitur yang
    // sudah dibagikan lewat shared props.
    Route::get('panduan', [PanduanController::class, 'index'])->name('admin.panduan');

    Route::middleware('permission:dashboard.access')
        ->get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware(['permission:siswa.access', 'feature:master_siswa'])->group(function () {
        // Wajib sebelum resource: `siswa/{siswa}` akan menelan `siswa/import`.
        Route::get('siswa/import', [StudentImportController::class, 'index'])->name('admin.siswa.import');
        Route::get('siswa/import/template', [StudentImportController::class, 'template'])->name('admin.siswa.import.template');
        Route::post('siswa/import', [StudentImportController::class, 'upload'])->name('admin.siswa.import.upload');
        Route::get('siswa/import/{key}', [StudentImportController::class, 'review'])->name('admin.siswa.import.review');
        Route::post('siswa/import/{key}/apply', [StudentImportController::class, 'apply'])->name('admin.siswa.import.apply');

        Route::resource('siswa', SiswaController::class)->names('admin.siswa');
        Route::get('siswa/{siswa}/qr', [SiswaController::class, 'qrCode'])->name('admin.siswa.qr');
        Route::post('siswa/{siswa}/photo-sheet', [PhotoSheetController::class, 'generate'])->name('admin.siswa.photo-sheet');
        Route::patch('siswa/{siswa}/prayer-opt-in', [SiswaController::class, 'updatePrayerOptIn'])->name('admin.siswa.prayer-opt-in');
        Route::post('siswa/{siswa}/drive-photo/refresh', [SiswaController::class, 'refreshDrivePhoto'])->name('admin.siswa.drive-photo.refresh');
        Route::post('siswa/{siswa}/foto', [SiswaController::class, 'uploadPhoto'])->name('admin.siswa.foto.upload');

        // Generate ulang per keluaran. Dipisah karena merender kartu memanggil
        // headless Chrome dan mengambil foto memukul Drive — memperbaiki satu
        // berkas tidak boleh menjalankan keempatnya.
        Route::post('siswa/{siswa}/regenerate/kartu', [StudentRegenerateController::class, 'cards'])->name('admin.siswa.regenerate.cards');
        Route::post('siswa/{siswa}/regenerate/pas-foto', [StudentRegenerateController::class, 'photoSheet'])->name('admin.siswa.regenerate.photo-sheet');
        Route::post('siswa/{siswa}/regenerate/foto', [StudentRegenerateController::class, 'photo'])->name('admin.siswa.regenerate.photo');
        Route::get('siswa/{siswa}/laporan/absensi/xlsx', [StudentReportController::class, 'attendanceXlsx'])->name('admin.siswa.laporan.absensi.xlsx');
        Route::get('siswa/{siswa}/laporan/absensi/pdf', [StudentReportController::class, 'attendancePdf'])->name('admin.siswa.laporan.absensi.pdf');
        Route::get('siswa/{siswa}/laporan/sholat/xlsx', [StudentReportController::class, 'prayerXlsx'])->name('admin.siswa.laporan.sholat.xlsx');
        Route::get('siswa/{siswa}/laporan/sholat/pdf', [StudentReportController::class, 'prayerPdf'])->name('admin.siswa.laporan.sholat.pdf');
    });

    // Modul grup Sistem: lintas tenant, jadi `super-admin` wajib ikut — permission
    // saja tidak cukup karena global scope tidak menjaga apa pun di sini.
    Route::middleware(['permission:semua-sekolah.access', 'super-admin'])->group(function () {
        Route::get('semua-sekolah', [SemuaSekolahController::class, 'index'])->name('admin.semua-sekolah');
    });

    Route::middleware(['permission:kunjungan-perpus.access', 'feature:kunjungan_perpustakaan'])->group(function () {
        Route::get('kunjungan-perpus', [KunjunganPerpusController::class, 'index'])->name('admin.kunjungan-perpus');
    });

    Route::middleware(['permission:rfid-cards.access', 'feature:absensi_rfid'])->group(function () {
        Route::get('rfid-cards', [RfidCardController::class, 'index'])->name('admin.rfid-cards');
        Route::post('rfid-cards/{siswa}', [RfidCardController::class, 'store'])->name('admin.rfid-cards.store');
        Route::delete('rfid-cards/{siswa}', [RfidCardController::class, 'destroy'])->name('admin.rfid-cards.destroy');
    });

    Route::middleware(['permission:orang-tua.access', 'feature:master_siswa'])->group(function () {
        Route::resource('orang-tua', OrangTuaController::class)->parameter('orang-tua', 'parentProfile')->names('admin.orang-tua');

        // Pintasan dari halaman detail siswa. Sengaja di grup ini, bukan grup
        // siswa: Guru boleh membuka detail siswa tapi tidak boleh mengubah
        // nomor tujuan notifikasi orang tuanya.
        Route::put('siswa/{siswa}/orang-tua', [OrangTuaController::class, 'updateFromStudent'])->name('admin.siswa.orang-tua.update');
    });

    Route::middleware(['permission:kelas.access', 'feature:master_siswa'])->group(function () {
        // Wajib sebelum resource supaya `kelas/{classroom}` tidak menelannya.
        Route::get('kelas/kenaikan', [ClassPromotionController::class, 'index'])->name('admin.kelas.kenaikan');
        Route::post('kelas/kenaikan', [ClassPromotionController::class, 'upload'])->name('admin.kelas.kenaikan.upload');
        Route::get('kelas/kenaikan/{key}', [ClassPromotionController::class, 'review'])->name('admin.kelas.kenaikan.review');
        Route::post('kelas/kenaikan/{key}/apply', [ClassPromotionController::class, 'apply'])->name('admin.kelas.kenaikan.apply');

        Route::resource('kelas', KelasController::class)->except(['show', 'create', 'edit'])->parameter('kelas', 'classroom');
    });

    Route::middleware(['permission:jadwal-absensi.access', 'feature:absensi_sekolah'])->group(function () {
        Route::resource('jadwal-absensi', AttendanceScheduleController::class)->except(['show', 'create', 'edit'])->parameter('jadwal-absensi', 'attendanceSchedule');
        Route::post('jadwal-absensi/generate-defaults', [AttendanceScheduleController::class, 'generateDefaults'])->name('jadwal-absensi.generate-defaults');
    });

    Route::middleware(['permission:absensi.access', 'feature:absensi_sekolah'])->group(function () {
        Route::get('absensi', [AbsensiController::class, 'index'])->name('admin.absensi');
        Route::post('absensi', [AbsensiController::class, 'store'])->name('admin.absensi.store');
    });

    Route::middleware(['permission:laporan.access', 'feature:laporan'])->group(function () {
        Route::get('laporan/export-pdf', [LaporanController::class, 'exportPdf'])->name('admin.laporan.export-pdf');
        Route::get('laporan/export', [LaporanController::class, 'export'])->name('admin.laporan.export');
        Route::get('laporan', [LaporanController::class, 'index'])->name('admin.laporan');
    });

    Route::middleware(['permission:notifikasi.access', 'feature:inbox_notifikasi'])->group(function () {
        Route::get('notifikasi', [NotifikasiController::class, 'index'])->name('admin.notifikasi');
        Route::post('notifikasi/baca-semua', [NotifikasiController::class, 'markAllRead'])->name('admin.notifikasi.read-all');
        Route::delete('notifikasi/terbaca', [NotifikasiController::class, 'destroyRead'])->name('admin.notifikasi.destroy-read');
        Route::post('notifikasi/{notifikasi}/baca', [NotifikasiController::class, 'markRead'])->name('admin.notifikasi.read');
        Route::delete('notifikasi/{notifikasi}', [NotifikasiController::class, 'destroy'])->name('admin.notifikasi.destroy');
    });

    // Kartu & Album
    Route::middleware(['permission:frames.access', 'feature:kartu_album'])->group(function () {
        Route::get('frames', [FrameController::class, 'index'])->name('admin.frames');
        Route::post('frames', [FrameController::class, 'store'])->name('admin.frames.store');
        Route::put('frames/{frame}', [FrameController::class, 'update'])->name('admin.frames.update');
        Route::delete('frames/{frame}', [FrameController::class, 'destroy'])->name('admin.frames.destroy');
    });

    Route::middleware(['permission:card-layouts.access', 'feature:kartu_album'])->group(function () {
        Route::get('card-layouts', [CardLayoutController::class, 'index'])->name('admin.card-layouts');
        Route::get('card-layouts/create', [CardLayoutController::class, 'create'])->name('admin.card-layouts.create');
        Route::post('card-layouts', [CardLayoutController::class, 'store'])->name('admin.card-layouts.store');
        Route::get('card-layouts/{cardLayout}/edit', [CardLayoutController::class, 'edit'])->name('admin.card-layouts.edit');
        Route::put('card-layouts/{cardLayout}', [CardLayoutController::class, 'update'])->name('admin.card-layouts.update');
        Route::delete('card-layouts/{cardLayout}', [CardLayoutController::class, 'destroy'])->name('admin.card-layouts.destroy');
    });

    Route::middleware(['permission:card-generation.access', 'feature:kartu_album'])->group(function () {
        Route::get('card-generation', [CardGenerationController::class, 'index'])->name('admin.card-generation');
        Route::post('card-generation/generate', [CardGenerationController::class, 'generate'])->name('admin.card-generation.generate');
    });

    Route::middleware(['permission:album-layouts.access', 'feature:kartu_album'])->group(function () {
        Route::get('album-layouts', [AlbumLayoutController::class, 'index'])->name('admin.album-layouts');
        Route::post('album-layouts', [AlbumLayoutController::class, 'store'])->name('admin.album-layouts.store');
        Route::put('album-layouts/{albumLayout}', [AlbumLayoutController::class, 'update'])->name('admin.album-layouts.update');
        Route::delete('album-layouts/{albumLayout}', [AlbumLayoutController::class, 'destroy'])->name('admin.album-layouts.destroy');
    });

    Route::middleware(['permission:album-generation.access', 'feature:kartu_album'])->group(function () {
        Route::get('album-generation', [AlbumGenerationController::class, 'index'])->name('admin.album-generation');
        Route::get('album-generation/download', [AlbumGenerationController::class, 'generate'])->name('admin.album-generation.generate');
    });

    Route::middleware(['permission:photo-sheets.access', 'feature:kartu_album'])->group(function () {
        Route::get('pas-foto', [PhotoSheetBatchController::class, 'index'])->name('admin.photo-sheets');
        Route::post('pas-foto', [PhotoSheetBatchController::class, 'store'])->name('admin.photo-sheets.store');
        Route::get('pas-foto/{batch}/berkas', [PhotoSheetBatchController::class, 'download'])->name('admin.photo-sheets.download');
    });

    // Administrasi
    Route::middleware('permission:pengaturan.access')->group(function () {
        Route::get('pengaturan', [PengaturanController::class, 'index'])->name('admin.pengaturan');
        Route::put('pengaturan', [PengaturanController::class, 'update'])->name('admin.pengaturan.update');
        Route::post('pengaturan/upload-logo', [PengaturanController::class, 'uploadLogo'])->name('admin.pengaturan.upload-logo');
        Route::post('pengaturan/upload-favicon', [PengaturanController::class, 'uploadFavicon'])->name('admin.pengaturan.upload-favicon');
    });

    Route::middleware(['permission:users.access', 'feature:manajemen_pengguna'])->group(function () {
        Route::resource('users', UserManagementController::class)->except(['show'])->names('admin.users');
    });

    Route::middleware(['permission:drive-config.access', 'feature:integrasi_drive'])->group(function () {
        Route::get('drive-config', [DriveConfigController::class, 'index'])->name('admin.drive-config');
        Route::post('drive-config', [DriveConfigController::class, 'update'])->name('admin.drive-config.update');
        Route::post('drive-config/test', [DriveConfigController::class, 'test'])->name('admin.drive-config.test');
        Route::get('drive-config/callback', [DriveConfigController::class, 'oauthCallback'])->name('admin.drive-config.callback');
    });

    Route::middleware(['permission:wa-config.access', 'feature:integrasi_whatsapp'])
        ->get('wa-config', [WaConfigController::class, 'index'])->name('admin.wa-config');

    // Sistem — modul yang menyentuh data lintas sekolah. Permission saja
    // tidak cukup di sini: School, Role, dan SchoolNotificationChannel tidak
    // ber-tenant, jadi global scope tidak melindungi apa pun.
    Route::middleware(['permission:impersonate.access', 'super-admin'])->group(function () {
        Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])->name('admin.users.impersonate');
    });

    Route::middleware(['permission:notification-gateways.access', 'super-admin'])->group(function () {
        Route::get('notification-gateways', [NotificationGatewayController::class, 'index'])->name('admin.notification-gateways');
        Route::put('notification-gateways/{school}', [NotificationGatewayController::class, 'update'])->name('admin.notification-gateways.update');
        Route::delete('notification-gateways/{school}', [NotificationGatewayController::class, 'destroy'])->name('admin.notification-gateways.destroy');
        Route::post('notification-gateways/{school}/test', [NotificationGatewayController::class, 'test'])->name('admin.notification-gateways.test');
    });

    Route::middleware(['permission:schools.access', 'super-admin'])->group(function () {
        Route::resource('schools', SchoolController::class)->except(['show'])->names('admin.schools');
        Route::post('schools/{school}/scanner-token', [SchoolController::class, 'regenerateScannerToken'])->name('admin.schools.regenerate-scanner');
    });

    Route::middleware(['permission:roles.access', 'super-admin'])->group(function () {
        Route::get('roles', [RolePermissionController::class, 'index'])->name('admin.roles');
        Route::post('roles', [RolePermissionController::class, 'store'])->name('admin.roles.store');
        Route::put('roles/{role}', [RolePermissionController::class, 'update'])->name('admin.roles.update');
        Route::delete('roles/{role}', [RolePermissionController::class, 'destroy'])->name('admin.roles.destroy');
    });

    // Kartu Bebas / Haji — dynamic card form builder
    Route::middleware(['permission:card-forms.access', 'super-admin'])->group(function () {
        Route::get('card-forms', [AdminCardFormController::class, 'index'])->name('admin.card-forms');
        Route::get('card-forms/create', [AdminCardFormController::class, 'create'])->name('admin.card-forms.create');
        Route::post('card-forms', [AdminCardFormController::class, 'store'])->name('admin.card-forms.store');
        Route::get('card-forms/{cardForm}/edit', [AdminCardFormController::class, 'edit'])->name('admin.card-forms.edit');
        Route::put('card-forms/{cardForm}', [AdminCardFormController::class, 'update'])->name('admin.card-forms.update');
        Route::delete('card-forms/{cardForm}', [AdminCardFormController::class, 'destroy'])->name('admin.card-forms.destroy');
    });
});

// Keluar dari mode menyamar — hanya butuh login, karena user yang sedang
// disamar tidak punya permission impersonate.
Route::middleware('auth')
    ->post('admin/stop-impersonate', [ImpersonationController::class, 'destroy'])
    ->name('admin.stop-impersonate');

Route::middleware(['auth', 'verified', 'permission:kartu-bebas.access', 'super-admin'])->prefix('kartu-bebas')->name('kartu-bebas.')->group(function () {
    Route::get('/', [KartuBebasDashboardController::class, 'index'])->name('dashboard');

    // Layout Kartu (= CardForm template: dynamic fields + card design)
    Route::get('layouts', [LayoutController::class, 'index'])->name('layouts');
    Route::get('layouts/create', [LayoutController::class, 'create'])->name('layouts.create');
    Route::post('layouts', [LayoutController::class, 'store'])->name('layouts.store');
    Route::get('layouts/{cardForm}/edit', [LayoutController::class, 'edit'])->name('layouts.edit');
    Route::put('layouts/{cardForm}', [LayoutController::class, 'update'])->name('layouts.update');
    Route::delete('layouts/{cardForm}', [LayoutController::class, 'destroy'])->name('layouts.destroy');

    // Data = "Format Data" (reusable dynamic field schema)
    Route::get('data', [DatasetController::class, 'index'])->name('data');
    Route::get('data/create', [DatasetController::class, 'create'])->name('data.create');
    Route::post('data', [DatasetController::class, 'store'])->name('data.store');
    Route::get('data/{dataset}', [DatasetController::class, 'show'])->name('data.show');
    Route::get('data/{dataset}/edit', [DatasetController::class, 'edit'])->name('data.edit');
    Route::put('data/{dataset}', [DatasetController::class, 'update'])->name('data.update');
    Route::delete('data/{dataset}', [DatasetController::class, 'destroy'])->name('data.destroy');

    // Generate = pick a layout, fill data via wizard, produce a card
    Route::get('generate', [GenerateController::class, 'index'])->name('generate');
    Route::get('generate/{cardForm}', [GenerateController::class, 'create'])->name('generate.create');
    Route::post('generate/{cardForm}', [GenerateController::class, 'store'])->middleware('throttle:30,1')->name('generate.store');
    Route::get('generate/status/{submission}', [GenerateController::class, 'status'])->middleware('throttle:120,1')->name('generate.status');

    // Delete a generated card (from Riwayat)
    Route::delete('data-card/{submission}', [RecordController::class, 'destroy'])->name('card.destroy');

    // Frame & Bingkai (category = kartu_bebas)
    Route::get('frames', [KartuBebasFrameController::class, 'index'])->name('frames');
    Route::post('frames', [KartuBebasFrameController::class, 'store'])->name('frames.store');
    Route::put('frames/{frame}', [KartuBebasFrameController::class, 'update'])->name('frames.update');
    Route::delete('frames/{frame}', [KartuBebasFrameController::class, 'destroy'])->name('frames.destroy');

    // Riwayat Kartu
    Route::get('riwayat', [RiwayatController::class, 'index'])->name('riwayat');
});

require __DIR__.'/settings.php';
