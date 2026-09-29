<?php

namespace App\Http\Controllers\KartuBebas;

use App\Http\Controllers\Controller;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Services\CardParticipantService;
use App\Services\DynamicCardFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ParticipantController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'layout' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'processing', 'completed', 'failed'])],
        ]);
        $records = CardFormSubmission::with('cardForm')
            ->when($filters['layout'] ?? null, fn ($query, $layout) => $query->where('card_form_id', $layout))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where('data', 'like', '%'.$search.'%'))
            ->latest('updated_at')->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn ($record) => $this->payload($record));

        return Inertia::render('kartu-bebas/peserta/index', [
            'participants' => $records,
            'layouts' => CardForm::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'history' => $request->routeIs('kartu-bebas.riwayat'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('kartu-bebas/peserta/form', [
            'layouts' => CardForm::orderBy('name')->get()->map(fn ($form) => ['id' => $form->id, 'name' => $form->name, 'fields' => $form->inputFields()]),
            'participant' => null,
        ]);
    }

    public function store(Request $request, CardParticipantService $participants): RedirectResponse
    {
        $request->validate(['layout_id' => ['required', 'exists:card_forms,id']]);
        $record = $participants->save($request, CardForm::findOrFail($request->input('layout_id')));

        return to_route('kartu-bebas.peserta.show', $record);
    }

    public function show(CardFormSubmission $submission): Response
    {
        return Inertia::render('kartu-bebas/peserta/show', ['participant' => $this->payload($submission)]);
    }

    public function edit(CardFormSubmission $submission): Response
    {
        abort_if($submission->status === 'processing', 409, 'Kartu sedang diproses.');

        return Inertia::render('kartu-bebas/peserta/form', [
            'layouts' => [['id' => $submission->card_form_id, 'name' => $submission->cardForm->name, 'fields' => $submission->cardForm->inputFields()]],
            'participant' => $this->payload($submission),
        ]);
    }

    public function update(Request $request, CardFormSubmission $submission, CardParticipantService $participants): RedirectResponse
    {
        $participants->save($request, $submission->cardForm, $submission);

        return to_route('kartu-bebas.peserta.show', $submission);
    }

    public function generate(CardFormSubmission $submission, CardParticipantService $participants): RedirectResponse
    {
        $participants->generate($submission);

        return to_route('kartu-bebas.peserta.show', $submission);
    }

    public function preview(CardFormSubmission $submission, DynamicCardFiles $files): BinaryFileResponse
    {
        return $files->response($submission, false);
    }

    public function download(CardFormSubmission $submission, DynamicCardFiles $files): BinaryFileResponse
    {
        return $files->response($submission, true);
    }

    public function destroy(CardFormSubmission $submission, DynamicCardFiles $files, CardParticipantService $participants): RedirectResponse
    {
        $removed = DB::transaction(function () use ($submission) {
            $record = CardFormSubmission::with('cardForm')->lockForUpdate()->findOrFail($submission->id);
            abort_if($record->status === 'processing', 409, 'Kartu sedang diproses.');
            $record->delete();

            return $record;
        });
        $participants->deletePhotos(array_values(array_filter([$removed->photo_path, $removed->original_photo_path])));
        if ($removed->file_path) {
            Storage::disk('public')->delete($removed->file_path);
        }
        $files->deleteDrive($removed->cardForm, $removed->drive_file_id);

        return to_route('kartu-bebas.peserta.index');
    }

    /** @return array<string, mixed> */
    private function payload(CardFormSubmission $record): array
    {
        $hasOutput = (bool) ($record->file_path || $record->drive_file_id);

        return [
            'id' => $record->id,
            'layout_id' => $record->card_form_id,
            'layout_name' => $record->cardForm?->name ?? '-',
            'fields' => $record->cardForm?->inputFields() ?? [],
            'data' => $record->data ?? [],
            'status' => $record->status,
            'error' => $record->generation_error,
            'photo_url' => $record->photo_path ? Storage::disk('public')->url($record->photo_path) : null,
            'original_photo_url' => ($record->original_photo_path ?: $record->photo_path) ? Storage::disk('public')->url($record->original_photo_path ?: $record->photo_path) : null,
            'preview_url' => $hasOutput ? route('kartu-bebas.peserta.preview', $record) : null,
            'download_url' => $hasOutput ? route('kartu-bebas.peserta.download', $record) : null,
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }
}
