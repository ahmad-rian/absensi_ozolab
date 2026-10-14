<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Program;
use App\Models\User;
use App\Services\Penyelenggara\KartuDrive;
use App\Services\Penyelenggara\SesiGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Program = satu periode berjalan dalam kategori (spec 002 US2).
 */
class ProgramController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('penyelenggara/program/index', [
            'programs' => Program::with('kategori:id,name')
                ->withCount('grups')
                ->orderByRaw('archived_at is not null')
                ->orderByDesc('starts_on')
                ->get()
                ->map(fn (Program $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'kategori' => $p->kategori?->name,
                    'starts_on' => $p->starts_on->toDateString(),
                    'ends_on' => $p->ends_on->toDateString(),
                    'grups_count' => $p->grups_count,
                    'peserta_count' => $p->peserta()->whereNull('archived_at')->count(),
                    'archived' => $p->isArchived(),
                ]),
            'kategoris' => Kategori::whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $program = Program::create($this->validated($request) + ['penyelenggara_id' => app('currentPenyelenggara')->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Program {$program->name} dibuat."]);

        return to_route('penyelenggara.program.show', $program);
    }

    public function show(Program $program): Response
    {
        $program->load('kategori');

        return Inertia::render('penyelenggara/program/show', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'kategori' => $program->kategori?->name,
                'istilah_peserta' => $program->kategori?->istilah_peserta ?? 'Peserta',
                'istilah_grup' => $program->kategori?->istilah_grup ?? 'Grup',
                'starts_on' => $program->starts_on->toDateString(),
                'ends_on' => $program->ends_on->toDateString(),
                'timezone' => $program->timezone,
                'archived' => $program->isArchived(),
                'peserta_count' => $program->peserta()->whereNull('archived_at')->count(),
            ],
            'grups' => $program->grups()
                ->withCount('peserta')
                ->with('pendamping:id,name')
                ->orderBy('urutan')
                ->orderBy('name')
                ->get()
                ->map(fn (Grup $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'parent_id' => $g->parent_id,
                    'urutan' => $g->urutan,
                    'peserta_count' => $g->peserta_count,
                    'pendamping' => $g->pendamping->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
                ]),
            // Tanpa folder Drive, kartu tetap jadi tapi hanya tersimpan di server.
            'driveSiap' => app(KartuDrive::class)->siap(),
            'pendampingOptions' => User::where('penyelenggara_id', $program->penyelenggara_id)
                ->role('PENDAMPING')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        abort_if($program->isArchived(), 403, 'Program sudah diarsip dan hanya bisa dilihat.');

        $program->update($this->validated($request, $program));
        app(SesiGenerator::class)->sinkronProgram($program->fresh());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Program diperbarui.']);

        return to_route('penyelenggara.program.show', $program);
    }

    public function arsip(Program $program): RedirectResponse
    {
        $program->update(['archived_at' => $program->isArchived() ? null : now()]);

        return to_route('penyelenggara.program.show', $program);
    }

    public function salin(Request $request, Program $program): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ], ['ends_on.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);

        $baru = $program->salinKe($validated['name'], $validated['starts_on'], $validated['ends_on']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Struktur grup disalin ke {$baru->name}."]);

        return to_route('penyelenggara.program.show', $baru);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Program $program = null): array
    {
        return $request->validate([
            'kategori_id' => [$program ? 'sometimes' : 'required', Rule::exists('kategoris', 'id')->where('penyelenggara_id', app('currentPenyelenggara')->id)->whereNull('archived_at')],
            'name' => ['required', 'string', 'max:150'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'timezone' => ['nullable', 'timezone:all'],
        ], [
            'kategori_id.required' => 'Pilih kategori.',
            'kategori_id.exists' => 'Kategori tidak ditemukan atau sudah diarsip. Aktifkan lagi kategorinya atau pilih yang lain.',
            'ends_on.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]) + ($program ? [] : ['timezone' => $request->input('timezone') ?: 'Asia/Jakarta']);
    }
}
