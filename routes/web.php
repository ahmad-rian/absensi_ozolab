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
use App\Http\Controllers\Admin\GenerateKartuMassalController;
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
use App\Http\Controllers\Admin\SiswaTanpaFotoController;
use App\Http\Controllers\Admin\StudentDrivePickerController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\StudentQuickOpenController;
use App\Http\Controllers\Admin\StudentRegenerateController;
use App\Http\Controllers\Admin\StudentReportController;
use App\Http\Controllers\Admin\StudioTokenController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WaConfigController;
use App\Http\Controllers\Auth\ChangeRequiredPasswordController;
use App\Http\Controllers\BerkasKeluaranController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\General\DashboardController as GeneralDashboardController;
use App\Http\Controllers\General\KatalogController as GeneralKatalogController;
use App\Http\Controllers\General\PenyelenggaraController;
use App\Http\Controllers\General\SettingsController as GeneralSettingsController;
use App\Http\Controllers\General\UserController as GeneralUserController;
use App\Http\Controllers\KartuBebas\AttendanceController as CardAttendanceController;
use App\Http\Controllers\KartuBebas\DashboardController as KartuBebasDashboardController;
use App\Http\Controllers\KartuBebas\DatasetController;
use App\Http\Controllers\KartuBebas\DrivePhotoController;
use App\Http\Controllers\KartuBebas\FrameController as KartuBebasFrameController;
use App\Http\Controllers\KartuBebas\GenerateController;
use App\Http\Controllers\KartuBebas\LayoutController;
use App\Http\Controllers\KartuBebas\ParticipantController;
use App\Http\Controllers\LibraryScannerController;
use App\Http\Controllers\OrangTua\PortalController;
use App\Http\Controllers\ParentTelegramController;
use App\Http\Controllers\Penyelenggara\DashboardController as PenyelenggaraDashboardController;
use App\Http\Controllers\Penyelenggara\GrupController as PenyelenggaraGrupController;
use App\Http\Controllers\Penyelenggara\JadwalController as PenyelenggaraJadwalController;
use App\Http\Controllers\Penyelenggara\KartuController as PenyelenggaraKartuController;
use App\Http\Controllers\Penyelenggara\KategoriController as PenyelenggaraKategoriController;
use App\Http\Controllers\Penyelenggara\KehadiranController as PenyelenggaraKehadiranController;
use App\Http\Controllers\Penyelenggara\LaporanProgramController as PenyelenggaraLaporanController;
use App\Http\Controllers\Penyelenggara\PesertaController as PenyelenggaraPesertaController;
use App\Http\Controllers\Penyelenggara\PesertaImporController as PenyelenggaraPesertaImporController;
use App\Http\Controllers\Penyelenggara\PesertaTanpaFotoController as PenyelenggaraPesertaTanpaFotoController;
use App\Http\Controllers\Penyelenggara\ProgramController as PenyelenggaraProgramController;
use App\Http\Controllers\Penyelenggara\ProgramDashboardController as PenyelenggaraProgramDashboardController;
use App\Http\Controllers\Penyelenggara\SwitchController as PenyelenggaraSwitchController;
use App\Http\Controllers\Penyelenggara\TimController as PenyelenggaraTimController;
use App\Http\Controllers\PrayerScannerController;
use App\Http\Controllers\Public\CardFormController;
use App\Http\Controllers\PublicProgramScanController;
use App\Http\Controllers\PublicScannerController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StudentRegistrationController;
use App\Http\Controllers\UndanganController;
use App\Http\Middleware\EnsureAnakSendiri;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// FAQ datang dari config/seo.php supaya isi yang dibaca pengunjung dan isi yang
// dibaca mesin (skema FAQPage) tidak pernah menyimpang.
Route::get('/', fn () => auth()->check() ? to_route(auth()->user()->homeRoute()) : Inertia\Inertia::render('welcome', ['faqs' => config('seo.faq')]))->name('home');

// Berkas yang dibaca mesin. robots.txt tetap berkas statis di public/.
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('llms.txt', [SeoController::class, 'llms'])->name('seo.llms');
Route::get('scan-peserta/{token}', [CardAttendanceController::class, 'scanner'])->name('public.card-scanner');
Route::post('scan-peserta/{token}/{mode}', [CardAttendanceController::class, 'scan'])->whereIn('mode', ['masuk', 'pulang'])->middleware('throttle:scan-gerbang')->name('public.card-scanner.scan');

Route::get('scan/{school:scanner_token}', [PublicScannerController::class, 'index'])->name('public.scanner');

/*
 | Rute perangkat gerbang: tanpa session, tanpa CSRF, tanpa Inertia.
 |
 | Yang membukanya box Android TV berspesifikasi rendah yang menyala sepanjang
 | hari. Dulu tiap buka halaman DAN tiap kartu ditempel menulis satu baris
 | session — dipicu semata oleh `csrf_token()` di dalam Blade-nya, karena
 | `StartSession` selalu menyimpan tanpa memeriksa apakah ada yang berubah.
 |
 | CSRF dilepas karena di sini ia tidak menjaga apa pun: ia melindungi sesi
 | login, sedangkan gerbang tidak punya sesi. Penjaga sebenarnya `scanner_token`
 | di URL plus `qr_token` kartu — dan penyerang yang memegang keduanya bisa
 | memanggil endpoint ini lewat curl tanpa terhalang CSRF sama sekali.
 |
 | `HandleInertiaRequests` ikut dilepas karena ia membaca session (`flash`) dan
 | akan melempar tanpa session; ia juga menempelkan `Vary: X-Inertia` yang cuma
 | memecah cache di depan Cloudflare untuk halaman yang bukan Inertia.
 |
 | Sengaja HANYA empat rute ini. Scan React, sholat, dan perpustakaan tetap
 | bersession — masing-masing punya pertimbangan sendiri, dan ada tes yang
 | menjaga pengecualian ini tidak melebar diam-diam.
 */
