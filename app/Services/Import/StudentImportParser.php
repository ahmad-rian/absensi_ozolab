<?php

namespace App\Services\Import;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Services\Import\Concerns\ReadsSpreadsheetRows;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Membaca berkas xlsx/csv siswa secara streaming lalu memutuskan, per baris,
 * apakah data itu siswa baru, pembaruan siswa lama, atau ditolak.
 *
 * Parser TIDAK menulis apa pun ke database - hasilnya dipakai untuk pratinjau
 * lebih dulu, baru StudentImportApplier yang menerapkannya.
 */
class StudentImportParser
{
    use ReadsSpreadsheetRows;

    /**
     * Batas ini menjaga memori: seluruh hasil parse ditahan di array sebelum
     * dipratinjau, jadi berkas raksasa ditolak sebelum dibaca sampai habis.
     */
    public const MAX_ROWS = 5000;

    private const ACTION_CREATE = 'create';

    private const ACTION_UPDATE = 'update';

    private const ACTION_REJECT = 'reject';

    /** Cocok dengan siswa lama tetapi tidak ada satu kolom pun yang berbeda. */
    private const ACTION_UNCHANGED = 'unchanged';

    /**
     * Kolom yang dibandingkan untuk pratinjau perubahan: kolom data => judul
     * kolom di template, supaya operator mengenalinya dari berkasnya sendiri.
     *
     * @var array<string, string>
     */
    private const LABEL_KOLOM = [
        'nisn' => 'NISN',
        'nis' => 'NIS',
        'full_name' => 'Nama',
        'classroom_id' => 'Kelas',
        'gender' => 'JK',
        'religion' => 'Agama',
        'no_absen' => 'No Absen',
        'birth_place' => 'Tempat Lahir',
        'birth_date' => 'Tanggal Lahir',
        'address' => 'Alamat',
        'parent_name' => 'Nama Orang Tua',
        'parent_phone' => 'No HP',
        'parent_email' => 'Email Orang Tua',
    ];

    /**
     * Kolom tujuan => daftar judul kolom yang diterima, sudah dinormalkan
     * (huruf kecil, spasi/underscore/strip dibuang).
     *
     * @var array<string, list<string>>
     */
    private const FIELD_SYNONYMS = [
        // Diisi oleh "Unduh Data Siswa": kunci pasti untuk siswa yang belum
        // punya NISN/NIS asli (mis. dari pendaftaran cepat).
        'student_id' => ['idsistem'],
        'nisn' => ['nisn'],
        'nis' => ['nis', 'nomorinduk'],
        'full_name' => ['nama', 'namalengkap', 'fullname', 'namasiswa'],
        'classroom' => ['kelas', 'rombel'],
        'gender' => ['jk', 'gender', 'jeniskelamin', 'l/p', 'lp'],
        'religion' => ['agama', 'religion'],
        'no_absen' => ['noabsen', 'absen'],
        'birth_place' => ['tempatlahir', 'birthplace'],
        'birth_date' => ['tanggallahir', 'birthdate', 'tgllahir'],
        'address' => ['alamat', 'address'],
        'parent_email' => ['emailortu', 'emailorangtua', 'emailwali', 'parentemail'],
        'parent_name' => ['namaorangtua', 'namaortu', 'parentname'],
        'parent_phone' => ['nohp', 'nomorhp', 'whatsapp', 'parentphone'],
    ];

    /** @var array<string, Gender> */
    private const GENDER_ALIASES = [
        'l' => Gender::LakiLaki,
        'm' => Gender::LakiLaki,
        'laki' => Gender::LakiLaki,
        'lakilaki' => Gender::LakiLaki,
        'pria' => Gender::LakiLaki,
        'male' => Gender::LakiLaki,
        'p' => Gender::Perempuan,
        'f' => Gender::Perempuan,
        'perempuan' => Gender::Perempuan,
        'wanita' => Gender::Perempuan,
        'female' => Gender::Perempuan,
    ];

