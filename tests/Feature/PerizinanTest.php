<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\PengajuanIzin;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Perizinan siswa: orang tua mengajukan izin/sakit (portal atau form publik
 * /izin), sekolah menyetujui atau menolak; yang disetujui mengisi absensi
 * Izin/Sakit tiap hari sekolah dalam rentangnya.
 */

beforeEach(function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-10-15 08:00', 'Asia/Jakarta'));
});
afterEach(fn () => Carbon::setTestNow());

function sekolahIzin(): array
{
    $admin = createAdminUser();
    $kelas = Classroom::factory()->create(['school_id' => $admin->school_id, 'name' => 'VII A']);
    // Senin–Jumat sekolah, Sabtu–Minggu libur.
    foreach (range(1, 5) as $hari) {
        AttendanceSchedule::factory()->create(['school_id' => $admin->school_id, 'classroom_id' => null, 'day_of_week' => $hari, 'is_active' => true]);
    }

    return [$admin, $kelas];
}

function orangTuaIzin(User $admin, Classroom $kelas, string $nama = 'ABIDZAR MALIQ'): array
{
    $user = User::factory()->create(['school_id' => $admin->school_id]);
    $user->assignRole('ORANG_TUA');
    $parent = ParentProfile::factory()->create(['school_id' => $admin->school_id, 'user_id' => $user->id]);
    $anak = Student::factory()->create(['school_id' => $admin->school_id, 'classroom_id' => $kelas->id, 'parent_profile_id' => $parent->id, 'full_name' => $nama, 'is_active' => true]);

    return [$user, $anak];
}

// ── portal orang tua ───────────────────────────────────────────────────────
test('orang tua mengajukan sakit dari portal dengan bukti yang disimpan sebagai webp', function () {
    [$admin, $kelas] = sekolahIzin();
    [$ortu, $anak] = orangTuaIzin($admin, $kelas);

    $this->actingAs($ortu)->get(route('orangtua.izin', ['anak' => $anak->id]))
        ->assertInertia(fn (Assert $page) => $page->component('orangtua/izin')->has('riwayat', 0));

    $this->actingAs($ortu)->post(route('orangtua.izin.store', ['anak' => $anak->id]), [
        'jenis' => 'SAKIT', 'tanggal_mulai' => '2026-10-15', 'tanggal_selesai' => '2026-10-16',
        'alasan' => 'Demam tinggi', 'bukti' => UploadedFile::fake()->image('surat.jpg', 800, 1000),
    ])->assertSessionHasNoErrors();

    $izin = PengajuanIzin::withoutGlobalScopes()->sole();
    expect($izin->status)->toBe(PengajuanIzin::MENUNGGU)
        ->and($izin->sumber)->toBe('portal')
        ->and($izin->student_id)->toBe($anak->id)
        ->and($izin->bukti_path)->toEndWith('.webp');
    Storage::disk('local')->assertExists($izin->bukti_path);
});

test('orang tua tidak bisa mengajukan izin untuk anak orang lain', function () {
    [$admin, $kelas] = sekolahIzin();
    [$ortu] = orangTuaIzin($admin, $kelas);
    [, $anakLain] = orangTuaIzin($admin, $kelas, 'ANAK LAIN');

    $this->actingAs($ortu)->post(route('orangtua.izin.store', ['anak' => $anakLain->id]), [
        'jenis' => 'IZIN', 'tanggal_mulai' => '2026-10-15', 'tanggal_selesai' => '2026-10-15', 'alasan' => 'Acara keluarga',
    ])->assertForbidden();

    expect(PengajuanIzin::withoutGlobalScopes()->count())->toBe(0);
});

test('tanggal izin tidak boleh terbalik atau terlalu panjang', function () {
    [$admin, $kelas] = sekolahIzin();
    [$ortu, $anak] = orangTuaIzin($admin, $kelas);

    $this->actingAs($ortu)->post(route('orangtua.izin.store', ['anak' => $anak->id]), [
        'jenis' => 'IZIN', 'tanggal_mulai' => '2026-10-16', 'tanggal_selesai' => '2026-10-15', 'alasan' => 'x',
    ])->assertSessionHasErrors('tanggal_selesai');

    $this->actingAs($ortu)->post(route('orangtua.izin.store', ['anak' => $anak->id]), [
        'jenis' => 'IZIN', 'tanggal_mulai' => '2026-10-15', 'tanggal_selesai' => '2026-12-31', 'alasan' => 'x',
    ])->assertSessionHasErrors('tanggal_selesai');
});