$tanpaSession = [
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
    HandleInertiaRequests::class,
    AddLinkHeadersForPreloadedAssets::class,
];

Route::post('scan/{school:scanner_token}', [PublicScannerController::class, 'scan'])
    ->middleware('throttle:scan-gerbang')
    ->withoutMiddleware($tanpaSession)
    ->name('public.scanner.scan');

// Versi ringan untuk perangkat gerbang berspesifikasi rendah (box Android TV).
// Blade polos tanpa React/Inertia/Tailwind: app.css memakai oklch() yang baru
// dikenal Chrome 111+, sedangkan box semacam itu umumnya masih Chrome 80-100 dan
// gagal mem-parse seluruh variabel warnanya. POST-nya tetap ke public.scanner.scan
// supaya logika absensinya tidak punya salinan kedua.
Route::get('scan/{school:scanner_token}/ringan', [PublicScannerController::class, 'light'])
    ->withoutMiddleware($tanpaSession)
    ->name('public.scanner.light');

// Alamat pendek menuju halaman ringan di atas. Alamatnya diketik dengan remote
// TV, jadi 40 karakter scanner_token tidak masuk akal. Throttle-nya rapat karena
// rute ini satu-satunya yang mengubah tebakan pendek jadi token penuh.
Route::get('g/{kode}', [PublicScannerController::class, 'shortLink'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware($tanpaSession)
    ->name('public.scanner.short');

Route::post('g/{kode}', [PublicScannerController::class, 'shortScan'])
    ->middleware('throttle:scan-gerbang')
    ->withoutMiddleware($tanpaSession)
    ->name('public.scanner.short.scan');

// Halaman scan ringan peserta (haji/umrah) — sama dengan /g/ tapi per layout.
// Satu link: scan pertama hari itu masuk, scan berikutnya pulang.
Route::get('p/{kode}', [CardAttendanceController::class, 'ringan'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware($tanpaSession)
    ->name('public.card-scanner.short');

Route::post('p/{kode}', [CardAttendanceController::class, 'scanRingan'])
    ->middleware('throttle:scan-gerbang')
    ->withoutMiddleware($tanpaSession)
    ->name('public.card-scanner.short.scan');

// Scan program penyelenggara (spec 004) — terpisah total dari /p dan
// /scan-peserta milik Kartu Bebas/haji. Kamera bersession (halaman React);
// endpoint JSON & halaman ringan tanpa session, sama dengan /p.
Route::get('hadir/{kode}', [PublicProgramScanController::class, 'halaman'])
    ->middleware('throttle:30,1')
    ->name('public.program-scan');
Route::post('hadir/{kode}', [PublicProgramScanController::class, 'scan'])
    ->middleware('throttle:scan-gerbang')
    ->withoutMiddleware($tanpaSession)
    ->name('public.program-scan.scan');
Route::get('h/{kode}', [PublicProgramScanController::class, 'ringan'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware($tanpaSession)
    ->name('public.program-scan.ringan');
Route::post('h/{kode}', [PublicProgramScanController::class, 'scanRingan'])
    ->middleware('throttle:scan-gerbang')
    ->withoutMiddleware($tanpaSession)
    ->name('public.program-scan.ringan.scan');

// Absen sholat — URL tersendiri untuk sekolah yang memisahkan perangkat mushola
// dari gerbang. Sejak gerbang utama ikut mencatat sholat (GerbangRecorder), ini
// bukan lagi satu-satunya jalan, tapi tautannya sudah tersebar dan tetap hidup.
//
// Batas lajunya disamakan dengan gerbang utama: `throttle:120,1` bawaan dihitung
// per ALAMAT IP, dan satu gedung berbagi satu IP publik — saat jamaah bubar dan
// puluhan kartu ditempel berbarengan, batas itu tersentuh dan scan ditolak karena
// alasan yang tidak ada hubungannya dengan absensi.
Route::get('scan/{school:scanner_token}/sholat', [PrayerScannerController::class, 'index'])->name('public.prayer-scanner');
Route::post('scan/{school:scanner_token}/sholat', [PrayerScannerController::class, 'scan'])->middleware('throttle:scan-gerbang')->name('public.prayer-scanner.scan');

// Kunjungan perpustakaan — URL terpisah, alasan yang sama seperti sholat: tablet
// di perpustakaan tidak boleh bisa salah mode.
Route::get('scan/{school:scanner_token}/perpustakaan', [LibraryScannerController::class, 'index'])->name('public.library-scanner');
Route::post('scan/{school:scanner_token}/perpustakaan', [LibraryScannerController::class, 'scan'])->middleware('throttle:scan-gerbang')->name('public.library-scanner.scan');

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

// Kartu & lembar pas foto (disk atau Google Drive) lewat URL bertanda tangan.
Route::get('berkas/{log}', BerkasKeluaranController::class)->middleware(['signed', 'throttle:120,1'])->name('berkas-keluaran');
Route::post('daftar-telegram', [ParentTelegramController::class, 'store'])->middleware('throttle:10,1')->name('parent.telegram.store');

// Public dynamic card form (encrypted link)
Route::get('f/{token}', [CardFormController::class, 'show'])->name('public.card-forms.show');
Route::post('f/{token}', [CardFormController::class, 'submit'])->middleware('throttle:10,1')->name('public.card-forms.submit');
Route::get('f/{token}/status/{submission}', [CardFormController::class, 'status'])->middleware('throttle:120,1')->name('public.card-forms.status');

// Berpindah tenant hanya untuk SUPER_ADMIN — role lain terkunci di sekolahnya.
Route::middleware(['auth', 'bukan-penyelenggara'])->post('admin/switch-school', function (Request $request) {
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
// `bukan-penyelenggara`: akun penyelenggara (school_id null) akan lolos scope
// sekolah yang fail-open — ditolak di pintu grup ini (spec 001).
Route::middleware(['auth', 'verified', 'bukan-penyelenggara'])->prefix('admin')->group(function () {
    // Panduan sengaja tanpa `permission:` — ia bukan modul, dan setiap pengguna
    // yang bisa masuk berhak tahu cara memakai bagian yang boleh dia buka.
    // Penyaringan isinya terjadi di klien memakai permission dan fitur yang
    // sudah dibagikan lewat shared props.
    Route::get('panduan', [PanduanController::class, 'index'])->name('admin.panduan');

    Route::middleware('permission:beranda.access')
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

        // Penjelajah folder Drive: admin melihat isi foldernya lalu memilih
        // sendiri berkasnya, untuk kasus yang tidak tertangkap pencocokan
        // otomatis. Ketiganya memeriksa `isInsideSchoolRoot()` — satu akun
        // OAuth melayani semua sekolah, jadi id yang datang dari klien tidak
        // pernah dipercaya begitu saja.
        Route::get('siswa/{siswa}/drive/jelajah', [StudentDrivePickerController::class, 'browse'])->name('admin.siswa.drive.browse');
        Route::get('siswa/{siswa}/drive/thumb/{fileId}', [StudentDrivePickerController::class, 'thumbnail'])->name('admin.siswa.drive.thumb');
        Route::post('siswa/{siswa}/foto/drive', [StudentDrivePickerController::class, 'use'])->name('admin.siswa.foto.drive');

        // Generate ulang per keluaran. Dipisah karena merender kartu memanggil
        // headless Chrome dan mengambil foto memukul Drive — memperbaiki satu
        // berkas tidak boleh menjalankan keempatnya.
        Route::post('siswa/{siswa}/regenerate/kartu', [StudentRegenerateController::class, 'cards'])->name('admin.siswa.regenerate.cards');
        Route::post('siswa/{siswa}/regenerate/pas-foto', [StudentRegenerateController::class, 'photoSheet'])->name('admin.siswa.regenerate.photo-sheet');
        Route::post('siswa/{siswa}/regenerate/foto', [StudentRegenerateController::class, 'photo'])->name('admin.siswa.regenerate.photo');
        Route::get('siswa/{siswa}/laporan/semuanya/pdf', [StudentReportController::class, 'combinedPdf'])->name('admin.siswa.laporan.semuanya.pdf');
        Route::get('siswa/{siswa}/laporan/absensi/xlsx', [StudentReportController::class, 'attendanceXlsx'])->name('admin.siswa.laporan.absensi.xlsx');
        Route::get('siswa/{siswa}/laporan/absensi/pdf', [StudentReportController::class, 'attendancePdf'])->name('admin.siswa.laporan.absensi.pdf');
        Route::get('siswa/{siswa}/laporan/sholat/xlsx', [StudentReportController::class, 'prayerXlsx'])->name('admin.siswa.laporan.sholat.xlsx');
        Route::get('siswa/{siswa}/laporan/sholat/pdf', [StudentReportController::class, 'prayerPdf'])->name('admin.siswa.laporan.sholat.pdf');

        // Siswa aktif yang belum punya pas foto, menggantikan daftar yang dulu
        // hanya ada di layar Generate Kartu massal (khusus super admin).
        Route::get('siswa-tanpa-foto', [SiswaTanpaFotoController::class, 'index'])->name('admin.siswa-tanpa-foto');
        Route::post('siswa-tanpa-foto/{siswa}/foto', [SiswaTanpaFotoController::class, 'foto'])->name('admin.siswa-tanpa-foto.foto');
    });

    // Modul grup Sistem: lintas tenant, jadi `super-admin` wajib ikut — permission
    // saja tidak cukup karena global scope tidak menjaga apa pun di sini.
    Route::middleware(['permission:semua-sekolah.access', 'super-admin'])->group(function () {
        Route::get('semua-sekolah', [SemuaSekolahController::class, 'index'])->name('admin.semua-sekolah');
    });

    Route::middleware(['permission:kunjungan-perpus.access', 'feature:kunjungan_perpustakaan'])->group(function () {
        Route::get('kunjungan-perpus', [KunjunganPerpusController::class, 'index'])->name('admin.kunjungan-perpus');
    });

    Route::middleware(['permission:kartu-rfid.access', 'feature:absensi_rfid'])->group(function () {
        Route::get('rfid-cards', [RfidCardController::class, 'index'])->name('admin.rfid-cards');
        Route::post('rfid-cards/{siswa}', [RfidCardController::class, 'store'])->name('admin.rfid-cards.store');
        Route::delete('rfid-cards/{siswa}', [RfidCardController::class, 'destroy'])->name('admin.rfid-cards.destroy');
    });

    Route::middleware(['permission:orang-tua.access', 'feature:master_siswa'])->group(function () {
        // Wajib sebelum resource: `orang-tua/{parentProfile}` akan menelan
        // `orang-tua/export-pdf` dan menjadikannya pencarian id.
        Route::get('orang-tua/export-pdf', [OrangTuaController::class, 'exportPdf'])->name('admin.orang-tua.export-pdf');
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
        Route::put('jadwal-absensi/sholat', [AttendanceScheduleController::class, 'updatePrayer'])->name('admin.jadwal-absensi.sholat');
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
    Route::middleware(['permission:bingkai.access', 'feature:kartu_album'])->group(function () {
        Route::get('frames', [FrameController::class, 'index'])->name('admin.frames');
        Route::post('frames', [FrameController::class, 'store'])->name('admin.frames.store');
        Route::put('frames/{frame}', [FrameController::class, 'update'])->name('admin.frames.update');
        Route::delete('frames/{frame}', [FrameController::class, 'destroy'])->name('admin.frames.destroy');
    });

    Route::middleware(['permission:layout-kartu.access', 'feature:kartu_album'])->group(function () {
        Route::get('card-layouts', [CardLayoutController::class, 'index'])->name('admin.card-layouts');
        Route::get('card-layouts/create', [CardLayoutController::class, 'create'])->name('admin.card-layouts.create');
        Route::post('card-layouts', [CardLayoutController::class, 'store'])->name('admin.card-layouts.store');
        Route::get('card-layouts/{cardLayout}/edit', [CardLayoutController::class, 'edit'])->name('admin.card-layouts.edit');
        Route::put('card-layouts/{cardLayout}', [CardLayoutController::class, 'update'])->name('admin.card-layouts.update');
        Route::delete('card-layouts/{cardLayout}', [CardLayoutController::class, 'destroy'])->name('admin.card-layouts.destroy');
    });

    Route::middleware(['permission:generate-kartu.access', 'feature:kartu_album'])->group(function () {
        Route::get('card-generation', [CardGenerationController::class, 'index'])->name('admin.card-generation');
        Route::post('card-generation/generate', [CardGenerationController::class, 'generate'])->middleware('generate-massal')->name('admin.card-generation.generate');
    });

    // Generate massal per sekolah/kelas. `super-admin` wajib ikut: layar ini
    // memilih sekolahnya dari daftar dan query-nya memakai `acrossSchools()`,
    // jadi global scope tenant tidak menjaga apa pun di sini — sama alasannya
    // dengan grup Sistem.
    Route::middleware(['permission:generate-kartu.access', 'feature:kartu_album', 'super-admin'])->group(function () {
        Route::get('generate-kartu', [GenerateKartuMassalController::class, 'index'])->middleware('generate-massal')->name('admin.generate-kartu');
        Route::post('generate-kartu', [GenerateKartuMassalController::class, 'generate'])->middleware('generate-massal')->name('admin.generate-kartu.jalan');
        Route::get('generate-kartu/{batch}/progres', [GenerateKartuMassalController::class, 'progres'])
            ->middleware('throttle:120,1')
            ->name('admin.generate-kartu.progres');

        // Unggah pas foto tanpa meninggalkan layar ini. Rute siswa yang biasa
        // 404 di sini: bindingnya tersaring global scope tenant, sedangkan
        // sekolah yang dibuka layar ini datang dari query string, bukan sesi.
        Route::post('generate-kartu/siswa/{siswa}/foto', [GenerateKartuMassalController::class, 'unggahFoto'])
            ->name('admin.generate-kartu.foto');
    });

    Route::middleware(['permission:layout-album.access', 'feature:kartu_album'])->group(function () {
        Route::get('album-layouts', [AlbumLayoutController::class, 'index'])->name('admin.album-layouts');
        Route::post('album-layouts', [AlbumLayoutController::class, 'store'])->name('admin.album-layouts.store');
        Route::put('album-layouts/{albumLayout}', [AlbumLayoutController::class, 'update'])->name('admin.album-layouts.update');
        Route::delete('album-layouts/{albumLayout}', [AlbumLayoutController::class, 'destroy'])->name('admin.album-layouts.destroy');
    });

    Route::middleware(['permission:generate-album.access', 'feature:kartu_album'])->group(function () {
        Route::get('album-generation', [AlbumGenerationController::class, 'index'])->name('admin.album-generation');
        Route::get('album-generation/download', [AlbumGenerationController::class, 'generate'])->name('admin.album-generation.generate');
    });

    Route::middleware(['permission:pas-foto.access', 'feature:kartu_album'])->group(function () {
        Route::get('pas-foto', [PhotoSheetBatchController::class, 'index'])->middleware('generate-massal')->name('admin.photo-sheets');
        Route::post('pas-foto', [PhotoSheetBatchController::class, 'store'])->middleware('generate-massal')->name('admin.photo-sheets.store');
        Route::get('pas-foto/{batch}/berkas', [PhotoSheetBatchController::class, 'download'])->name('admin.photo-sheets.download');
    });

    // Administrasi
    Route::middleware('permission:pengaturan.access')->group(function () {
        Route::get('pengaturan', [PengaturanController::class, 'index'])->name('admin.pengaturan');
        Route::put('pengaturan', [PengaturanController::class, 'update'])->name('admin.pengaturan.update');
        Route::post('pengaturan/upload-logo', [PengaturanController::class, 'uploadLogo'])->name('admin.pengaturan.upload-logo');
        Route::post('pengaturan/upload-favicon', [PengaturanController::class, 'uploadFavicon'])->name('admin.pengaturan.upload-favicon');
        Route::post('pengaturan/branding-publik', [PengaturanController::class, 'publishBranding'])->name('admin.pengaturan.branding-publik');
    });

    Route::middleware(['permission:pengguna.access', 'feature:manajemen_pengguna'])->group(function () {
        Route::resource('users', UserManagementController::class)->except(['show'])->names('admin.users');
    });

    Route::middleware(['permission:google-drive.access', 'feature:integrasi_drive'])->group(function () {
        Route::get('drive-config', [DriveConfigController::class, 'index'])->name('admin.drive-config');
        Route::post('drive-config', [DriveConfigController::class, 'update'])->name('admin.drive-config.update');
        Route::post('drive-config/test', [DriveConfigController::class, 'test'])->name('admin.drive-config.test');
        Route::get('drive-config/callback', [DriveConfigController::class, 'oauthCallback'])->name('admin.drive-config.callback');
    });

    Route::middleware(['permission:whatsapp.access', 'feature:integrasi_whatsapp'])
        ->get('wa-config', [WaConfigController::class, 'index'])->name('admin.wa-config');

    // Sistem — modul yang menyentuh data lintas sekolah. Permission saja
    // tidak cukup di sini: School, Role, dan SchoolNotificationChannel tidak
    // ber-tenant, jadi global scope tidak melindungi apa pun.
    Route::middleware(['permission:masuk-sebagai.access', 'super-admin'])->group(function () {
        Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])->name('admin.users.impersonate');
    });

    // Kredensial Tyas Studio. `super-admin` wajib: token boleh diterbitkan
    // lintas sekolah, dan `StudioToken` sengaja tidak ber-tenant supaya
    // pencariannya tetap bekerja saat tidak ada user yang login.
    Route::middleware(['permission:sekolah.access', 'super-admin'])->group(function () {
        Route::get('studio-tokens', [StudioTokenController::class, 'index'])->name('admin.studio-tokens');
        Route::post('studio-tokens', [StudioTokenController::class, 'store'])->name('admin.studio-tokens.store');
        Route::delete('studio-tokens/{studioToken}', [StudioTokenController::class, 'destroy'])->name('admin.studio-tokens.destroy');
    });

    Route::middleware(['permission:gateway-notifikasi.access', 'super-admin'])->group(function () {
        Route::get('notification-gateways', [NotificationGatewayController::class, 'index'])->name('admin.notification-gateways');
        Route::put('notification-gateways/{school}', [NotificationGatewayController::class, 'update'])->name('admin.notification-gateways.update');
        Route::delete('notification-gateways/{school}', [NotificationGatewayController::class, 'destroy'])->name('admin.notification-gateways.destroy');
        Route::post('notification-gateways/{school}/test', [NotificationGatewayController::class, 'test'])->name('admin.notification-gateways.test');
    });

    Route::middleware(['permission:sekolah.access', 'super-admin'])->group(function () {
        Route::resource('schools', SchoolController::class)->except(['show'])->names('admin.schools');
        Route::post('schools/{school}/scanner-token', [SchoolController::class, 'regenerateScannerToken'])->name('admin.schools.regenerate-scanner');
    });

    Route::middleware(['permission:hak-akses.access', 'super-admin'])->group(function () {
        Route::get('roles', [RolePermissionController::class, 'index'])->name('admin.roles');
        Route::post('roles', [RolePermissionController::class, 'store'])->name('admin.roles.store');
        Route::put('roles/{role}', [RolePermissionController::class, 'update'])->name('admin.roles.update');
        Route::delete('roles/{role}', [RolePermissionController::class, 'destroy'])->name('admin.roles.destroy');
    });

    // Kartu Bebas / Haji — dynamic card form builder
    Route::middleware(['permission:form-kartu.access', 'super-admin', 'kartu-bebas-legacy'])->group(function () {
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

Route::middleware(['auth', 'verified', 'permission:kartu-bebas.access', 'super-admin', 'kartu-bebas-legacy'])->prefix('kartu-bebas')->name('kartu-bebas.')->group(function () {
    Route::get('/', [KartuBebasDashboardController::class, 'index'])->name('dashboard');

    Route::get('drive/jelajah', [DrivePhotoController::class, 'browse'])->name('drive.browse');
    Route::get('drive/thumb/{fileId}', [DrivePhotoController::class, 'thumbnail'])->name('drive.thumbnail');
    Route::get('drive/image/{fileId}', [DrivePhotoController::class, 'image'])->name('drive.image');

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
    Route::get('absensi', [CardAttendanceController::class, 'index'])->name('absensi.index');
    Route::post('absensi/{submission}', [CardAttendanceController::class, 'store'])->name('absensi.store');
    Route::post('layouts/{cardForm}/scanner-token', [CardAttendanceController::class, 'rotate'])->name('absensi.rotate');
    Route::post('layouts/{cardForm}/kartu-lama', [CardAttendanceController::class, 'terimaKartuLama'])->name('absensi.legacy');
    Route::post('layouts/{cardForm}/generate-ulang', [CardAttendanceController::class, 'regenerate'])->middleware(['throttle:6,1', 'generate-massal'])->name('absensi.regenerate');
    Route::get('layouts/{cardForm}/unduh-kartu', [CardAttendanceController::class, 'downloadCards'])->middleware('throttle:6,1')->name('absensi.download-cards');
    Route::get('laporan', [CardAttendanceController::class, 'report'])->name('laporan.index');
    Route::get('laporan/export/{format}', [CardAttendanceController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('laporan.export');

    Route::get('peserta', [ParticipantController::class, 'index'])->name('peserta.index');
    Route::get('peserta/create', [ParticipantController::class, 'create'])->name('peserta.create');
    Route::post('peserta', [ParticipantController::class, 'store'])->name('peserta.store');
    Route::get('peserta/{submission}', [ParticipantController::class, 'show'])->name('peserta.show');
    Route::get('peserta/{submission}/edit', [ParticipantController::class, 'edit'])->name('peserta.edit');
    Route::put('peserta/{submission}', [ParticipantController::class, 'update'])->name('peserta.update');
    Route::delete('peserta/{submission}', [ParticipantController::class, 'destroy'])->name('peserta.destroy');
    Route::post('peserta/{submission}/generate', [ParticipantController::class, 'generate'])->middleware('throttle:30,1')->name('peserta.generate');
    Route::get('peserta/{submission}/preview', [ParticipantController::class, 'preview'])->name('peserta.preview');
    Route::get('peserta/{submission}/download', [ParticipantController::class, 'download'])->name('peserta.download');

    Route::delete('data-card/{submission}', [ParticipantController::class, 'destroy'])->name('card.destroy');

    // Frame & Bingkai (category = kartu_bebas)
    Route::get('frames', [KartuBebasFrameController::class, 'index'])->name('frames');
    Route::post('frames', [KartuBebasFrameController::class, 'store'])->name('frames.store');
    Route::put('frames/{frame}', [KartuBebasFrameController::class, 'update'])->name('frames.update');
    Route::delete('frames/{frame}', [KartuBebasFrameController::class, 'destroy'])->name('frames.destroy');

    // Riwayat Kartu
    Route::get('riwayat', [ParticipantController::class, 'index'])->name('riwayat');
});

require __DIR__.'/settings.php';

Route::middleware('auth')->group(function () {
    Route::get('ganti-password', [ChangeRequiredPasswordController::class, 'edit'])->name('password.required.edit');
    Route::put('ganti-password', [ChangeRequiredPasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.required.update');
});

/*
 | Portal orang tua.
 |
 | Anaknya TIDAK lagi jadi segmen rute. Dulu setiap menu berada di bawah
 | `anak/{anak}/…`, jadi orang tua harus membuka anaknya dulu sebelum bisa
 | melihat apa pun — satu klik tambahan di setiap kunjungan, dan bagi mayoritas
 | yang hanya punya satu anak klik itu tidak memilih apa-apa.
 |
 | Sekarang anaknya adalah konteks (`?anak=`), dipilih di sidebar dan diingat
 | lewat tautan antar menu. EnsureAnakSendiri yang membacanya, menetapkan anak
 | pertama bila tidak disebut, dan tetap menolak anak milik orang lain.
 */
// ── Workspace Absensi General (spec 009) ────────────────────────────────────
// Berdiri sendiri, khusus super admin, terpisah dari grup /admin sekolah:
// tidak ada konteks sekolah yang dipakai dan pengaturannya di `general_*`.
Route::middleware(['auth', 'verified', 'super-admin'])
    ->prefix('general')
    ->name('general.')
    ->group(function () {
        Route::get('/', GeneralDashboardController::class)->name('dashboard');

        Route::prefix('penyelenggara')->name('penyelenggara.')->group(function () {
            Route::get('/', [PenyelenggaraController::class, 'index'])->name('index');
            Route::post('/', [PenyelenggaraController::class, 'store'])->name('store');
            Route::get('{penyelenggara}', [PenyelenggaraController::class, 'show'])->name('show');
            Route::put('{penyelenggara}', [PenyelenggaraController::class, 'update'])->name('update');
            Route::post('{penyelenggara}/undangan', [PenyelenggaraController::class, 'invite'])
                ->middleware('throttle:20,1')
                ->name('invite');
        });

        Route::get('kategori', [GeneralKatalogController::class, 'kategori'])->name('kategori');
        Route::get('program', [GeneralKatalogController::class, 'program'])->name('program');

        Route::get('pengguna', [GeneralUserController::class, 'index'])->name('pengguna');
        Route::put('pengguna/{user}/status', [GeneralUserController::class, 'status'])->name('pengguna.status');
        Route::put('pengguna/{user}/sandi', [GeneralUserController::class, 'sandi'])->middleware('throttle:20,1')->name('pengguna.sandi');

        Route::get('pengaturan', [GeneralSettingsController::class, 'index'])->name('pengaturan');
        Route::put('pengaturan/umum', [GeneralSettingsController::class, 'umum'])->name('pengaturan.umum');
        Route::post('pengaturan/logo', [GeneralSettingsController::class, 'logo'])->name('pengaturan.logo');
        Route::put('pengaturan/drive', [GeneralSettingsController::class, 'drive'])->name('pengaturan.drive');
        Route::put('pengaturan/whatsapp', [GeneralSettingsController::class, 'whatsapp'])->name('pengaturan.whatsapp');
        Route::post('pengaturan/whatsapp/uji', [GeneralSettingsController::class, 'ujiWhatsapp'])->middleware('throttle:10,1')->name('pengaturan.whatsapp.uji');
    });

// Alamat lama kelola penyelenggara (sebelum spec 009) — bookmark tetap jalan.
Route::middleware(['auth', 'super-admin'])->get('admin/penyelenggara/{rest?}', fn (?string $rest = null) => redirect('/general/penyelenggara'.($rest ? '/'.$rest : ''), 301))
    ->where('rest', '.*');

// Terima undangan — publik; token SHA-256 di DB, throttle melawan tebakan.
Route::get('undangan/{token}', [UndanganController::class, 'show'])->middleware('throttle:30,1')->name('undangan.show');
Route::post('undangan/{token}', [UndanganController::class, 'accept'])->middleware('throttle:10,1')->name('undangan.accept');

// Area penyelenggara. `penyelenggara` menolak akun sekolah/orang tua dan
// memblokir penyelenggara nonaktif; konteks diisi SetCurrentPenyelenggara.
Route::middleware(['auth', 'verified', 'penyelenggara', 'konteks-penyelenggara'])
    ->prefix('penyelenggara')
    ->name('penyelenggara.')
    ->group(function () {
        Route::get('/', [PenyelenggaraDashboardController::class, 'index'])
            ->middleware('permission:penyelenggara-beranda.access')
            ->name('dashboard');
        Route::post('switch', PenyelenggaraSwitchController::class)->middleware('super-admin')->name('switch');

        Route::middleware('permission:penyelenggara-kategori.access')->prefix('kategori')->name('kategori.')->group(function () {
            Route::get('/', [PenyelenggaraKategoriController::class, 'index'])->name('index');
            Route::post('/', [PenyelenggaraKategoriController::class, 'store'])->name('store');
            Route::get('{kategori}', [PenyelenggaraKategoriController::class, 'edit'])->name('edit');
            Route::put('{kategori}', [PenyelenggaraKategoriController::class, 'update'])->name('update');
            Route::post('{kategori}/arsip', [PenyelenggaraKategoriController::class, 'arsip'])->name('arsip');
        });

        Route::middleware('permission:penyelenggara-program.access')->group(function () {
            Route::get('program', [PenyelenggaraProgramController::class, 'index'])->name('program.index');
            Route::post('program', [PenyelenggaraProgramController::class, 'store'])->name('program.store');
            Route::get('program/{program}', [PenyelenggaraProgramController::class, 'show'])->name('program.show');
            Route::put('program/{program}', [PenyelenggaraProgramController::class, 'update'])->name('program.update');
            Route::post('program/{program}/arsip', [PenyelenggaraProgramController::class, 'arsip'])->name('program.arsip');
            Route::post('program/{program}/salin', [PenyelenggaraProgramController::class, 'salin'])->name('program.salin');
            Route::post('program/{program}/link-scan', [PenyelenggaraKehadiranController::class, 'rotate'])->name('kehadiran.rotate');

            Route::post('program/{program}/grup', [PenyelenggaraGrupController::class, 'store'])->name('grup.store');
            Route::put('grup/{grup}', [PenyelenggaraGrupController::class, 'update'])->name('grup.update');
            Route::delete('grup/{grup}', [PenyelenggaraGrupController::class, 'destroy'])->name('grup.destroy');
            Route::put('grup/{grup}/pendamping', [PenyelenggaraGrupController::class, 'pendamping'])->name('grup.pendamping');

            Route::get('program/{program}/jadwal', [PenyelenggaraJadwalController::class, 'index'])->name('jadwal.index');
            Route::post('program/{program}/jadwal', [PenyelenggaraJadwalController::class, 'store'])->name('jadwal.store');
            Route::put('jadwal/{pola}', [PenyelenggaraJadwalController::class, 'update'])->name('jadwal.update');
            Route::delete('jadwal/{pola}', [PenyelenggaraJadwalController::class, 'destroy'])->name('jadwal.destroy');
            Route::post('program/{program}/libur', [PenyelenggaraJadwalController::class, 'liburStore'])->name('libur.store');
            Route::delete('libur/{libur}', [PenyelenggaraJadwalController::class, 'liburDestroy'])->name('libur.destroy');
        });

        Route::middleware('permission:penyelenggara-peserta.access')->group(function () {
            Route::get('program/{program}/peserta', [PenyelenggaraPesertaController::class, 'index'])->name('peserta.index');
            Route::get('program/{program}/peserta-tanpa-foto', [PenyelenggaraPesertaTanpaFotoController::class, 'index'])->name('peserta.tanpa-foto');

            Route::get('peserta/{peserta}', [PenyelenggaraPesertaController::class, 'edit'])->name('peserta.edit');
            Route::get('peserta/{peserta}/kartu', [PenyelenggaraPesertaController::class, 'kartu'])->name('peserta.kartu');

            Route::get('program/{program}/kehadiran', [PenyelenggaraKehadiranController::class, 'index'])->name('kehadiran.index');
            Route::put('kehadiran/{sesi}/{peserta}', [PenyelenggaraKehadiranController::class, 'update'])->name('kehadiran.update');

            Route::get('program/{program}/dashboard', [PenyelenggaraProgramDashboardController::class, 'show'])->name('program.dashboard');
            Route::get('program/{program}/laporan', [PenyelenggaraLaporanController::class, 'index'])->name('laporan.index');
            Route::get('program/{program}/laporan/unduh', [PenyelenggaraLaporanController::class, 'export'])->middleware('throttle:20,1')->name('laporan.export');
        });

        // Tambah/impor/arsip/kartu: Admin Penyelenggara saja — pendamping hanya
        // melihat dan mencatat kehadiran (audit 2026-10-14).
        Route::middleware('permission:penyelenggara-peserta-kelola.access')->group(function () {
            Route::get('program/{program}/peserta/create', [PenyelenggaraPesertaController::class, 'create'])->name('peserta.create');
            Route::post('program/{program}/peserta', [PenyelenggaraPesertaController::class, 'store'])->name('peserta.store');
            Route::post('program/{program}/impor', [PenyelenggaraPesertaImporController::class, 'pratinjau'])->name('impor.pratinjau');
            Route::post('program/{program}/impor/{kunci}', [PenyelenggaraPesertaImporController::class, 'simpan'])->name('impor.simpan');
            Route::post('peserta/{peserta}', [PenyelenggaraPesertaController::class, 'update'])->name('peserta.update');
            Route::delete('peserta/{peserta}', [PenyelenggaraPesertaController::class, 'destroy'])->name('peserta.destroy');
            Route::post('peserta/{peserta}/pulihkan', [PenyelenggaraPesertaController::class, 'restore'])->name('peserta.restore');
            Route::put('peserta/{peserta}/grup', [PenyelenggaraPesertaController::class, 'grup'])->name('peserta.grup');
            Route::post('peserta/{peserta}/generate', [PenyelenggaraPesertaController::class, 'generate'])->middleware('throttle:30,1')->name('peserta.generate');
            Route::post('peserta/{peserta}/foto', [PenyelenggaraPesertaTanpaFotoController::class, 'foto'])->middleware('throttle:60,1')->name('peserta.foto');
        });

        Route::middleware('permission:penyelenggara-kartu.access')->group(function () {
            Route::get('program/{program}/layout', [PenyelenggaraKartuController::class, 'edit'])->name('kartu.edit');
            Route::put('program/{program}/layout', [PenyelenggaraKartuController::class, 'update'])->name('kartu.update');
            Route::get('program/{program}/kartu.zip', [PenyelenggaraKartuController::class, 'zip'])->name('kartu.zip');
        });

        Route::middleware('permission:penyelenggara-tim.access')->prefix('tim')->name('tim.')->group(function () {
            Route::get('/', [PenyelenggaraTimController::class, 'index'])->name('index');
            Route::post('undangan', [PenyelenggaraTimController::class, 'invite'])->middleware('throttle:20,1')->name('invite');
            Route::delete('undangan/{invitation}', [PenyelenggaraTimController::class, 'revoke'])->name('revoke');
            Route::put('{user}/status', [PenyelenggaraTimController::class, 'status'])->name('status');
            Route::put('{user}/peran', [PenyelenggaraTimController::class, 'peran'])->name('peran');
        });
    });

Route::middleware(['auth', 'permission:portal-orang-tua.access', 'password.ganti', EnsureAnakSendiri::class])
    ->prefix('orangtua')->name('orangtua.')->group(function () {
        Route::get('/', [PortalController::class, 'index'])->name('index');
        Route::get('absensi', [PortalController::class, 'absensi'])->name('absensi');
        Route::get('sholat', [PortalController::class, 'sholat'])->name('sholat');
        Route::get('laporan', [PortalController::class, 'laporan'])->name('laporan');
        Route::get('galeri', [PortalController::class, 'galeri'])->name('galeri');
        Route::get('unduh/{asset}', [PortalController::class, 'download'])->name('download');

        // Bentuk lama yang sudah beredar sebagai tautan; diarahkan, bukan 404.
        Route::get('anak/{anak}/{sisa?}', fn (string $anak) => redirect()->route('orangtua.index', ['anak' => $anak]))
            ->where('sisa', '.*')->name('anak');
    });