    /** @var array<string, Religion> */
    private const RELIGION_ALIASES = [
        'islam' => Religion::Islam,
        'muslim' => Religion::Islam,
        'kristen' => Religion::Kristen,
        'protestan' => Religion::Kristen,
        'kristenprotestan' => Religion::Kristen,
        'katolik' => Religion::Katolik,
        'katholik' => Religion::Katolik,
        'kristenkatolik' => Religion::Katolik,
        'hindu' => Religion::Hindu,
        'buddha' => Religion::Buddha,
        'budha' => Religion::Buddha,
        'buda' => Religion::Buddha,
        'konghucu' => Religion::Konghucu,
        'khonghucu' => Religion::Konghucu,
    ];

    /** @var list<string> */
    private const DATE_FORMATS = ['d/m/Y', 'd-m-Y', 'Y-m-d'];

    /**
     * @return array{
     *     rows: list<array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>, changes?: list<array{kolom: string, lama: string, baru: string}>}>,
     *     summary: array{create: int, update: int, unchanged: int, reject: int, total: int}
     * }
     *
     * @throws RuntimeException Jika format berkas, header, atau jumlah barisnya tidak bisa diproses.
     */
    public function parse(string $path, string $extension, string $schoolId): array
    {
        $classrooms = $this->classroomIndex($schoolId);
        [$studentsByNisn, $studentsByNis, $studentsById] = $this->studentIndexes($schoolId);
        $classroomNames = $this->classroomNames($schoolId);

        $columns = null;
        $rows = [];
        $dataRowCount = 0;

        // Duplikat di dalam berkas itu sendiri: dua baris ber-NISN sama akan
        // saling menimpa diam-diam kalau dibiarkan lolos.
        $seenNisn = [];
        $seenNis = [];
        $seenId = [];

        $reader = $this->readerFor($path, $extension);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $values = $this->rowValues($row);

                    if ($columns === null) {
                        $columns = $this->mapColumns($values);

                        continue;
                    }

                    if ($this->isBlank($values)) {
                        continue;
                    }

                    if (++$dataRowCount > self::MAX_ROWS) {
                        throw new RuntimeException(
                            'Berkas berisi lebih dari '.number_format(self::MAX_ROWS, 0, ',', '.')
                            .' baris data. Pecah menjadi beberapa berkas lalu unggah bergantian.'
                        );
                    }

                    $rows[] = $this->buildRow(
                        (int) $rowNumber,
                        $this->extractFields($columns, $values),
                        $classrooms,
                        $studentsByNisn,
                        $studentsByNis,
                        $seenNisn,
                        $seenNis,
                        $studentsById,
                        $classroomNames,
                        $seenId,
                    );
                }

