<?php

namespace App\Http\Controllers\KartuBebas;

use App\Http\Controllers\Controller;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\SchoolFrame;
use App\Services\CardParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Generate" = pick a layout, fill its data via a multi-step wizard, produce a
 * card. Same wizard powers the public per-layout link.
 */
class GenerateController extends Controller
{
    public function index(): Response
    {
        $layouts = CardForm::query()
            ->with('cardDataset:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('kartu-bebas/generate/index', [
            'layouts' => $layouts->map(fn (CardForm $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'orientation' => $f->orientation,
                'dataset_name' => $f->cardDataset?->name,
                'fields_count' => is_array($f->fields) ? count($f->fields) : 0,
            ]),
        ]);
    }

    public function create(CardForm $cardForm): Response
    {
        return Inertia::render('kartu-bebas/generate/form', [
            'layout' => $this->layoutPayload($cardForm),
        ]);
    }

    public function store(Request $request, CardForm $cardForm, CardParticipantService $participants): JsonResponse
    {
        $submission = $participants->save($request, $cardForm);
        $participants->generate($submission);

        return response()->json([
            'success' => true,
            'submission' => ['id' => $submission->id],
        ]);
    }

    public function status(CardFormSubmission $submission): JsonResponse
    {
        return response()->json([
            'status' => $submission->status,
            'card_url' => $submission->drive_url ?: ($submission->file_path ? Storage::disk('public')->url($submission->file_path) : null),
            'download_url' => ($submission->file_path || $submission->drive_file_id) ? route('kartu-bebas.peserta.download', $submission) : null,
            'thumb_url' => ($submission->file_path || $submission->drive_file_id) ? route('kartu-bebas.peserta.preview', $submission) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutPayload(CardForm $cardForm): array
    {
        $frameUrl = null;
        if ($cardForm->frame_id) {
            $frame = SchoolFrame::acrossSchools()->find($cardForm->frame_id);
            $frameUrl = $frame ? Storage::disk('public')->url($frame->image_path) : null;
        }

        return [
            'id' => $cardForm->id,
            'name' => $cardForm->name,
            'orientation' => $cardForm->orientation,
            'frame_url' => $frameUrl,
            'fields' => $cardForm->inputFields(),
        ];
    }
}
