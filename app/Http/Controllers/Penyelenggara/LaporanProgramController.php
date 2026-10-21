<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Libur;
use App\Models\Program;
use App\Models\Sesi;
use App\Models\User;
use App\Services\Penyelenggara\PesertaService;
use App\Services\Penyelenggara\RingkasanKehadiran;
use App\Support\FilterKolomPeserta;
use App\Support\SchoolTime;
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

    private const TANPA_GRUP = '(Tanpa grup)';

    public function __construct(private RingkasanKehadiran $ringkasan, private PesertaService $peserta) {}

    public function index(Request $request, Program $program): Response
    {
        $libur = Libur::where('program_id', $program->id)->pluck('tanggal')->all();
        $dari = $this->tanggalAtau($request->query('dari'), $program->starts_on->toDateString());
        $sampai = $this->tanggalAtau($request->query('sampai'), min($program->ends_on->toDateString(), SchoolTime::todayString()));

        return Inertia::render('penyelenggara/program/laporan', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'starts_on' => $program->starts_on->toDateString(),
                'ends_on' => $program->ends_on->toDateString(),
            ],
            'grups' => Grup::where('program_id', $program->id)->orderBy('urutan')->orderBy('name')->get(['id', 'name']),
            // Sesi di hari libur tidak dilaporkan — sama dengan dasbor.
            'sesis' => Sesi::where('program_id', $program->id)->when($libur, fn ($q) => $q->whereNotIn('tanggal', $libur))
                ->orderByDesc('tanggal')->orderBy('datang_mulai')->limit(200)->get(['id', 'name', 'tanggal']),
            // Hanya nama — kolom `data` lengkap tidak perlu dikirim untuk dropdown.
            'peserta' => $this->peserta->batasi(CardFormSubmission::where('program_id', $program->id)->whereNull('archived_at'), $request->user(), $program)
                ->get(['id', 'data->nama as nama'])->map(fn ($p) => ['id' => $p->id, 'nama' => $p->nama ?: '—'])->sortBy('nama')->values(),
            'kecamatan' => $this->opsiKecamatan($request, $program),
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'ringkasanKecamatan' => Inertia::defer(fn () => $this->rekapKecamatan($program, $dari, $sampai, $request->user())),
        ]);
    }

    public function export(Request $request, Program $program): HttpResponse
    {
        $v = $request->validate([
            'mode' => ['required', Rule::in(['grup', 'sesi', 'peserta', 'kecamatan'])],
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'dari' => ['required_unless:mode,sesi', 'nullable', 'date_format:Y-m-d'],
            'sampai' => ['required_unless:mode,sesi', 'nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'kecamatan' => ['nullable', 'string', 'max:120'],
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
            'kecamatan' => $this->perKecamatan($request, $program, $v),
        };
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private function perGrup(Request $request, Program $program, array $v): HttpResponse
    {
        $user = $request->user();
        $m = $this->ringkasan->matriks($program, $v['dari'], $v['sampai'], $user, $v['grup'] ?? null);
        $m['peserta'] = $this->saringKecamatan($program, $m['peserta'], $v['kecamatan'] ?? null);
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
            $bagian = collect($kelompok)->map(fn ($ps) => collect($ps)->map(fn ($p) => $this->barisHitung($program, $m, $p, $kolom, $cache)));

            return $this->pdf('Laporan per grup', $program, $v, ['bagian' => $bagian, 'mode' => 'grup', 'kolom' => $kolom]);
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
        if (Libur::where('program_id', $program->id)->where('tanggal', $sesi->tanggal)->exists()) {
            throw ValidationException::withMessages(['sesi' => 'Sesi ini jatuh pada hari libur program, jadi tidak dilaporkan.']);
        }
        $target = $this->saringKecamatan($program, $this->ringkasan->target($program, $sesi, $request->user(), $v['grup'] ?? null), $v['kecamatan'] ?? null);
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

        $libur = Libur::where('program_id', $program->id)->pluck('tanggal')->all();
        $sesis = Sesi::where('program_id', $program->id)->where('tanggal', '>=', $v['dari'])->where('tanggal', '<=', $v['sampai'])
            ->when($libur, fn ($q) => $q->whereNotIn('tanggal', $libur))
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
                // Label dalam kurung supaya tidak bisa bertabrakan dengan grup
                // sungguhan yang kebetulan bernama "Tanpa Grup".
                $kelompok[self::TANPA_GRUP][] = $p;
            }
            foreach ($ids as $id) {
                $kelompok[$jalur($id)][] = $p;
            }
        }
        ksort($kelompok, SORT_NATURAL | SORT_FLAG_CASE);

        return $kelompok;
    }

    /**
     * Ringkasan per kecamatan + rincian peserta tiap kecamatan.
     *
     * @param  array<string, mixed>  $v
     */
    private function perKecamatan(Request $request, Program $program, array $v): HttpResponse
    {
        $m = $this->ringkasan->matriks($program, $v['dari'], $v['sampai'], $request->user(), $v['grup'] ?? null);
        $m['peserta'] = $this->saringKecamatan($program, $m['peserta'], $v['kecamatan'] ?? null);
        $kolom = $this->kolomIdentitas($program);
        $kunci = $this->kunciKecamatan($program);
        $cache = [];

        $kelompok = [];
        foreach ($m['peserta'] as $p) {
            $kelompok[FilterKolomPeserta::kecamatanPeserta($p->data, $kunci)][] = $this->barisHitung($program, $m, $p, $kolom, $cache);
        }
        uksort($kelompok, fn ($a, $b) => self::urutKecamatan($a, $b));
        $ringkasan = $this->ringkasDariKelompok($kelompok);

        if ($v['format'] === 'pdf') {
            return $this->pdf('Laporan per kecamatan', $program, $v, [
                'mode' => 'grup', 'kolom' => $kolom, 'ringkasan' => $ringkasan,
                'bagian' => collect($kelompok)->map(fn ($rows) => collect($rows)),
            ]);
        }

        $sheets = ['Ringkasan per Kecamatan' => [
            'header' => ['Kecamatan', 'Peserta', ...RingkasanKehadiran::STATUS],
            'rows' => array_map(fn (array $r) => [$r['kecamatan'], $r['peserta'], ...array_values($r['hitung'])], $ringkasan),
        ]];
        $terpakai = ['Ringkasan per Kecamatan'];
        foreach ($kelompok as $nama => $rows) {
            $sheet = XlsxDownload::namaSheet((string) $nama, $terpakai);
            $terpakai[] = $sheet;
            $sheets[$sheet] = [
                'header' => ['Nama', ...array_column($kolom, 'label'), ...RingkasanKehadiran::STATUS],
                'rows' => array_map(fn (array $r) => [$r['nama'], ...$r['identitas'], ...array_values($r['hitung'])], $rows),
            ];
        }

        return XlsxDownload::sheets($this->namaBerkas($program, 'kecamatan', 'xlsx'), $sheets);
    }

    /**
     * Hitungan status satu peserta di seluruh sesi matriks yang menjadi targetnya.
     *
     * @param  array{sesis: Collection<int, Sesi>, status: array<string, array<string, Kehadiran>>}  $m
     * @param  array<int, array{key: string, label: string}>  $kolom
     * @param  array<string, array<int, string>>  $cache
     * @return array{nama: string, identitas: array<int, string>, hitung: array<string, int>}
     */
    private function barisHitung(Program $program, array $m, CardFormSubmission $p, array $kolom, array &$cache): array
    {
        $hitung = array_fill_keys(RingkasanKehadiran::STATUS, 0);
        foreach ($m['sesis'] as $s) {
            if ($this->ringkasan->termasukTarget($program, $s, $p, $cache)) {
                $hitung[($m['status'][$s->id][$p->id] ?? null)?->status ?? 'BELUM']++;
            }
        }

        return [
            'nama' => $p->data['nama'] ?? '—',
            'identitas' => array_map(fn ($k) => (string) ($p->data[$k['key']] ?? ''), $kolom),
            'hitung' => $hitung,
        ];
    }

    /**
     * @param  array<string, array<int, array{hitung: array<string, int>}>>  $kelompok
     * @return array<int, array{kecamatan: string, peserta: int, hitung: array<string, int>}>
     */
    private function ringkasDariKelompok(array $kelompok): array
    {
        $hasil = [];
        foreach ($kelompok as $nama => $rows) {
            $hitung = array_fill_keys(RingkasanKehadiran::STATUS, 0);
            foreach ($rows as $r) {
                foreach ($r['hitung'] as $status => $n) {
                    $hitung[$status] += $n;
                }
            }
            $hasil[] = ['kecamatan' => (string) $nama, 'peserta' => count($rows), 'hitung' => $hitung];
        }

        return $hasil;
    }

    /**
     * Rekap per kecamatan untuk halaman laporan (rentang tanggal terpilih).
     *
     * @return array<int, array<string, int|string>>
     */
    private function rekapKecamatan(Program $program, string $dari, string $sampai, User $user): array
    {
        $m = $this->ringkasan->matriks($program, $dari, $sampai, $user);
        $kunci = $this->kunciKecamatan($program);
        $cache = [];
        $kelompok = [];
        foreach ($m['peserta'] as $p) {
            $kelompok[FilterKolomPeserta::kecamatanPeserta($p->data, $kunci)][] = $this->barisHitung($program, $m, $p, [], $cache);
        }
        uksort($kelompok, fn ($a, $b) => self::urutKecamatan($a, $b));

        return array_map(fn (array $r) => ['kecamatan' => $r['kecamatan'], 'peserta' => $r['peserta'], ...array_change_key_case($r['hitung'], CASE_LOWER)], $this->ringkasDariKelompok($kelompok));
    }

    /**
     * Kecamatan yang benar-benar dimiliki peserta (dalam cakupan pengguna).
     *
     * @return array<int, string>
     */
    private function opsiKecamatan(Request $request, Program $program): array
    {
        $kunci = $this->kunciKecamatan($program);
        $nama = $this->peserta->batasi(CardFormSubmission::where('program_id', $program->id)->whereNull('archived_at'), $request->user(), $program)
            ->get(['id', 'data'])->map(fn ($p) => FilterKolomPeserta::kecamatanPeserta($p->data, $kunci))->unique()->values()->all();
        usort($nama, fn ($a, $b) => self::urutKecamatan($a, $b));

        return $nama;
    }

    /**
     * @param  Collection<int, CardFormSubmission>  $peserta
     * @return Collection<int, CardFormSubmission>
     */
    private function saringKecamatan(Program $program, Collection $peserta, ?string $kecamatan): Collection
    {
        if ($kecamatan === null || $kecamatan === '') {
            return $peserta;
        }
        $kunci = $this->kunciKecamatan($program);

        return $peserta->filter(fn ($p) => FilterKolomPeserta::kecamatanPeserta($p->data, $kunci) === $kecamatan)->values();
    }

    private function kunciKecamatan(Program $program): ?string
    {
        return FilterKolomPeserta::kolomKecamatan(Kategori::find($program->kategori_id)?->visibleFields() ?? []);
    }

    /** "Tanpa kecamatan" selalu di akhir, sisanya alfabetis. */
    private static function urutKecamatan(string $a, string $b): int
    {
        return [$a === FilterKolomPeserta::TANPA_KECAMATAN, $a] <=> [$b === FilterKolomPeserta::TANPA_KECAMATAN, $b];
    }

    private function tanggalAtau(mixed $nilai, string $bawaan): string
    {
        return is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) ? $nilai : $bawaan;
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