                // Hanya sheet pertama yang diimpor.
                break;
            }
        } finally {
            $reader->close();
        }

        if ($columns === null) {
            throw new RuntimeException('Berkas kosong - tidak ada baris header yang bisa dibaca.');
        }

        $summary = [
            self::ACTION_CREATE => 0,
            self::ACTION_UPDATE => 0,
            self::ACTION_UNCHANGED => 0,
            self::ACTION_REJECT => 0,
        ];

        foreach ($rows as $row) {
            $summary[$row['action']]++;
        }

        return [
            'rows' => $rows,
            'summary' => [...$summary, 'total' => count($rows)],
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, list<string>>  $classrooms  nama ternormalkan => daftar id kelas
     * @param  array<string, list<array{id: string, full_name: string}>>  $studentsByNisn
     * @param  array<string, list<array{id: string, full_name: string}>>  $studentsByNis
     * @param  array<string, int>  $seenNisn
     * @param  array<string, int>  $seenNis
     * @param  array<string, array<string, mixed>>  $studentsById  id => potret data siswa saat ini
     * @param  array<string, string>  $classroomNames  id kelas => nama
     * @param  array<string, int>  $seenId
     * @return array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>, changes?: list<array{kolom: string, lama: string, baru: string}>}
     */
    private function buildRow(
        int $rowNumber,
        array $fields,
        array $classrooms,
        array $studentsByNisn,
        array $studentsByNis,
        array &$seenNisn,
        array &$seenNis,
        array $studentsById = [],
        array $classroomNames = [],
        array &$seenId = [],
    ): array {
        $data = [];

        $nisn = $fields['nisn'] ?? '';
        $nis = $fields['nis'] ?? '';

        foreach (['nisn', 'nis', 'full_name', 'no_absen', 'birth_place', 'address', 'parent_name'] as $field) {
            if (($fields[$field] ?? '') !== '') {
                $data[$field] = $fields[$field];
            }
        }

        if (($fields['parent_email'] ?? '') !== '') {
            if (! filter_var($fields['parent_email'], FILTER_VALIDATE_EMAIL)) {
                return $this->reject($rowNumber, 'Format email orang tua tidak valid.');
            }
            $data['parent_email'] = $fields['parent_email'];
        }

        if (($fields['parent_phone'] ?? '') !== '') {
            $data['parent_phone'] = $this->normalizePhone($fields['parent_phone']);
        }

        if (($fields['gender'] ?? '') !== '') {
            $gender = self::GENDER_ALIASES[$this->aliasKey($fields['gender'])] ?? null;

            if ($gender === null) {
                return $this->reject($rowNumber, 'Jenis kelamin "'.$fields['gender'].'" tidak dikenali. Gunakan L/P atau Laki-laki/Perempuan.');
            }

            $data['gender'] = $gender->value;
        }

        if (($fields['religion'] ?? '') !== '') {
            $religion = self::RELIGION_ALIASES[$this->aliasKey($fields['religion'])] ?? null;

            if ($religion === null) {
                return $this->reject($rowNumber, 'Agama "'.$fields['religion'].'" tidak dikenali.');
            }

            $data['religion'] = $religion->value;
        }

        if (($fields['birth_date'] ?? '') !== '') {
            $birthDate = $this->parseDate($fields['birth_date']);

            if ($birthDate === null) {
                return $this->reject($rowNumber, 'Tanggal lahir "'.$fields['birth_date'].'" tidak dikenali. Gunakan format dd/mm/yyyy.');
            }

            $data['birth_date'] = $birthDate;
        }

        if (($fields['classroom'] ?? '') !== '') {
            $matches = $classrooms[$this->normalizeName($fields['classroom'])] ?? [];

            if ($matches === []) {
                return $this->reject($rowNumber, 'Kelas "'.$fields['classroom'].'" tidak ditemukan di sekolah ini.');
            }

            if (count($matches) > 1) {
                return $this->reject($rowNumber, 'Kelas "'.$fields['classroom'].'" ada lebih dari satu di sekolah ini. Rapikan nama kelas dulu.');
            }

            $data['classroom_id'] = $matches[0];
        }

        if ($nisn !== '') {
            if (isset($seenNisn[$nisn])) {
                return $this->reject($rowNumber, 'NISN '.$nisn.' sudah dipakai di baris '.$seenNisn[$nisn].' pada berkas yang sama.');
            }

            $seenNisn[$nisn] = $rowNumber;
        }

        if ($nis !== '') {
            if (isset($seenNis[$nis])) {
                return $this->reject($rowNumber, 'NIS '.$nis.' sudah dipakai di baris '.$seenNis[$nis].' pada berkas yang sama.');
            }

            $seenNis[$nis] = $rowNumber;
        }

        // ID Sistem (dari "Unduh Data Siswa") adalah kunci paling pasti: ia
        // tetap menunjuk siswa yang sama walau NISN/NIS-nya baru diisi sekarang.
        $idSistem = $fields['student_id'] ?? '';

        if ($idSistem !== '') {
            if (isset($seenId[$idSistem])) {
                return $this->reject($rowNumber, 'ID Sistem ini sudah dipakai di baris '.$seenId[$idSistem].' pada berkas yang sama.');
            }

            $seenId[$idSistem] = $rowNumber;

            $current = $studentsById[$idSistem] ?? null;

            if ($current === null) {
                return $this->reject($rowNumber, 'ID Sistem tidak dikenali di sekolah ini. Unduh ulang data siswa lalu isi tanpa mengubah kolom ID Sistem, atau kosongkan kolom itu untuk siswa baru.');
            }

            foreach ([['NISN', $nisn, $studentsByNisn], ['NIS', $nis, $studentsByNis]] as [$label, $value, $index]) {
                $pemilikLain = array_values(array_filter(
                    $value === '' ? [] : ($index[$value] ?? []),
                    static fn (array $student): bool => $student['id'] !== $idSistem,
                ));

                if ($pemilikLain !== []) {
                    return $this->reject($rowNumber, $label.' '.$value.' sudah dipakai siswa lain ('.$pemilikLain[0]['full_name'].'). Perbaiki nomornya.');
                }
            }

            return $this->perbarui($rowNumber, $current, $data, $classroomNames);
        }

        // Pencocokan EKSAK: NISN dulu, lalu NIS. Nama tidak pernah dipakai
        // untuk mencocokkan - dua siswa bisa saja senama.
        $candidates = [];

        if ($nisn !== '') {
            $candidates = $studentsByNisn[$nisn] ?? [];
        } elseif ($nis !== '') {
            $candidates = $studentsByNis[$nis] ?? [];
        }

        if (count($candidates) > 1) {
            $names = implode(', ', array_map(static fn (array $student): string => $student['full_name'], $candidates));
            $key = $nisn !== '' ? 'NISN '.$nisn : 'NIS '.$nis;

            return $this->reject($rowNumber, $key.' cocok dengan lebih dari satu siswa: '.$names.'. Rapikan data siswa dulu.');
        }

        if (count($candidates) === 1) {
            $current = $studentsById[$candidates[0]['id']] ?? ['id' => $candidates[0]['id']];

            return $this->perbarui($rowNumber, $current, $data, $classroomNames);
        }

        $missing = [];

        if (($data['full_name'] ?? '') === '') {
            $missing[] = 'Nama';
        }

        if (($data['gender'] ?? '') === '') {
            $missing[] = 'Jenis kelamin';
        }

        if (($data['classroom_id'] ?? '') === '') {
            $missing[] = 'Kelas';
        }

        if ($nis === '' && $nisn === '') {
            $missing[] = 'NIS atau NISN';
        }

        if ($missing !== []) {
            return $this->reject($rowNumber, 'Siswa baru tetapi kolom wajib kosong: '.implode(', ', $missing).'.');
        }

        // Baris ini akan membuat siswa BARU, jadi kedua nomornya wajib benar-benar
        // bebas. Pencocokan di atas berhenti pada nomor yang pertama terisi: baris
        // ber-NISN baru tapi ber-NIS milik siswa lama tidak cocok apa pun dan lolos
        // ke sini. Sejak `deleted_at` masuk unique index, database menganggap tiap
        // NULL berbeda dan tidak lagi menolaknya - penjagaannya harus di sini.
        foreach ([['NISN', $nisn, $studentsByNisn], ['NIS', $nis, $studentsByNis]] as [$label, $value, $index]) {
            if ($value === '' || ($index[$value] ?? []) === []) {
                continue;
            }

            $owner = $index[$value][0]['full_name'];

            return $this->reject(
                $rowNumber,
                $label.' '.$value.' sudah dipakai siswa lain ('.$owner.'). Perbaiki nomornya atau samakan dengan data siswa itu.'
            );
        }

        return [
            'row_number' => $rowNumber,
            'action' => self::ACTION_CREATE,
            'reason' => null,
            'student_id' => null,
            'data' => $data,
        ];
    }

    /**
     * Baris yang cocok dengan siswa lama: bandingkan dengan data sekarang supaya
     * pratinjau bisa memperlihatkan persis kolom mana yang akan ditimpa.
     *
     * Kolom kosong di berkas tidak pernah masuk `$data`, jadi tidak pernah
     * dihitung sebagai perubahan - sama dengan aturan di StudentImportApplier.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $classroomNames
     * @return array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>, changes: list<array{kolom: string, lama: string, baru: string}>}
     */
    private function perbarui(int $rowNumber, array $current, array $data, array $classroomNames): array
    {
        // Nama kelas dibuat ulang tiap tahun ajaran. Siswa yang masih duduk di
        // "7A" tahun lalu tidak boleh diam-diam terseret ke "7A" tahun aktif
        // hanya karena berkasnya menyebut nama yang sama; itu urusan Kenaikan Kelas.
        $kelasBaru = $data['classroom_id'] ?? null;
        $kelasLama = $current['classroom_id'] ?? null;

        if ($kelasBaru !== null && $kelasLama !== null && $kelasBaru !== $kelasLama
            && $this->normalizeName($classroomNames[$kelasBaru] ?? '') === $this->normalizeName($classroomNames[$kelasLama] ?? '')) {
            unset($data['classroom_id']);
        }

        $changes = [];

        foreach (self::LABEL_KOLOM as $field => $label) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $lama = $current[$field] ?? null;
            $baru = $data[$field];

            if ($this->kunciBanding($field, $lama) === $this->kunciBanding($field, $baru)) {
                continue;
            }

            $changes[] = [
                'kolom' => $label,
                'lama' => $this->tampilan($field, $lama, $classroomNames),
                'baru' => $this->tampilan($field, $baru, $classroomNames),
            ];
        }

        return [
            'row_number' => $rowNumber,
            'action' => $changes === [] ? self::ACTION_UNCHANGED : self::ACTION_UPDATE,
            'reason' => null,
            'student_id' => (string) $current['id'],
            'data' => $data,
            'changes' => $changes,
        ];
    }

    /**
     * Bentuk nilai untuk dibandingkan: beda huruf besar-kecil pada nama, atau
     * `+62` lawan `08` pada nomor HP, bukan perubahan.
     */
    private function kunciBanding(string $field, mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return match ($field) {
            // Keduanya disimpan huruf besar oleh mutator Student.
            'full_name', 'parent_name' => mb_strtoupper($value),
            'parent_phone' => $value === '' ? '' : $this->normalizePhone($value),
            'parent_email' => mb_strtolower($value),
            default => $value,
        };
    }

    /**
     * Nilai seperti yang ditulis operator di berkas, bukan bentuk simpanannya.
     *
     * @param  array<string, string>  $classroomNames
     */
    private function tampilan(string $field, mixed $value, array $classroomNames): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '';
        }

        return match ($field) {
            'classroom_id' => $classroomNames[$value] ?? '(kelas tidak dikenal)',
            'gender' => Gender::tryFrom($value) === Gender::LakiLaki ? 'L' : (Gender::tryFrom($value) === Gender::Perempuan ? 'P' : $value),
            'religion' => Religion::tryFrom($value)?->label() ?? $value,
            'birth_date' => $this->tanggalTampil($value),
            default => $value,
        };
    }

    private function tanggalTampil(string $value): string
    {
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @return array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>}
     */
    private function reject(int $rowNumber, string $reason): array
    {
        return [
            'row_number' => $rowNumber,
            'action' => self::ACTION_REJECT,
            'reason' => $reason,
            'student_id' => null,
            'data' => [],
        ];
    }

    /**
     * @param  list<string>  $headers
     * @return array<int, string> indeks kolom => nama kolom tujuan
     */
    private function mapColumns(array $headers): array
    {
        $lookup = [];

        foreach (self::FIELD_SYNONYMS as $field => $synonyms) {
            foreach ($synonyms as $synonym) {
                $lookup[$synonym] = $field;
            }
        }

        $columns = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader($header);
            $field = $lookup[$normalized] ?? null;

            // Kolom pertama yang cocok yang menang; kolom kembar diabaikan.
            if ($field !== null && ! in_array($field, $columns, true)) {
                $columns[$index] = $field;
            }
        }

        $identifiers = array_intersect(['student_id', 'nisn', 'nis', 'full_name'], $columns);

        if ($identifiers === []) {
            throw new RuntimeException('Header tidak dikenali. Minimal harus ada kolom NISN, NIS, atau Nama.');
        }

        return $columns;
    }

    /**
     * @param  array<int, string>  $columns
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function extractFields(array $columns, array $values): array
    {
        $fields = [];

        foreach ($columns as $index => $field) {
            $fields[$field] = trim($values[$index] ?? '');
        }

        return $fields;
    }

    /**
     * Kunci pencarian alias enum: sisakan huruf saja supaya "Laki-Laki",
     * "LAKI_LAKI", dan "laki laki" jatuh ke kunci yang sama.
     */
    private function aliasKey(string $value): string
    {
        return preg_replace('/[^a-z]/', '', mb_strtolower(trim($value))) ?? '';
    }

    /**
     * Excel membuang angka nol di depan nomor HP saat kolomnya bertipe angka;
     * kembalikan ke format lokal 08xx supaya cocok dengan data yang sudah ada.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if (str_starts_with($digits, '62')) {
            return '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '8')) {
            return '0'.$digits;
        }

        return $digits;
    }

    private function parseDate(string $value): ?string
    {
        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (\Throwable) {
                continue;
            }

            // Carbon menerima "31/02/2010" dan menggesernya ke Maret; bandingkan
            // ulang agar tanggal mustahil tetap ditolak.
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>> nama kelas ternormalkan => daftar id
     */
    private function classroomIndex(string $schoolId): array
    {
        $index = [];

        Classroom::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            // Nama kelas dibuat ulang tiap tahun ajaran; tanpa batas ini setiap
            // baris berkelas ditolak "lebih dari satu" begitu tahun baru dibuat.
            ->tahunAktif()
            ->get(['id', 'name'])
            ->each(function (Classroom $classroom) use (&$index): void {
                $index[$this->normalizeName($classroom->name)][] = $classroom->id;
            });

        return $index;
    }

    /**
     * @return array<string, string> id kelas => nama, seluruh tahun ajaran
     */
    private function classroomNames(string $schoolId): array
    {
        return Classroom::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array{0: array<string, list<array{id: string, full_name: string}>>, 1: array<string, list<array{id: string, full_name: string}>>, 2: array<string, array<string, mixed>>}
     */
    private function studentIndexes(string $schoolId): array
    {
        $byNisn = [];
        $byNis = [];
        $byId = [];

        $emailOrtu = ParentProfile::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->pluck('email', 'id');

        Student::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->get([
                'id', 'nis', 'nisn', 'full_name', 'classroom_id', 'gender', 'religion', 'no_absen',
                'birth_place', 'birth_date', 'address', 'parent_name', 'parent_phone', 'parent_profile_id',
            ])
            ->each(function (Student $student) use (&$byNisn, &$byNis, &$byId, $emailOrtu): void {
                $entry = ['id' => $student->id, 'full_name' => (string) $student->full_name];

                if (($nisn = trim((string) $student->nisn)) !== '') {
                    $byNisn[$nisn][] = $entry;
                }

                if (($nis = trim((string) $student->nis)) !== '') {
                    $byNis[$nis][] = $entry;
                }

                $byId[$student->id] = [
                    'id' => $student->id,
                    'nisn' => $student->nisn,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'classroom_id' => $student->classroom_id,
                    'gender' => $student->gender?->value,
                    'religion' => $student->religion?->value,
                    'no_absen' => $student->no_absen,
                    'birth_place' => $student->birth_place,
                    'birth_date' => $student->birth_date?->format('Y-m-d'),
                    'address' => $student->address,
                    'parent_name' => $student->parent_name,
                    'parent_phone' => $student->parent_phone,
                    'parent_email' => $student->parent_profile_id ? ($emailOrtu[$student->parent_profile_id] ?? null) : null,
                ];
            });

        return [$byNisn, $byNis, $byId];
    }
}
