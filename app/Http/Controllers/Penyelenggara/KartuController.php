<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Http\Controllers\Controller;
use App\Models\CardFormSubmission;
use App\Models\Program;
use App\Models\SchoolFrame;
use App\Services\CardAttendanceService;
use App\Services\DynamicCardFiles;
use App\Support\SchoolTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Layout kartu program + unduh semua kartu (spec 002 US4).
 *
 * Editor yang dipakai sama dengan Kartu Bebas; yang berbeda hanya alamat
 * simpannya dan skema kolomnya (kategori program).
 */
class KartuController extends Controller
{
    public function edit(Program $program): Response
    {
        $form = $program->cardForm()->firstOrFail();
        $kategori = $program->kategori()->firstOrFail();

        return Inertia::render('penyelenggara/program/layout', [
            'program' => ['id' => $program->id, 'name' => $program->name],
            'form' => [
                'id' => $form->id,
                'name' => $form->name,
                'token' => $form->token,
                'card_dataset_id' => $kategori->card_dataset_id,
                'orientation' => $form->orientation,
                'frame_id' => $form->frame_id,
                'is_active' => $form->is_active,
                'layout_config' => $form->normalizedConfig(),
                // Link pendaftaran publik hanya ada saat dinyalakan.
                'public_url' => $form->terbukaUntukPublik() ? $form->publicUrl() : null,
            ],
            'formPublik' => [
                'aktif' => $form->form_publik === true,
                'url' => $form->publicUrl(),
                'terbuka' => $form->terbukaUntukPublik(),
                'arsip' => $program->isArchived(),
            ],
            'frames' => SchoolFrame::acrossSchools()
                ->where('category', 'kartu_bebas')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'image_path', 'width', 'height', 'category'])
                ->map(fn (SchoolFrame $frame) => [
                    'id' => $frame->id,
                    'name' => $frame->name,
                    'image_url' => Storage::disk('public')->url($frame->image_path),
                    'width' => $frame->width,
                    'height' => $frame->height,
                    'category' => $frame->category,
                ]),
            'datasets' => [[
                'id' => $kategori->card_dataset_id,
                'name' => $kategori->name,
                'fields' => $kategori->visibleFields(),
            ]],
        ]);
    }

    /**
     * Nyalakan/matikan form pendaftaran publik (/f/{token}) program ini.
     * Pendaftar publik tetap tunduk kuota peserta penyelenggara.
     */
    public function formPublik(Request $request, Program $program): RedirectResponse
    {
        abort_if($program->isArchived(), 403, 'Program sudah diarsip, form publiknya tidak bisa dinyalakan.');

        $aktif = $request->validate(['aktif' => ['required', 'boolean']])['aktif'];
        $program->cardForm()->firstOrFail()->update(['form_publik' => (bool) $aktif]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $aktif
            ? 'Form pendaftaran publik dinyalakan. Bagikan link-nya ke calon peserta.'
            : 'Form pendaftaran publik dimatikan. Link lama tidak bisa dibuka lagi.']);

        return back();
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        abort_if($program->isArchived(), 403, 'Program sudah diarsip dan hanya bisa dilihat.');

        $form = $program->cardForm()->firstOrFail();
        $kategori = $program->kategori()->firstOrFail();

        $validated = $request->validate([
            'orientation' => ['required', Rule::in(['landscape', 'portrait'])],
            'frame_id' => ['nullable', 'string', 'exists:school_frames,id'],
            'layout_config' => ['required', 'array'],
        ]);

        $sources = collect($kategori->visibleFields())->reject(fn (array $f) => $f['type'] === 'photo')->pluck('key')->all();
        $sources[] = CardAttendanceService::QR_SOURCE;
        $rules = [];
        foreach ($validated['layout_config']['elements'] ?? [] as $id => $element) {
            if (! is_array($element) || ($element['type'] ?? '') !== 'qr') {
                continue;
            }
            $prefix = 'layout_config.elements.'.$id;
            $rules[$prefix.'.enabled'] = ['required', 'boolean'];
            $rules[$prefix.'.source'] = [empty($element['enabled']) ? 'nullable' : 'required', 'string', Rule::in($sources)];
            $rules[$prefix.'.x'] = ['required', 'numeric', 'min:0', 'max:85.6'];
            $rules[$prefix.'.y'] = ['required', 'numeric', 'min:0', 'max:85.6'];
            $rules[$prefix.'.w'] = ['required', 'numeric', 'min:5', 'max:54'];
            $rules[$prefix.'.h'] = ['required', 'numeric', 'min:5', 'max:54'];
        }
        if ($rules !== []) {
            $request->validate($rules);
        }

        $form->update([
            'orientation' => $validated['orientation'],
            'frame_id' => $validated['frame_id'] ?? null,
            'layout_config' => $validated['layout_config'],
            'fields' => $kategori->visibleFields(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout kartu disimpan.']);

        return to_route('penyelenggara.kartu.edit', $program);
    }

    public function zip(Program $program, CardAttendanceService $attendance, DynamicCardFiles $files): BinaryFileResponse|RedirectResponse
    {
        $sementara = [];
        $path = tempnam(sys_get_temp_dir(), 'kartu-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $jumlah = 0;

        CardFormSubmission::where('program_id', $program->id)
            ->whereNull('archived_at')
            ->where('status', 'completed')
            ->whereNotNull('file_path')
            ->eachById(function (CardFormSubmission $peserta) use ($files, $zip, $attendance, &$jumlah, &$sementara): void {
                $berkas = $files->localPath($peserta);
                if (! $berkas) {
                    return;
                }
                if ($berkas['temporary']) {
                    $sementara[] = $berkas['path'];
                }
                $nama = (Str::slug($attendance->participantName($peserta)) ?: 'peserta').'-'.substr($peserta->id, -6).'.'.pathinfo($peserta->file_path, PATHINFO_EXTENSION);
                $zip->addFile($berkas['path'], $nama);
                $zip->setCompressionName($nama, ZipArchive::CM_STORE);
                $jumlah++;
            });

        if ($jumlah === 0) {
            $zip->close();
            @unlink($path);
            array_map('unlink', $sementara);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Belum ada kartu yang selesai dibuat di program ini.']);

            return back();
        }
        $zip->close();
        // Unduhan Drive sementara baru boleh dibuang setelah ZIP ditutup:
        // ZipArchive membaca berkasnya saat close(), bukan saat addFile().
        array_map('unlink', $sementara);

        return response()->download($path, 'kartu-'.Str::slug($program->name).'-'.SchoolTime::todayString().'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
