<?php

namespace App\Services\Penyelenggara;

use App\Models\Grup;
use App\Models\Program;
use App\Services\Import\Concerns\ReadsSpreadsheetRows;

/**
 * Baca berkas impor peserta dan cocokkan ke skema kategori — persis, tanpa tebakan.
 *
 * Header dicocokkan dengan label atau kode kolom (abaikan huruf besar/spasi);
 * kolom "Grup" dicocokkan dengan nama grup program secara persis.
 */
class PesertaImportParser
{
    use ReadsSpreadsheetRows;

    private const MAX_ROWS = 5000;

    /**
     * @return array{rows: array<int, array{baris: int, data: array<string, string|null>, grup_id: ?string}>, errors: array<int, array{baris: int, alasan: string}>, total: int}
     */
    public function parse(string $path, string $extension, Program $program): array
    {
        $kategori = $program->kategori()->first();
        $fields = collect($kategori?->visibleFields() ?? [])
            ->reject(fn (array $f) => $f['type'] === 'photo')
            ->values();

        $byHeader = [];
        foreach ($fields as $f) {
            $byHeader[$this->normalizeHeader($f['label'])] = $f;
            $byHeader[$this->normalizeHeader($f['key'])] = $f;
        }

        $grups = Grup::where('program_id', $program->id)->get(['id', 'name'])
            ->keyBy(fn (Grup $g) => $this->normalizeName($g->name));

        $reader = $this->readerFor($path, $extension);
        $reader->open($path);

        $rows = [];
        $errors = [];
        $seen = [];
        $total = 0;
        $map = null;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $values = $this->rowValues($row);

                    if ($map === null) {
                        $map = $this->petaHeader($values, $byHeader, $kategori?->istilah_grup);

                        continue;
                    }
                    if ($this->isBlank($values)) {
                        continue;
                    }
                    if (++$total > self::MAX_ROWS) {
                        $errors[] = ['baris' => $index, 'alasan' => 'Melebihi batas '.self::MAX_ROWS.' baris per impor.'];
                        break;
                    }

                    [$data, $grupName, $alasan] = $this->bacaBaris($values, $map, $fields->all());

                    $grupId = null;
                    if ($grupName !== null && $grupName !== '') {
                        $grupId = $grups->get($this->normalizeName($grupName))?->id;
                        if (! $grupId) {
                            $alasan[] = "Grup \"{$grupName}\" tidak ditemukan di program ini (nama harus sama persis).";
                        }
                    }

                    $sidik = md5(json_encode([$data, $grupName]));
                    if (isset($seen[$sidik])) {
                        $alasan[] = "Baris kembar dengan baris {$seen[$sidik]}.";
                    }
                    $seen[$sidik] ??= $index;

                    if ($alasan) {
                        $errors[] = ['baris' => $index, 'alasan' => implode(' ', $alasan)];

                        continue;
                    }

                    $rows[] = ['baris' => $index, 'data' => $data, 'grup_id' => $grupId];
                }
                break; // hanya sheet pertama
            }
        } finally {
            $reader->close();
        }

        return ['rows' => $rows, 'errors' => $errors, 'total' => $total];
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, array<string, mixed>>  $byHeader
     * @return array{fields: array<int, array<string, mixed>>, grup: ?int}
     */
    private function petaHeader(array $headers, array $byHeader, ?string $istilahGrup = null): array
    {
        $map = ['fields' => [], 'grup' => null];

        foreach ($headers as $i => $header) {
            $norm = $this->normalizeHeader($header);
            // Istilah grup kategori (mis. "Halaqah", "Bus") juga dikenali.
            $namaGrup = array_filter(['grup', 'rombongan', 'kelompok', $istilahGrup ? $this->normalizeHeader($istilahGrup) : null]);
            if (in_array($norm, $namaGrup, true)) {
                $map['grup'] = $i;

                continue;
            }
            if (isset($byHeader[$norm])) {
                $map['fields'][$i] = $byHeader[$norm];
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $values
     * @param  array{fields: array<int, array<string, mixed>>, grup: ?int}  $map
     * @param  array<int, array<string, mixed>>  $fields
     * @return array{0: array<string, string|null>, 1: ?string, 2: list<string>}
     */
    private function bacaBaris(array $values, array $map, array $fields): array
    {
        $data = [];
        $alasan = [];
        $kolomAda = [];

        foreach ($map['fields'] as $i => $field) {
            $kolomAda[$field['key']] = true;
            $nilai = trim($values[$i] ?? '');
            $data[$field['key']] = $nilai === '' ? null : $nilai;

            if ($nilai === '') {
                continue;
            }

            $salah = match ($field['type']) {
                'number' => is_numeric($nilai) ? null : 'harus angka',
                'date' => strtotime($nilai) !== false ? null : 'harus tanggal (YYYY-MM-DD)',
                'select' => in_array($nilai, $field['options'] ?? [], true) ? null : 'harus salah satu dari: '.implode(', ', $field['options'] ?? []),
                default => mb_strlen($nilai) > 1000 ? 'terlalu panjang' : null,
            };
            if ($salah) {
                $alasan[] = "{$field['label']} \"{$nilai}\" {$salah}.";
            }
        }

        foreach ($fields as $field) {
            if (! empty($field['required']) && empty($data[$field['key']] ?? null)) {
                $alasan[] = "{$field['label']} wajib diisi.";
            }
        }

        $grup = $map['grup'] !== null ? trim($values[$map['grup']] ?? '') : null;

        return [$data, $grup, $alasan];
    }
}