// ── form publik /izin ───────────────────────────────────────────────────────
test('form publik: cari siswa wajib lolos captcha server dulu', function () {
    [$admin, $kelas] = sekolahIzin();
    orangTuaIzin($admin, $kelas);

    $this->getJson(route('izin.siswa', ['school_id' => $admin->school_id, 'q' => 'abid']))->assertStatus(403);

    $this->get(route('izin.index'))->assertInertia(fn (Assert $page) => $page->component('public/izin')
        ->where('sekolah', fn ($s) => collect($s)->pluck('id')->contains($admin->school_id))
        ->has('captcha.soal'));

    $jawaban = session('izin_captcha');
    $this->postJson(route('izin.verifikasi'), ['jawaban' => $jawaban + 1])->assertStatus(422);
    $this->get(route('izin.index'));
    $this->postJson(route('izin.verifikasi'), ['jawaban' => session('izin_captcha')])->assertOk();

    $this->getJson(route('izin.siswa', ['school_id' => $admin->school_id, 'q' => 'ab']))->assertStatus(422);
    $this->getJson(route('izin.siswa', ['school_id' => $admin->school_id, 'q' => 'abid']))
        ->assertOk()
        ->assertJsonPath('siswa.0.nama', 'ABIDZAR MALIQ')
        ->assertJsonPath('siswa.0.kelas', 'VII A');
});

test('form publik: pengajuan tersimpan dengan sumber publik dan data pengirim', function () {
    [$admin, $kelas] = sekolahIzin();
    [, $anak] = orangTuaIzin($admin, $kelas);
    $this->get(route('izin.index'));
    $this->postJson(route('izin.verifikasi'), ['jawaban' => session('izin_captcha')]);

    $this->post(route('izin.store'), [
        'school_id' => $admin->school_id, 'student_id' => $anak->id,
        'jenis' => 'IZIN', 'tanggal_mulai' => '2026-10-15', 'tanggal_selesai' => '2026-10-15',
        'alasan' => 'Menghadiri pernikahan saudara', 'pengirim_nama' => 'Ibu Sari', 'pengirim_telepon' => '081234567890',
        'bukti' => UploadedFile::fake()->image('undangan.png', 600, 600),
    ])->assertRedirect(route('izin.terkirim'));

    $izin = PengajuanIzin::withoutGlobalScopes()->sole();
    expect($izin->sumber)->toBe('publik')->and($izin->pengirim_nama)->toBe('Ibu Sari')->and($izin->school_id)->toBe($admin->school_id);
});

test('form publik menolak siswa dari sekolah lain', function () {
    [$admin, $kelas] = sekolahIzin();
    [, $anak] = orangTuaIzin($admin, $kelas);
    $lain = School::factory()->create(['is_active' => true]);
    $this->get(route('izin.index'));
    $this->postJson(route('izin.verifikasi'), ['jawaban' => session('izin_captcha')]);

    $this->post(route('izin.store'), [
        'school_id' => $lain->id, 'student_id' => $anak->id, 'jenis' => 'IZIN',
        'tanggal_mulai' => '2026-10-15', 'tanggal_selesai' => '2026-10-15', 'alasan' => 'Acara keluarga',
        'pengirim_nama' => 'X', 'pengirim_telepon' => '081234567890',
    ])->assertSessionHasErrors('student_id');
});

// ── sekolah: review ─────────────────────────────────────────────────────────
test('sekolah menyetujui izin: absensi Izin/Sakit per hari sekolah, Hadir tidak ditimpa', function () {
    [$admin, $kelas] = sekolahIzin();
    [, $anak] = orangTuaIzin($admin, $kelas);
    // Jumat 16 Okt sudah hadir; Sabtu–Minggu libur; Senin 19 Okt kosong.
    Attendance::create(['school_id' => $admin->school_id, 'student_id' => $anak->id, 'attendance_date' => '2026-10-16', 'type' => AttendanceType::CheckIn, 'status' => AttendanceStatus::Hadir, 'recorded_at' => '2026-10-16 06:40:00']);
    $izin = PengajuanIzin::withoutGlobalScopes()->create([
        'school_id' => $admin->school_id, 'student_id' => $anak->id, 'sumber' => 'portal', 'jenis' => 'SAKIT',
        'tanggal_mulai' => '2026-10-15', 'tanggal_selesai' => '2026-10-19', 'alasan' => 'Tipes', 'status' => PengajuanIzin::MENUNGGU,
    ]);

    $this->actingAs($admin)->get(route('admin.perizinan.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/perizinan/index')->has('izin.data', 1)->where('ringkasan.menunggu', 1));

    $this->actingAs($admin)->post(route('admin.perizinan.setujui', $izin))->assertSessionHasNoErrors();

    $masuk = Attendance::withoutGlobalScopes()->where('student_id', $anak->id)->where('type', AttendanceType::CheckIn)->orderBy('attendance_date')->get();
    expect($masuk->map(fn ($a) => $a->attendance_date->toDateString().':'.$a->status->value)->all())
        ->toBe(['2026-10-15:SAKIT', '2026-10-16:HADIR', '2026-10-19:SAKIT'])
        ->and($izin->fresh()->status)->toBe(PengajuanIzin::DISETUJUI)
        ->and($izin->fresh()->reviewed_by)->toBe($admin->id);

    $this->actingAs($admin)->post(route('admin.perizinan.tolak', $izin), ['catatan' => 'telat'])->assertSessionHasErrors('status');
});

