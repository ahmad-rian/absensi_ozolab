<?php

namespace App\Services\Import;

use App\Models\Classroom;
use App\Models\Student;
use App\Services\Import\Concerns\ReadsSpreadsheetRows;
use RuntimeException;

/**
 * Membaca berkas kenaikan kelas - hanya butuh NISN (atau NIS) + kelas tujuan -
 * lalu memutuskan per baris apakah siswanya dipindah, diluluskan, atau ditolak.
 *
 * Pencocokan siswa selalu eksak: NISN dulu; NIS hanya dipakai bila kolom NISN
 * baris itu kosong. Nama tidak pernah jadi kunci.
 *
 * Parser TIDAK menulis apa pun ke database. Selain baris berkas, ia juga
 * mengembalikan daftar siswa sekolah yang TIDAK muncul di berkas: itu kasus
 * paling berbahaya di kenaikan kelas (satu kelas terlewat saat menyusun
 * berkas), dan kelas mereka sengaja dibiarkan apa adanya.
 */
class ClassPromotionParser
{
    use ReadsSpreadsheetRows;

    /**
     * Sama dengan impor siswa: seluruh hasil parse ditahan di memori sebelum
     * dipratinjau, jadi berkas raksasa ditolak sebelum dibaca sampai habis.
     */
    public const MAX_ROWS = 5000;

    public const ACTION_PROMOTE = 'promote';

    public const ACTION_REJECT = 'reject';

    /** Siswa kelas akhir: kolom Kelas Baru diisi kata ini. */
    public const ACTION_GRADUATE = 'graduate';

    public const KATA_LULUS = 'lulus';

    /**
     * Kolom tujuan => judul kolom yang diterima, sudah dinormalkan dan
     * DIURUTKAN dari yang paling spesifik. Berkas kenaikan kelas lazim memuat
     * kolom "kelas" (asal) dan "kelas baru" sekaligus; yang spesifik harus
     * menang walau posisinya di sebelah kanan.
     *
     * @var array<string, list<string>>
     */
    private const FIELD_SYNONYMS = [
        'nisn' => ['nisn'],
        'nis' => ['nis', 'nomorinduk', 'nomorinduksiswa'],
        'classroom' => ['kelasbaru', 'kelastujuan', 'kelas', 'rombel'],
    ];

