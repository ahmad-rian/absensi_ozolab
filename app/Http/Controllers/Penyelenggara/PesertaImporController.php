<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\CardFormSubmission;
use App\Models\PesertaImport;
use App\Models\Program;
use App\Services\Penyelenggara\PesertaImportParser;
use App\Services\Penyelenggara\PesertaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Impor peserta dua langkah: pratinjau (tidak menyimpan apa pun) lalu simpan.
 */
class PesertaImporController extends Controller
{
    private const CACHE_PREFIX = 'impor-peserta:';

    public function pratinjau(Request $request, Program $program, PesertaImportParser $parser): Response
    {
        abort_if($program->isArchived(), 403, 'Program sudah diarsip dan hanya bisa dilihat.');

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ], ['file.mimes' => 'Gunakan berkas .xlsx atau .csv.', 'file.max' => 'Ukuran berkas maksimal 10 MB.']);

        $file = $validated['file'];
        $hasil = $parser->parse($file->getRealPath(), $file->getClientOriginalExtension() ?: 'csv', $program);

        $kunci = (string) Str::ulid();
        Cache::put(self::CACHE_PREFIX.$kunci, [
            'program_id' => $program->id,
            'penyelenggara_id' => $program->penyelenggara_id,
            'filename' => $file->getClientOriginalName(),
            ...$hasil,
        ], now()->addMinutes(30));
        $request->session()->put('impor_peserta_kunci', $kunci);

        return Inertia::render('penyelenggara/peserta/impor', [
            'program' => ['id' => $program->id, 'name' => $program->name],
            'pratinjau' => [
                'kunci' => $kunci,
                'filename' => $file->getClientOriginalName(),
                'total' => $hasil['total'],
                'valid' => count($hasil['rows']),
                'gagal' => count($hasil['errors']),
                'errors' => array_slice($hasil['errors'], 0, 200),
                'contoh' => array_slice(array_column($hasil['rows'], 'data'), 0, 10),
            ],
        ]);
    }

    public function simpan(Request $request, Program $program, string $kunci, PesertaService $peserta): RedirectResponse
    {
        abort_if($program->isArchived(), 403, 'Program sudah diarsip dan hanya bisa dilihat.');

        // pull, bukan get: klik ganda pada "Simpan" tidak boleh mengimpor dua kali.
        $staging = Cache::pull(self::CACHE_PREFIX.$kunci);

        abort_unless($staging && $staging['program_id'] === $program->id, 410, 'Pratinjau impor sudah kedaluwarsa atau sudah disimpan. Unggah ulang berkasnya.');

        try {
            $this->impor($request, $program, $staging, $peserta);
        } catch (Throwable $e) {
            // Gagal (mis. kuota penuh): staging dikembalikan supaya bisa dicoba lagi.
            Cache::put(self::CACHE_PREFIX.$kunci, $staging, now()->addMinutes(30));

            throw $e;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => count($staging['rows']).' peserta diimpor; '.count($staging['errors']).' baris dilewati.']);

        return to_route('penyelenggara.peserta.index', $program);
    }

    /**
     * @param  array{rows: array<int, array{data: array<string, mixed>, grup_id: ?string}>, errors: array<int, mixed>, total: int, filename: string}  $staging
     */
    private function impor(Request $request, Program $program, array $staging, PesertaService $peserta): void
    {
        $peserta->pastikanKuota(app('currentPenyelenggara'), count($staging['rows']));
        $formId = $program->card_form_id;

        DB::transaction(function () use ($staging, $formId, $program, $peserta, $request) {
            foreach ($staging['rows'] as $row) {
                $baru = CardFormSubmission::create([
                    'card_form_id' => $formId,
                    'program_id' => $program->id,
                    'penyelenggara_id' => $program->penyelenggara_id,
                    'data' => $row['data'],
                    'status' => 'draft',
                ]);

                if ($row['grup_id']) {
                    $peserta->sinkronGrup($baru, [$row['grup_id']], $request->user());
                }
            }

            PesertaImport::create([
                'penyelenggara_id' => $program->penyelenggara_id,
                'program_id' => $program->id,
                'filename' => $staging['filename'],
                'total' => $staging['total'],
                'berhasil' => count($staging['rows']),
                'gagal' => count($staging['errors']),
                'errors' => $staging['errors'],
                'oleh' => $request->user()->id,
            ]);
        });
    }
}
