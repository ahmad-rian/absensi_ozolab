<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Libur;
use App\Models\Program;
use App\Models\Sesi;
use App\Models\SesiPola;
use App\Services\Penyelenggara\SesiGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jadwal sesi dan hari libur program (spec 003).
 */
class JadwalController extends Controller
{
    public function __construct(private SesiGenerator $generator) {}

    public function index(Program $program): Response
    {
        $kategori = Kategori::find($program->kategori_id);

        return Inertia::render('penyelenggara/program/jadwal', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'starts_on' => $program->starts_on->toDateString(),
                'ends_on' => $program->ends_on->toDateString(),
                'timezone' => $program->timezone,
                'archived' => $program->isArchived(),
                'istilah_grup' => $kategori?->istilah_grup ?? 'Grup',
                'fitur' => ['terlambat' => (bool) $kategori?->fitur('terlambat'), 'pulang' => (bool) $kategori?->fitur('pulang')],
            ],
            'polas' => SesiPola::where('program_id', $program->id)->with('grup:id,name')->orderBy('datang_mulai')->get()
                ->map(fn (SesiPola $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'pengulangan' => $p->pengulangan,
                    'hari' => $p->hari ?? [],
                    'tanggal' => $p->tanggal,
                    'tanggal_mulai' => $p->tanggal_mulai,
                    'tanggal_selesai' => $p->tanggal_selesai,
                    'grup_id' => $p->grup_id,
                    'grup' => $p->grup?->name,
                    ...array_map(fn (?string $v) => $v ? substr($v, 0, 5) : null, $p->jendela()),
                ]),
            'liburs' => Libur::where('program_id', $program->id)->orderBy('tanggal')->get(['id', 'tanggal', 'keterangan']),
            'kalender' => Sesi::where('program_id', $program->id)->with('grup:id,name')->orderBy('tanggal')->orderBy('datang_mulai')->get()
                ->groupBy('tanggal')
                ->map(fn ($sesis, $tanggal) => [
                    'tanggal' => $tanggal,
                    'sesis' => $sesis->map(fn (Sesi $s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'grup' => $s->grup?->name,
                        'datang' => substr($s->datang_mulai, 0, 5).'–'.substr($s->datang_selesai, 0, 5),
                        'pulang' => $s->pulang_mulai ? substr($s->pulang_mulai, 0, 5).'–'.substr($s->pulang_selesai, 0, 5) : null,
                    ])->values(),
                ])->values(),
            'grups' => Grup::where('program_id', $program->id)->orderBy('urutan')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Program $program): RedirectResponse
    {
        $this->bolehUbah($program);
        $this->generator->simpan($program, $this->validated($request, $program));

        return to_route('penyelenggara.jadwal.index', $program);
    }

    public function update(Request $request, SesiPola $pola): RedirectResponse
    {
        $program = Program::findOrFail($pola->program_id);
        $this->bolehUbah($program);

        $this->generator->simpan($program, $this->validated($request, $program), $pola, $request->boolean('termasuk_hari_ini'));

        return to_route('penyelenggara.jadwal.index', $program);
    }

    public function destroy(SesiPola $pola): RedirectResponse
    {
        $program = Program::findOrFail($pola->program_id);
        $this->bolehUbah($program);
        $this->generator->hapus($program, $pola);

        return to_route('penyelenggara.jadwal.index', $program);
    }

    public function liburStore(Request $request, Program $program): RedirectResponse
    {
        $this->bolehUbah($program);

        $validated = $request->validate([
            'tanggal' => ['required', 'date', 'after_or_equal:'.$program->starts_on->toDateString(), 'before_or_equal:'.$program->ends_on->toDateString()],
            'keterangan' => ['nullable', 'string', 'max:120'],
        ], ['tanggal.after_or_equal' => 'Tanggal libur harus di dalam rentang program.', 'tanggal.before_or_equal' => 'Tanggal libur harus di dalam rentang program.']);

        Libur::updateOrCreate(
            ['program_id' => $program->id, 'tanggal' => substr($validated['tanggal'], 0, 10)],
            ['penyelenggara_id' => $program->penyelenggara_id, 'keterangan' => $validated['keterangan'] ?? null],
        );

        return to_route('penyelenggara.jadwal.index', $program);
    }

    public function liburDestroy(Libur $libur): RedirectResponse
    {
        $this->bolehUbah(Program::findOrFail($libur->program_id));
        $programId = $libur->program_id;
        $libur->delete();

        return to_route('penyelenggara.jadwal.index', $programId);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Program $program): array
    {
        $jam = ['nullable', 'date_format:H:i'];

        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'pengulangan' => ['required', Rule::in([SesiPola::MINGGUAN, SesiPola::RENTANG, SesiPola::SEKALI])],
            'hari' => ['nullable', 'array'],
            'hari.*' => ['integer', 'between:1,7'],
            'tanggal' => ['nullable', 'date'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'grup_id' => ['nullable', Rule::exists('grups', 'id')->where('program_id', $program->id)],
            'datang_mulai' => ['required', 'date_format:H:i'],
            'datang_selesai' => ['required', 'date_format:H:i'],
            'batas_terlambat' => $jam,
            'pulang_mulai' => $jam,
            'pulang_selesai' => $jam,
        ], [
            'datang_mulai.required' => 'Isi jam mulai datang.',
            'datang_selesai.required' => 'Isi jam akhir datang.',
            'grup_id.exists' => 'Grup harus berasal dari program ini.',
        ]);
    }

    private function bolehUbah(Program $program): void
    {
        abort_if($program->isArchived(), 403, 'Program sudah diarsip dan hanya bisa dilihat.');
    }
}