test('izin yang disetujui mengubah Alpa menjadi Izin', function () {
    [$admin, $kelas] = sekolahIzin();
    [, $anak] = orangTuaIzin($admin, $kelas);
    Attendance::create(['school_id' => $admin->school_id, 'student_id' => $anak->id, 'attendance_date' => '2026-10-15', 'type' => AttendanceType::CheckIn, 'status' => AttendanceStatus::Alpa, 'recorded_at' => '2026-10-15 07:00:00']);
    $izin = PengajuanIzin::withoutGlobalScopes()->create([
        'school_id' => $admin->school_id, 'student_id' => $anak->id, 'sumber' => 'publik', 'jenis' => 'IZIN',
        'tanggal_mulai' => '2026-10-15', 'tanggal_selesai' => '2026-10-15', 'alasan' => 'Urusan keluarga', 'status' => PengajuanIzin::MENUNGGU,
    ]);

    $this->actingAs($admin)->post(route('admin.perizinan.setujui', $izin));

    expect(Attendance::withoutGlobalScopes()->where('student_id', $anak->id)->value('status'))->toBe(AttendanceStatus::Izin);
});

test('sekolah menolak izin dengan catatan dan absensi tidak berubah', function () {
    [$admin, $kelas] = sekolahIzin();
    [, $anak] = orangTuaIzin($admin, $kelas);
    $izin = PengajuanIzin::withoutGlobalScopes()->create([
        'school_id' => $admin->school_id, 'student_id' => $anak->id, 'sumber' => 'publik', 'jenis' => 'IZIN',
        'tanggal_mulai' => '2026-10-15', 'tanggal_selesai' => '2026-10-15', 'alasan' => 'Main', 'status' => PengajuanIzin::MENUNGGU,
    ]);

    $this->actingAs($admin)->post(route('admin.perizinan.tolak', $izin), ['catatan' => 'Alasan tidak jelas'])->assertSessionHasNoErrors();

    expect($izin->fresh()->status)->toBe(PengajuanIzin::DITOLAK)
        ->and($izin->fresh()->catatan_review)->toBe('Alasan tidak jelas')
        ->and(Attendance::withoutGlobalScopes()->count())->toBe(0);
});

test('admin sekolah lain tidak bisa melihat atau memproses izin', function () {
    [$admin, $kelas] = sekolahIzin();
    [, $anak] = orangTuaIzin($admin, $kelas);
    $izin = PengajuanIzin::withoutGlobalScopes()->create([
        'school_id' => $admin->school_id, 'student_id' => $anak->id, 'sumber' => 'publik', 'jenis' => 'IZIN',
        'tanggal_mulai' => '2026-10-15', 'tanggal_selesai' => '2026-10-15', 'alasan' => 'x', 'status' => PengajuanIzin::MENUNGGU,
    ]);
    $lain = createAdminUser();

    $this->actingAs($lain)->post(route('admin.perizinan.setujui', $izin))->assertNotFound();
    $this->actingAs($lain)->get(route('admin.perizinan.bukti', $izin))->assertNotFound();
});

// ── auto check-out tidak membuat Hadir palsu ───────────────────────────────
test('auto check-out tidak menutup baris Izin/Sakit dengan Hadir', function () {
    [$admin, $kelas] = sekolahIzin();
    [, $anak] = orangTuaIzin($admin, $kelas);
    Attendance::create(['school_id' => $admin->school_id, 'student_id' => $anak->id, 'attendance_date' => '2026-10-15', 'type' => AttendanceType::CheckIn, 'status' => AttendanceStatus::Sakit, 'recorded_at' => '2026-10-15 07:00:00']);
    Carbon::setTestNow(Carbon::parse('2026-10-15 23:00', 'Asia/Jakarta'));

    Artisan::call('attendance:auto-checkout', ['--date' => '2026-10-15']);

    expect(Attendance::withoutGlobalScopes()->where('type', AttendanceType::CheckOut)->count())->toBe(0);
});
