<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\Import\StudentImportParser;

/**
 * Seluruh ekspor pindah dari CSV ke XLSX.
 *
 * Yang diuji di sini bukan "apakah berkasnya terunduh" melainkan dua hal yang
 * TIDAK PERNAH bisa dijamin CSV: tipe tiap sel, dan bahwa teks berawalan `=`
 * tidak berubah jadi rumus. Karena itu berkasnya dibaca ulang, bukan cuma
 * diperiksa header HTTP-nya.
 */
beforeEach(function () {
    $this->admin = createAdminUser();
});

/**
 * Rentang yang mengapit hari ini dari kedua sisi.
 *
 * Rentang bawaan (`startOfMonth`..`endOfMonth`) menjatuhkan baris di tanggal
 * terakhir saat dijalankan di SQLite: kolom bertipe `date` menyimpan komponen
 * jam, sehingga `'…-31 00:00:00' > '…-31'` secara leksikografis. Gejala test,
 * bukan bug produksi — MySQL memakai kolom DATE sungguhan.
 */
function rekapUrl(): string
{
    return route('admin.laporan.export', [
        'start_date' => today()->subDays(5)->toDateString(),
        'end_date' => today()->addDays(5)->toDateString(),
    ]);
}

function siswaDenganAbsensi(string $schoolId, array $attributes = []): Student
{
    $student = Student::factory()->create([
        'school_id' => $schoolId,
        ...$attributes,
    ]);

    Attendance::factory()->create([
        'school_id' => $schoolId,
        'student_id' => $student->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    return $student;
}

/**
 * Inti temuan yang membentuk `XlsxDownload`: `Cell::fromValue()` milik OpenSpout
 * mengubah string berawalan `=` menjadi `FormulaCell`, jadi pindah ke XLSX
 * TIDAK otomatis menutup celah yang dulu ditutup `csvSafe()`. Nama siswa datang
 * dari `/daftar` yang publik.
 *
 * Elemen `<f>` di sheet XML adalah satu-satunya bukti pasti: pembaca OpenSpout
 * mengembalikan nilai sel, bukan jenisnya, jadi memeriksa nilai saja buta.
 */
test('nama berbentuk rumus ditulis sebagai teks, bukan rumus', function () {
    $jahat = '=cmd|\' /c calc\'!A1';

    siswaDenganAbsensi($this->admin->school_id, ['full_name' => $jahat, 'nis' => '20250001']);

    $response = $this->actingAs($this->admin)->get(rekapUrl());

    $response->assertOk();

    expect(xlsxSheetXml($response))->not->toContain('<f>')
        ->and(collect(xlsxRows($response))->flatten()->all())->toContain(strtoupper($jahat));
});

/**
 * Alasan utama permintaan ini. NIS berawalan nol dibaca Excel sebagai angka
 * pada CSV dan nol depannya hilang; nomor yang lebih panjang berubah menjadi
 * notasi ilmiah. XLSX menyimpan tipe selnya, jadi tebakan itu tidak terjadi.
 */
test('NIS berawalan nol tidak kehilangan nol depannya', function () {
    siswaDenganAbsensi($this->admin->school_id, ['nis' => '0071234567']);

    $response = $this->actingAs($this->admin)->get(rekapUrl());

    expect(collect(xlsxRows($response))->flatten()->all())->toContain('0071234567');
});

test('kolom hitungan tersimpan sebagai angka supaya bisa dijumlah di Excel', function () {
    siswaDenganAbsensi($this->admin->school_id);

    $response = $this->actingAs($this->admin)->get(rekapUrl());

    // Sel numerik tidak punya atribut `t="s"` (shared string) maupun
    // `t="inlineStr"`; nilainya ditulis telanjang di dalam `<v>`.
    $baris = xlsxRows($response)[1];

    expect($baris[3])->toBe('1');
    expect(xlsxSheetXml($response))->toContain('<v>1</v>');
});

test('rekap laporan terunduh sebagai xlsx', function () {
    siswaDenganAbsensi($this->admin->school_id);

    $response = $this->actingAs($this->admin)->get(rekapUrl());

    $response->assertOk()->assertDownload();

    expect($response->headers->get('content-type'))
        ->toContain('spreadsheetml.sheet')
        ->and($response->headers->get('content-disposition'))->toContain('.xlsx');

    expect(xlsxRows($response)[0])->toBe([
        'NIS', 'Nama Siswa', 'Kelas', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', '% Kehadiran',
    ]);
});

test('laporan per siswa terunduh sebagai xlsx dengan blok identitas', function () {
    $student = siswaDenganAbsensi($this->admin->school_id, [
        'full_name' => 'SITI RAHMAWATI',
        'nis' => '20250099',
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.siswa.laporan.absensi.xlsx', $student));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('absensi-20250099-');

    $isi = collect(xlsxRows($response))->flatten()->all();

    expect($isi)->toContain('SITI RAHMAWATI')
        ->toContain('% Kehadiran');
});

test('laporan sholat terunduh sebagai xlsx', function () {
    $student = siswaDenganAbsensi($this->admin->school_id);

    $this->actingAs($this->admin)
        ->get(route('admin.siswa.laporan.sholat.xlsx', $student))
        ->assertOk()
        ->assertDownload();
});

/**
 * Ekspor dan impor tidak boleh berpisah jalan: template yang diunduh harus bisa
 * langsung diisi lalu diunggah kembali tanpa satu pun kolom ditolak.
 */
test('template impor yang diunduh bisa dibaca kembali oleh parser impor', function () {
    Classroom::factory()->create(['school_id' => $this->admin->school_id, 'name' => '7A']);

    $response = $this->actingAs($this->admin)->get(route('admin.siswa.import.template'));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('template-impor-siswa.xlsx');

    $hasil = app(StudentImportParser::class)->parse(
        $response->baseResponse->getFile()->getPathname(),
        'xlsx',
        $this->admin->school_id,
    );

    expect($hasil['summary']['reject'])->toBe(0)
        ->and($hasil['summary']['create'])->toBe(2);
});
