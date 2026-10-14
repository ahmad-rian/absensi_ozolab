<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\Penyelenggara\PesertaService;
use App\Services\Penyelenggara\RingkasanKehadiran;
use App\Support\XlsxDownload;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Laporan kehadiran program: XLSX/PDF per grup, per sesi, per peserta (spec 005).
 */
class LaporanProgramController extends Controller
{
    private const MAKS_HARI = 366;

    public function __construct(private RingkasanKehadiran $ringkasan, private PesertaService $peserta) {}

    public function index(Request $request, Program $program): Response
    {
        return Inertia::render('penyelenggara/program/laporan', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'starts_on' => $program->starts_on->toDateString(),
                'ends_on' => $program->ends_on->toDateString(),
            ],
            'grups' => Grup::where('program_id', $program->id)->orderBy('urutan')->orderBy('name')->get(['id', 'name']),
            'sesis' => Sesi::where('program_id', $program->id)->orderByDesc('tanggal')->orderBy('datang_mulai')->limit(200)->get(['id', 'name', 'tanggal']),
            'peserta' => $this->peserta->batasi(CardFormSubmission::where('program_id', $program->id)->whereNull('archived_at'), $request->user(), $program)
                ->get(['id', 'data'])->map(fn ($p) => ['id' => $p->id, 'nama' => $p->data['nama'] ?? '—'])->sortBy('nama')->values(),
        ]);
    }

    public function export(Request $request, Program $program): HttpResponse
    {
        $v = $request->validate([
            'mode' => ['required', Rule::in(['grup', 'sesi', 'peserta'])],
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'dari' => ['required_unless:mode,sesi', 'nullable', 'date'],
            'sampai' => ['required_unless:mode,sesi', 'nullable', 'date', 'after_or_equal:dari'],
            'grup' => ['nullable', Rule::exists('grups', 'id')->where('program_id', $program->id)],
            'sesi' => ['required_if:mode,sesi', 'nullable', Rule::exists('sesis', 'id')->where('program_id', $program->id)],
            'peserta' => ['required_if:mode,peserta', 'nullable', Rule::exists('card_form_submissions', 'id')->where('program_id', $program->id)],
        ], ['sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);

        if ($v['mode'] !== 'sesi' && CarbonImmutable::parse($v['dari'])->diffInDays(CarbonImmutable::parse($v['sampai'])) > self::MAKS_HARI) {
            throw ValidationException::withMessages(['sampai' => 'Rentang laporan maksimal 1 tahun. Persempit tanggalnya.']);
        }

        return match ($v['mode']) {
            'grup' => $this->perGrup($request, $program, $v),
            'sesi' => $this->perSesi($request, $program, $v),
            'peserta' => $this->perPeserta($request, $program, $v),
        };
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private function perGrup(Request $request, Program $program, array $v): HttpResponse
    {
        $user = $request->user();
        $m = $this->ringkasan->matriks($program, substr($v['dari'], 0, 10), substr($v['sampai'], 0, 10), $user, $v['grup'] ?? null);
        $kolom = $this->kolomIdentitas($program);
        $label = fn (Sesi $s) => CarbonImmutable::parse($s->tanggal)->format('d/m').' '.$s->name;
        $cache = [];

        // Ringkasan dihitung dari matriks yang sama — tanpa query per sesi.
        $ringkasanRows = $m['sesis']->map(function (Sesi $s) use ($m, $program, &$cache) {
            $hitung = array_fill_keys(RingkasanKehadiran::STATUS, 0);
            $target = 0;
            foreach ($m['peserta'] as $p) {
                if ($this->ringkasan->termasukTarget($program, $s, $p, $cache)) {
                    $target++;
                    $hitung[($m['status'][$s->id][$p->id] ?? null)?->status ?? 'BELUM']++;
                }
            }

            return [$s->name, $s->tanggal, $target, ...array_values($hitung)];
        })->all();

        $kelompok = $this->kelompokPerGrup($program, $m['peserta'], $v['grup'] ?? null);

        if ($v['format'] === 'pdf') {
            $bagian = collect($kelompok)->map(fn ($ps) => collect($ps)->map(function ($p) use ($m, $program, &$cache) {
                $hitung = array_fill_keys(RingkasanKehadiran::STATUS, 0);
                foreach ($m['sesis'] as $s) {
                    if ($this->ringkasan->termasukTarget($program, $s, $p, $cache)) {
                        $hitung[($m['status'][$s->id][$p->id] ?? null)?->status ?? 'BELUM']++;
                    }
                }

                return ['nama' => $p->data['nama'] ?? '—', 'hitung' => $hitung];
            }));

            return $this->pdf('Laporan per grup', $program, $v, ['bagian' => $bagian, 'mode' => 'grup']);
        }

        $sheets = ['Ringkasan' => ['header' => ['Sesi', 'Tanggal', 'Target', ...RingkasanKehadiran::STATUS], 'rows' => $ringkasanRows]];
        $terpakai = ['Ringkasan'];
        foreach ($kelompok as $nama => $ps) {
            $sheet = XlsxDownload::namaSheet($nama, $terpakai);
            $terpakai[] = $sheet;
            $sheets[$sheet] = [
                'header' => ['Nama', ...array_column($kolom, 'label'), ...$m['sesis']->map($label)->all()],
                'rows' => collect($ps)->map(fn ($p) => [
                    $p->data['nama'] ?? '—',
                    ...array_map(fn ($k) => (string) ($p->data[$k['key']] ?? ''), $kolom),
                    ...$m['sesis']->map(fn (Sesi $s) => $this->ringkasan->termasukTarget($program, $s, $p, $cache)
                        ? (($m['status'][$s->id][$p->id] ?? null)?->status ?? 'BELUM')
                        : '-')->all(),
                ])->all(),
            ];
        }

        return XlsxDownload::sheets($this->namaBerkas($program, 'grup', 'xlsx'), $sheets);
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private function perSesi(Request $request, Program $program, array $v): HttpResponse
    {
        $sesi = Sesi::findOrFail($v['sesi']);
        $target = $this->ringkasan->target($program, $sesi, $request->user(), $v['grup'] ?? null);
        $hadir = Kehadiran::where('sesi_id', $sesi->id)->get()->keyBy('card_form_submission_id');
        $rows = $target->sortBy(fn ($p) => $p->data['nama'] ?? '')->map(function ($p) use ($hadir) {
            $k = $hadir->get($p->id);

            return [
                $p->data['nama'] ?? '—',
                $p->grups->pluck('name')->implode(', '),
                $k->status ?? 'BELUM',
                $k?->datang_at?->format('H:i') ?? '',
                $k?->pulang_at?->format('H:i') ?? '',
                $k->sumber ?? '',
                $k->catatan ?? '',
            ];
        })->values()->all();
        $header = ['Nama', 'Grup', 'Status', 'Datang', 'Pulang', 'Sumber', 'Catatan'];

        if ($v['format'] === 'pdf') {
            return $this->pdf("Laporan sesi {$sesi->name} ({$sesi->tanggal})", $program, $v, ['mode' => 'tabel', 'header' => $header, 'rows' => $rows]);
        }

        return XlsxDownload::sheets($this->namaBerkas($program, 'sesi', 'xlsx'), [XlsxDownload::namaSheet($sesi->name, []) => ['header' => $header, 'rows' => $rows]]);
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private function perPeserta(Request $request, Program $program, array $v): HttpResponse
    {
        $p = CardFormSubmission::with('grups:id,name')->findOrFail($v['peserta']);
        abort_unless($this->peserta->bolehLihat($request->user(), $p), 404);

        $sesis = Sesi::where('program_id', $program->id)->where('tanggal', '>=', substr($v['dari'], 0, 10))->where('tanggal', '<=', substr($v['sampai'], 0, 10))
            ->orderBy('tanggal')->orderBy('datang_mulai')->get();
        $hadir = Kehadiran::where('card_form_submission_id', $p->id)->whereIn('sesi_id', $sesis->pluck('id'))->get()->keyBy('sesi_id');
        $cache = [];
        $rows = $sesis->filter(fn (Sesi $s) => $this->ringkasan->termasukTarget($program, $s, $p, $cache))->map(function (Sesi $s) use ($hadir) {
            $k = $hadir->get($s->id);

            return [
                $s->tanggal,
                $s->name,
                $k->status ?? 'BELUM',
                $k?->datang_at?->format('H:i') ?? '',
                $k?->pulang_at?->format('H:i') ?? '',
                $k->sumber ?? '',
                $k->catatan ?? '',
            ];
        })->values()->all();
        $header = ['Tanggal', 'Sesi', 'Status', 'Datang', 'Pulang', 'Sumber', 'Catatan'];
        $nama = $p->data['nama'] ?? 'Peserta';

        if ($v['format'] === 'pdf') {
            return $this->pdf("Laporan {$nama}", $program, $v, ['mode' => 'tabel', 'header' => $header, 'rows' => $rows]);
        }

        return XlsxDownload::sheets($this->namaBerkas($program, Str::slug($nama), 'xlsx'), [XlsxDownload::namaSheet($nama, []) => ['header' => $header, 'rows' => $rows]]);
    }

    /**
     * Peserta dikelompokkan per id grup (bukan nama — dua "Regu 1" di rombongan
     * berbeda tetap terpisah) dengan label jalur "Induk › Anak". Peserta di
     * beberapa grup muncul di tiap grupnya.
     *
     * @param  Collection<int, CardFormSubmission>  $peserta
     * @return array<string, array<int, CardFormSubmission>>
     */
    private function kelompokPerGrup(Program $program, Collection $peserta, ?string $grupFilter): array
    {
        $grups = Grup::where('program_id', $program->id)->get(['id', 'name', 'parent_id'])->keyBy('id');
        $dalamFilter = $grupFilter ? array_flip(Grup::denganTurunan($program->id, [$grupFilter])) : null;
        $jalur = function (string $id) use ($grups): string {
            $nama = [];
            for ($g = $grups->get($id); $g; $g = $g->parent_id ? $grups->get($g->parent_id) : null) {
                array_unshift($nama, $g->name);
            }

            return implode(' › ', $nama);
        };

        $kelompok = [];
        foreach ($peserta as $p) {
            $ids = $p->grups->pluck('id')->filter(fn ($id) => $dalamFilter === null || isset($dalamFilter[$id]));
            if ($ids->isEmpty()) {
                $kelompok['Tanpa Grup'][] = $p;
            }
            foreach ($ids as $id) {
                $kelompok[$jalur($id)][] = $p;
            }
        }
        ksort($kelompok, SORT_NATURAL | SORT_FLAG_CASE);

        return $kelompok;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function kolomIdentitas(Program $program): array
    {
        $kategori = Kategori::find($program->kategori_id);

        return collect($kategori?->visibleFields() ?? [])
            ->reject(fn ($f) => $f['type'] === 'photo' || $f['key'] === 'nama')
            ->map(fn ($f) => ['key' => $f['key'], 'label' => $f['label']])
            ->values()->all();
    }

    /**
     * @param  array<string, mixed>  $v
     * @param  array<string, mixed>  $data
     */
    private function pdf(string $judul, Program $program, array $v, array $data): HttpResponse
    {
        return Pdf::loadView('pdf.kehadiran-program', [
            'judul' => $judul,
            'penyelenggara' => app('currentPenyelenggara')->name,
            'program' => $program->name,
            'dari' => $v['dari'] ?? null,
            'sampai' => $v['sampai'] ?? null,
            'status' => RingkasanKehadiran::STATUS,
            ...$data,
        ])->setPaper('a4', 'landscape')->download($this->namaBerkas($program, $v['mode'], 'pdf'));
    }

    private function namaBerkas(Program $program, string $mode, string $ext): string
    {
        return 'kehadiran-'.Str::slug($program->name).'-'.$mode.'.'.$ext;
    }
}