    /**
     * @param  string  $activeAcademicYearId  Tahun ajaran yang sedang aktif; kelas tujuan wajib miliknya.
     * @return array{
     *     rows: list<array{row_number: int, action: string, reason: ?string, student_id: ?string, student_name: ?string, nisn: string, nis: string, from_classroom: ?string, to_classroom: string, classroom_id: ?string}>,
     *     missing: list<array{student_id: string, nisn: string, nis: string, full_name: string, classroom: ?string}>,
     *     summary: array{promote: int, graduate: int, reject: int, missing: int, total: int}
     * }
     *
     * @throws RuntimeException Jika format berkas, header, atau jumlah barisnya tidak bisa diproses.
     */
    public function parse(string $path, string $extension, string $schoolId, string $activeAcademicYearId): array
    {
        $classrooms = $this->classroomIndex($schoolId);
        [$studentsByNisn, $allStudents, $studentsByNis] = $this->studentIndexes($schoolId);

        $columns = null;
        $rows = [];
        $dataRowCount = 0;

        // Dua baris ber-NISN sama akan saling menimpa diam-diam kalau dibiarkan.
        $seenNisn = [];
        $matchedStudentIds = [];

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
                        $activeAcademicYearId,
                        $seenNisn,
                        $matchedStudentIds,
                    );
                }

                // Hanya sheet pertama yang dibaca.
                break;
            }
        } finally {
            $reader->close();
        }

        if ($columns === null) {
            throw new RuntimeException('Berkas kosong - tidak ada baris header yang bisa dibaca.');
        }

        $missing = [];

        foreach ($allStudents as $student) {
            if (isset($matchedStudentIds[$student['id']])) {
                continue;
            }

            // Siswa nonaktif (pindah/lulus) memang tidak ikut naik kelas, jadi
            // memunculkannya sebagai peringatan hanya menambah derau.
            if (! $student['is_active']) {
                continue;
            }

            $missing[] = [
                'student_id' => $student['id'],
                'nisn' => $student['nisn'],
                'nis' => $student['nis'],
                'full_name' => $student['full_name'],
                'classroom' => $student['classroom'],
            ];
        }

        $summary = [
            self::ACTION_PROMOTE => 0,
            self::ACTION_GRADUATE => 0,
            self::ACTION_REJECT => 0,
        ];

        foreach ($rows as $row) {
            $summary[$row['action']]++;
        }

        return [
            'rows' => $rows,
            'missing' => $missing,
            'summary' => [...$summary, 'missing' => count($missing), 'total' => count($rows)],
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, list<array{id: string, name: string, academic_year_id: string}>>  $classrooms
     * @param  array<string, list<array{id: string, nisn: string, nis: string, full_name: string, classroom: ?string, is_active: bool}>>  $studentsByNisn
     * @param  array<string, list<array{id: string, nisn: string, nis: string, full_name: string, classroom: ?string, is_active: bool}>>  $studentsByNis
     * @param  array<string, int>  $seenNisn  kunci "NISN:x" / "NIS:x" => nomor baris pertama
     * @param  array<string, true>  $matchedStudentIds
     * @return array{row_number: int, action: string, reason: ?string, student_id: ?string, student_name: ?string, nisn: string, nis: string, from_classroom: ?string, to_classroom: string, classroom_id: ?string}
     */
    private function buildRow(
        int $rowNumber,
        array $fields,
        array $classrooms,
        array $studentsByNisn,
        array $studentsByNis,
        string $activeAcademicYearId,
        array &$seenNisn,
        array &$matchedStudentIds,
    ): array {
        $nisn = $fields['nisn'] ?? '';
        $nis = $fields['nis'] ?? '';
        $target = $fields['classroom'] ?? '';

        // NISN menang; NIS hanya jalan cadangan untuk siswa yang NISN-nya kosong.
        $pakaiNis = $nisn === '' && $nis !== '';
        $nomor = $pakaiNis ? $nis : $nisn;
        $label = $pakaiNis ? 'NIS' : 'NISN';

        if ($nomor === '') {
            return $this->reject($rowNumber, $nisn, $nis, $target, 'Kolom NISN kosong'.(array_key_exists('nis', $fields) ? ' dan kolom NIS juga kosong.' : '.'));
        }

        if ($target === '') {
            return $this->reject($rowNumber, $nisn, $nis, $target, 'Kolom Kelas Baru kosong, jadi kelas siswa ini tidak diubah. Isi kelas tujuannya atau tulis LULUS.');
        }

        $kunci = $label.':'.$nomor;

        if (isset($seenNisn[$kunci])) {
            return $this->reject($rowNumber, $nisn, $nis, $target, $label.' '.$nomor.' sudah dipakai di baris '.$seenNisn[$kunci].' pada berkas yang sama.');
        }

        $seenNisn[$kunci] = $rowNumber;

        $studentMatches = ($pakaiNis ? $studentsByNis : $studentsByNisn)[$nomor] ?? [];

        if ($studentMatches === []) {
            return $this->reject($rowNumber, $nisn, $nis, $target, $label.' '.$nomor.' tidak terdaftar di sekolah ini.');
        }

        if (count($studentMatches) > 1) {
            $names = implode(', ', array_map(static fn (array $match): string => $match['full_name'], $studentMatches));

            return $this->reject($rowNumber, $nisn, $nis, $target, $label.' '.$nomor.' cocok dengan lebih dari satu siswa: '.$names.'. Rapikan data siswa dulu.');
        }

        $student = $studentMatches[0];

        // Siswa yang sama bisa muncul dua kali lewat NISN di satu baris dan NIS
        // di baris lain; yang kedua ditolak supaya tidak saling menimpa.
        if (isset($matchedStudentIds[$student['id']])) {
            return $this->reject($rowNumber, $nisn, $nis, $target, $student['full_name'].' sudah ada di baris lain pada berkas yang sama.', $student);
        }

        if ($this->normalizeName($target) === self::KATA_LULUS) {
            $matchedStudentIds[$student['id']] = true;

            return [
                'row_number' => $rowNumber,
                'action' => self::ACTION_GRADUATE,
                'reason' => null,
                'student_id' => $student['id'],
                'student_name' => $student['full_name'],
                'nisn' => $student['nisn'],
                'nis' => $student['nis'],
                'from_classroom' => $student['classroom'],
                'to_classroom' => 'LULUS',
                'classroom_id' => null,
            ];
        }

        $candidates = $classrooms[$this->normalizeName($target)] ?? [];

        if ($candidates === []) {
            return $this->reject($rowNumber, $nisn, $nis, $target, 'Kelas "'.$target.'" tidak ditemukan di sekolah ini.', $student);
        }

        $inActiveYear = array_values(array_filter(
            $candidates,
            static fn (array $classroom): bool => $classroom['academic_year_id'] === $activeAcademicYearId,
        ));

        if ($inActiveYear === []) {
            return $this->reject(
                $rowNumber,
                $nisn,
                $nis,
                $target,
                'Kelas "'.$target.'" bukan milik tahun ajaran aktif. Buat dulu kelas itu di tahun ajaran berjalan.',
                $student,
            );
        }

        if (count($inActiveYear) > 1) {
            return $this->reject($rowNumber, $nisn, $nis, $target, 'Kelas "'.$target.'" ada lebih dari satu di tahun ajaran aktif. Rapikan nama kelas dulu.', $student);
        }

        $matchedStudentIds[$student['id']] = true;

        return [
            'row_number' => $rowNumber,
            'action' => self::ACTION_PROMOTE,
            'reason' => null,
            'student_id' => $student['id'],
            'student_name' => $student['full_name'],
            'nisn' => $student['nisn'],
            'nis' => $student['nis'],
            'from_classroom' => $student['classroom'],
            'to_classroom' => $inActiveYear[0]['name'],
            'classroom_id' => $inActiveYear[0]['id'],
        ];
    }

    /**
     * Baris ditolak tetap membawa identitas siswa bila sudah dikenali, supaya
     * admin tahu siapa yang gagal naik tanpa membuka berkasnya lagi.
     *
     * @param  array{id: string, nisn: string, nis: string, full_name: string, classroom: ?string, is_active: bool}|null  $student
     * @return array{row_number: int, action: string, reason: ?string, student_id: ?string, student_name: ?string, nisn: string, nis: string, from_classroom: ?string, to_classroom: string, classroom_id: ?string}
     */
    private function reject(int $rowNumber, string $nisn, string $nis, string $target, string $reason, ?array $student = null): array
    {
        return [
            'row_number' => $rowNumber,
            'action' => self::ACTION_REJECT,
            'reason' => $reason,
            'student_id' => null,
            'student_name' => $student['full_name'] ?? null,
            'nisn' => $nisn,
            'nis' => $nis !== '' ? $nis : ($student['nis'] ?? ''),
            'from_classroom' => $student['classroom'] ?? null,
            'to_classroom' => $target,
            'classroom_id' => null,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @return array<int, string> indeks kolom => nama kolom tujuan
     *
     * @throws RuntimeException
     */
    private function mapColumns(array $headers): array
    {
        $columns = [];
        $priorities = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader($header);

            foreach (self::FIELD_SYNONYMS as $field => $synonyms) {
                $priority = array_search($normalized, $synonyms, true);

                if ($priority === false) {
                    continue;
                }

                // Sinonim yang lebih spesifik menggeser kolom yang sudah dipilih.
                if (! isset($priorities[$field]) || $priority < $priorities[$field]) {
                    $columns = array_filter($columns, static fn (string $existing): bool => $existing !== $field);
                    $columns[$index] = $field;
                    $priorities[$field] = $priority;
                }

                break;
            }
        }

        $found = array_values($columns);

        $adaNomor = in_array('nisn', $found, true) || in_array('nis', $found, true);

        if (! $adaNomor || ! in_array('classroom', $found, true)) {
            throw new RuntimeException('Header tidak dikenali. Berkas kenaikan kelas wajib punya kolom NISN (atau NIS) dan Kelas Baru. Unduh templatenya supaya judul kolomnya pasti benar.');
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
     * @return array<string, list<array{id: string, name: string, academic_year_id: string}>> nama ternormalkan => daftar kelas
     */
    private function classroomIndex(string $schoolId): array
    {
        $index = [];

        Classroom::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->get(['id', 'name', 'academic_year_id'])
            ->each(function (Classroom $classroom) use (&$index): void {
                $index[$this->normalizeName($classroom->name)][] = [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'academic_year_id' => (string) $classroom->academic_year_id,
                ];
            });

        return $index;
    }

    /**
     * Indeks NISN hanya memuat siswa yang punya NISN, sementara daftar kedua
     * memuat semua siswa: yang ber-NISN kosong tidak bisa dicocokkan lewat
     * berkas, tapi tetap harus muncul di kelompok "tidak ada di berkas".
     *
     * @return array{0: array<string, list<array{id: string, nisn: string, nis: string, full_name: string, classroom: ?string, is_active: bool}>>, 1: list<array{id: string, nisn: string, nis: string, full_name: string, classroom: ?string, is_active: bool}>, 2: array<string, list<array{id: string, nisn: string, nis: string, full_name: string, classroom: ?string, is_active: bool}>>}
     */
    private function studentIndexes(string $schoolId): array
    {
        $byNisn = [];
        $byNis = [];
        $all = [];

        Student::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->with('classroom:id,name')
            ->orderBy('full_name')
            ->get(['id', 'nisn', 'nis', 'full_name', 'classroom_id', 'is_active'])
            ->each(function (Student $student) use (&$byNisn, &$byNis, &$all): void {
                $nisn = trim((string) $student->nisn);
                $nis = trim((string) $student->nis);

                $entry = [
                    'id' => $student->id,
                    'nisn' => $nisn,
                    'nis' => $nis,
                    'full_name' => (string) $student->full_name,
                    'classroom' => $student->classroom?->name,
                    'is_active' => (bool) $student->is_active,
                ];

                $all[] = $entry;

                if ($nisn !== '') {
                    $byNisn[$nisn][] = $entry;
                }

                if ($nis !== '') {
                    $byNis[$nis][] = $entry;
                }
            });

        return [$byNisn, $all, $byNis];
    }
}
