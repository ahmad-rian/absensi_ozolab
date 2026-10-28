<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Penyelenggara;
use App\Services\CardParticipantService;
use App\Services\Penyelenggara\PesertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CardFormController extends Controller
{
    public function show(string $token): Response
    {
        $form = $this->formPublik($token);

        return Inertia::render('public/card-form', [
            'form' => [
                'name' => $form->name,
                'token' => $form->token,
                'fields' => array_values($form->inputFields()),
            ],
            'result' => null,
        ]);
    }

    public function submit(string $token, Request $request, CardParticipantService $participants, PesertaService $peserta): RedirectResponse|Response
    {
        $form = $this->formPublik($token);

        // Pendaftar publik program memakan kuota penyelenggara sama seperti
        // peserta yang ditambahkan admin.
        if ($form->program_id !== null) {
            $peserta->pastikanKuota(Penyelenggara::findOrFail($form->penyelenggara_id), 1);
        }

        $submission = $participants->save($request, $form);
        $participants->generate($submission);

        return Inertia::render('public/card-form', [
            'form' => [
                'name' => $form->name,
                'token' => $form->token,
                'fields' => array_values($form->inputFields()),
            ],
            'result' => [
                'submission_id' => $submission->id,
                'status' => 'processing',
                'card_url' => null,
                'download_url' => null,
            ],
        ]);
    }

    private function formPublik(string $token): CardForm
    {
        $form = CardForm::where('token', $token)->firstOrFail();
        abort_unless($form->terbukaUntukPublik(), 404);

        return $form;
    }

    /**
     * Poll endpoint: returns the generation status + card link once ready.
     */
    public function status(string $token, CardFormSubmission $submission): JsonResponse
    {
        $form = CardForm::where('token', $token)->firstOrFail();
        abort_unless($submission->card_form_id === $form->id, 404);

        // Salinan lokal dibuang setelah kartu terbit di Drive, jadi pakai URL
        // lokal hanya selama berkasnya masih ada.
        $cardUrl = $submission->file_path && Storage::disk('public')->exists($submission->file_path)
            ? Storage::disk('public')->url($submission->file_path)
            : $submission->drive_url;

        return response()->json([
            'status' => $submission->status,
            'card_url' => $cardUrl,
            'download_url' => $cardUrl,
        ]);
    }
}
