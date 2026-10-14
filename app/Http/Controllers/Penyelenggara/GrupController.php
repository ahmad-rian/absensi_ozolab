<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\Grup;
use App\Models\Program;
use App\Models\Sesi;
use App\Models\SesiPola;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Grup bertingkat dalam program + penugasan pendamping (spec 002 US2/US5).
 */
class GrupController extends Controller
{
    public function store(Request $request, Program $program): RedirectResponse
    {
        $this->bolehUbah($program);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', Rule::exists('grups', 'id')->where('program_id', $program->id)],
            'urutan' => ['nullable', 'integer', 'min:0'],
        ], ['parent_id.exists' => 'Induk grup harus berasal dari program yang sama.']);

        Grup::create($validated + ['program_id' => $program->id, 'penyelenggara_id' => $program->penyelenggara_id, 'urutan' => $validated['urutan'] ?? 0]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grup ditambahkan.']);

        return to_route('penyelenggara.program.show', $program);
    }

    public function update(Request $request, Grup $grup): RedirectResponse
    {
        $this->bolehUbah($grup->program);

        $grup->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'urutan' => ['nullable', 'integer', 'min:0'],
        ]));

        return to_route('penyelenggara.program.show', $grup->program_id);
    }

    public function destroy(Grup $grup): RedirectResponse
    {
        $this->bolehUbah($grup->program);

        if ($grup->children()->exists() || $grup->peserta()->exists()) {
            return back()->withErrors(['grup' => 'Grup masih punya sub-grup atau peserta. Kosongkan dulu sebelum menghapus.']);
        }

        // Sesi khusus grup ikut terhapus (FK cascade) beserta kehadirannya —
        // riwayat laporan tidak boleh hilang diam-diam.
        if (SesiPola::where('grup_id', $grup->id)->exists() || Sesi::where('grup_id', $grup->id)->exists()) {
            return back()->withErrors(['grup' => 'Grup ini punya jadwal sesi sendiri. Hapus atau pindahkan jadwalnya dulu supaya riwayat kehadiran tidak ikut terhapus.']);
        }

        $programId = $grup->program_id;
        $grup->delete();

        return to_route('penyelenggara.program.show', $programId);
    }

    public function pendamping(Request $request, Grup $grup): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => ['array'],
            'user_ids.*' => [Rule::exists('users', 'id')->where('penyelenggara_id', $grup->penyelenggara_id)],
        ]);

        $ids = User::whereIn('id', $validated['user_ids'] ?? [])->role('PENDAMPING')->pluck('id');
        $grup->pendamping()->sync($ids);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pendamping diperbarui.']);

        return to_route('penyelenggara.program.show', $grup->program_id);
    }

    private function bolehUbah(Program $program): void
    {
        abort_if($program->isArchived(), 403, 'Program sudah diarsip dan hanya bisa dilihat.');
    }
}
