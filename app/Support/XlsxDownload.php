<?php

namespace App\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Penulis XLSX untuk seluruh ekspor aplikasi.
 *
 * Menggantikan `fputcsv`: Excel menebak tipe kolom pada CSV, jadi NIS
 * `0071234567` kehilangan angka nol depannya dan NIS panjang berubah menjadi
 * notasi ilmiah. XLSX menyimpan tipe tiap sel secara eksplisit, jadi tebakan
 * itu tidak pernah terjadi.
 */
class XlsxDownload
{
    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array<int, mixed>>  $preamble  blok identitas/ringkasan sebelum tabel
     */
    public static function make(string $filename, array $header, array $rows, array $preamble = []): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        $writer = new Writer;
        $writer->openToFile($path);

        $tebal = (new Style)->withFontBold(true);

        foreach ($preamble as $baris) {
            $writer->addRow(self::row($baris));
        }

        $writer->addRow(self::row($header, $tebal));

        foreach ($rows as $baris) {
            $writer->addRow(self::row($baris));
        }

        $writer->close();

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * @param  array<int, mixed>  $nilai
     */
    private static function row(array $nilai, ?Style $style = null): Row
    {
        return new Row(array_values(array_map(
            static fn (mixed $sel): Cell => self::cell($sel, $style),
            $nilai,
        )));
    }

    /**
     * Bangun sel secara eksplisit, JANGAN lewat `Cell::fromValue()`.
     *
     * `Cell::fromValue()` memeriksa `'=' === $value[0]` dan mengembalikan
     * `FormulaCell` — nilai yang datang dari pendaftaran publik akan ditulis
     * sebagai rumus sungguhan dan dieksekusi saat berkasnya dibuka. Itu temuan
     * M-4 di SECURITY-AUDIT.md, dulu ditangkal dengan melarikan awalan pada
     * jalur CSV. Di sini penangkalnya adalah tipe selnya sendiri: teks tetap
     * teks apa pun karakter pertamanya.
     *
     * Angka sengaja tetap `NumericCell` supaya kolom hitungan bisa dijumlah di
     * Excel — nilai yang harus tetap tampil apa adanya (NIS, persentase)
     * dikirim pemanggilnya sebagai string.
     */
    private static function cell(mixed $nilai, ?Style $style = null): Cell
    {
        if (is_int($nilai) || is_float($nilai)) {
            return new NumericCell($nilai, $style);
        }

        return new StringCell((string) ($nilai ?? ''), $style);
    }
}
