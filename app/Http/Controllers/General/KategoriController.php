<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\CardDataset;
use App\Models\CardForm;
use App\Models\Kategori;
use App\Models\Penyelenggara;
use App\Models\Program;
use App\Support\KategoriTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Kelola kategori dari workspace general: tambah untuk penyelenggara mana pun,
 * arsip/aktifkan, dan hapus yang belum pernah dipakai. Isi skema kolom tetap
 * diedit di area penyelenggara (satu editor, satu sumber kebenaran).
 *
 * Kategori ber-tenant fail-closed, jadi semua pencarian lewat
 * `acrossPenyelenggara()` — binding rute biasa akan selalu 404 di sini.
 */
class KategoriController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'penyelenggara_id' => ['required', 'string', 'exists:penyelenggaras,id'],
            'template' => ['required', Rule::in(array_keys(KategoriTemplates::all()))],
            'name' => ['nullable', 'string', 'max:120'],
        ], [
            'penyelenggara_id.required' => 'Pilih penyelenggara pemilik kategori ini.',
            'template.in' => 'Pilih salah satu template yang tersedia.',
        ]);

        $penyelenggara = Penyelenggara::findOrFail($validated['penyelenggara_id']);
        $kategori = Kategori::dariTemplate($penyelenggara, $validated['template'], $validated['name'] ?? null, $request->user());

        // Langsung ke editor kolom di area penyelenggara pemiliknya.
        $request->session()->put('current_penyelenggara_id', $penyelenggara->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$kategori->name} dibuat untuk {$penyelenggara->name}. Atur kolom datanya di sini."]);

        return to_route('penyelenggara.kategori.edit', $kategori->id);
    }

    public function arsip(string $kategori): RedirectResponse
    {
        $kategori = $this->temukan($kategori);
        $kategori->update(['archived_at' => $kategori->isArchived() ? null : now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $kategori->isArchived()
            ? "Kategori {$kategori->name} diarsip."
            : "Kategori {$kategori->name} diaktifkan lagi."]);

        return back();
    }

    /**
     * Hanya kategori yang belum pernah punya program. Yang sudah dipakai
     * diarsip, supaya peserta, kartu, dan absensinya tidak ikut hilang.
     */
    public function destroy(string $kategori): RedirectResponse
    {
        $kategori = $this->temukan($kategori);

        $dipakai = Program::acrossPenyelenggara()->where('kategori_id', $kategori->id)->exists()
            || ($kategori->card_dataset_id && CardForm::withoutGlobalScopes()->where('card_dataset_id', $kategori->card_dataset_id)->exists());

        if ($dipakai) {
            return back()->withErrors(['kategori' => "Kategori {$kategori->name} sudah punya program. Arsipkan saja supaya datanya tetap aman."]);
        }

        DB::transaction(function () use ($kategori) {
            if ($kategori->card_dataset_id) {
                CardDataset::withoutGlobalScopes()->whereKey($kategori->card_dataset_id)->delete();
            }

            $kategori->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$kategori->name} dihapus."]);

        return back();
    }

    private function temukan(string $id): Kategori
    {
        return Kategori::acrossPenyelenggara()->findOrFail($id);
    }
}
