<?php

namespace App\Services\Import\Concerns;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Pembacaan berkas xlsx/csv yang dipakai bersama oleh parser impor.
 *
 * Dipisah supaya kuirk berkas Excel Indonesia (delimiter titik koma, NIS yang
 * berubah jadi float, BOM di header) hanya diperbaiki di satu tempat.
 */
trait ReadsSpreadsheetRows
{
    /**
     * @throws RuntimeException Jika ekstensinya tidak didukung.
     */
    protected function readerFor(string $path, string $extension): ReaderInterface
    {
        return match (mb_strtolower($extension)) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader(new CsvOptions(FIELD_DELIMITER: $this->detectDelimiter($path))),
            default => throw new RuntimeException('Format berkas ".'.$extension.'" tidak didukung. Gunakan .xlsx atau .csv.'),
        };
    }

    /**
     * Excel versi Indonesia menyimpan CSV dengan titik koma; tebak dari baris
     * header supaya pengguna tidak perlu menyimpan ulang berkasnya.
     */
    protected function detectDelimiter(string $path): string
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            return ',';
        }

        $firstLine = fgets($handle) ?: '';
        fclose($handle);

        $counts = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];

        arsort($counts);

        $delimiter = array_key_first($counts);

        return $counts[$delimiter] > 0 ? $delimiter : ',';
    }

    /**
     * @return list<string>
     */
    protected function rowValues(Row $row): array
    {
        return array_map(function (mixed $value): string {
            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            // NIS/NISN yang tersimpan sebagai angka di xlsx tidak boleh berubah
            // jadi notasi ilmiah atau berekor ",0".
            if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
                return number_format($value, 0, '.', '');
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return (string) $value;
        }, $row->toArray());
    }

    /**
     * @param  list<string>  $values
     */
    protected function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = str_replace("\u{FEFF}", '', $header);

        return preg_replace('/[\s_\-.]+/u', '', mb_strtolower(trim($header))) ?? '';
    }

    protected function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($name))) ?? '';
    }
}
