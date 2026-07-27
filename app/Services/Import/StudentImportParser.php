<?php

namespace App\Services\Import;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\Import\Concerns\ReadsSpreadsheetRows;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Membaca berkas xlsx/csv siswa secara streaming lalu memutuskan, per baris,
 * apakah data itu siswa baru, pembaruan siswa lama, atau ditolak.
 *
 * Parser TIDAK menulis apa pun ke database — hasilnya dipakai untuk pratinjau
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

    /**
     * Kolom tujuan => daftar judul kolom yang diterima, sudah dinormalkan
     * (huruf kecil, spasi/underscore/strip dibuang).
     *
     * @var array<string, list<string>>
     */
    private const FIELD_SYNONYMS = [
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
     *     rows: list<array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>}>,
     *     summary: array{create: int, update: int, reject: int, total: int}
     * }
     *
     * @throws RuntimeException Jika format berkas, header, atau jumlah barisnya tidak bisa diproses.
     */
    public function parse(string $path, string $extension, string $schoolId): array
    {
        $classrooms = $this->classroomIndex($schoolId);
        [$studentsByNisn, $studentsByNis] = $this->studentIndexes($schoolId);

        $columns = null;
        $rows = [];
        $dataRowCount = 0;

        // Duplikat di dalam berkas itu sendiri: dua baris ber-NISN sama akan
        // saling menimpa diam-diam kalau dibiarkan lolos.
        $seenNisn = [];
        $seenNis = [];

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
                    );
                }

                // Hanya sheet pertama yang diimpor.
                break;
            }
        } finally {
            $reader->close();
        }

        if ($columns === null) {
            throw new RuntimeException('Berkas kosong — tidak ada baris header yang bisa dibaca.');
        }

        $summary = [
            self::ACTION_CREATE => 0,
            self::ACTION_UPDATE => 0,
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
     * @return array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>}
     */
    private function buildRow(
        int $rowNumber,
        array $fields,
        array $classrooms,
        array $studentsByNisn,
        array $studentsByNis,
        array &$seenNisn,
        array &$seenNis,
    ): array {
        $data = [];

        $nisn = $fields['nisn'] ?? '';
        $nis = $fields['nis'] ?? '';

        foreach (['nisn', 'nis', 'full_name', 'no_absen', 'birth_place', 'address', 'parent_name'] as $field) {
            if (($fields[$field] ?? '') !== '') {
                $data[$field] = $fields[$field];
            }
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

        // Pencocokan EKSAK: NISN dulu, lalu NIS. Nama tidak pernah dipakai
        // untuk mencocokkan — dua siswa bisa saja senama.
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
            return [
                'row_number' => $rowNumber,
                'action' => self::ACTION_UPDATE,
                'reason' => null,
                'student_id' => $candidates[0]['id'],
                'data' => $data,
            ];
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
        // NULL berbeda dan tidak lagi menolaknya — penjagaannya harus di sini.
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

        $identifiers = array_intersect(['nisn', 'nis', 'full_name'], $columns);

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
            ->get(['id', 'name'])
            ->each(function (Classroom $classroom) use (&$index): void {
                $index[$this->normalizeName($classroom->name)][] = $classroom->id;
            });

        return $index;
    }

    /**
     * @return array{0: array<string, list<array{id: string, full_name: string}>>, 1: array<string, list<array{id: string, full_name: string}>>}
     */
    private function studentIndexes(string $schoolId): array
    {
        $byNisn = [];
        $byNis = [];

        Student::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->get(['id', 'nis', 'nisn', 'full_name'])
            ->each(function (Student $student) use (&$byNisn, &$byNis): void {
                $entry = ['id' => $student->id, 'full_name' => (string) $student->full_name];

                if (($nisn = trim((string) $student->nisn)) !== '') {
                    $byNisn[$nisn][] = $entry;
                }

                if (($nis = trim((string) $student->nis)) !== '') {
                    $byNis[$nis][] = $entry;
                }
            });

        return [$byNisn, $byNis];
    }
}
