<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Services\CardParticipantService;
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
        $form = CardForm::where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        return Inertia::render('public/card-form', [
            'form' => [
                'name' => $form->name,
                'token' => $form->token,
                'fields' => array_values($form->inputFields()),
            ],
            'result' => null,
        ]);
    }

    public function submit(string $token, Request $request, CardParticipantService $participants): RedirectResponse|Response
    {
        $form = CardForm::where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

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

    /**
     * Poll endpoint: returns the generation status + card link once ready.
     */
    public function status(string $token, CardFormSubmission $submission): JsonResponse
    {
        $form = CardForm::where('token', $token)->firstOrFail();
        abort_unless($submission->card_form_id === $form->id, 404);

        $cardUrl = $submission->file_path ? Storage::disk('public')->url($submission->file_path) : $submission->drive_url;

        return response()->json([
            'status' => $submission->status,
            'card_url' => $cardUrl,
            'download_url' => $cardUrl,
        ]);
    }
}
