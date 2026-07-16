<?php

namespace App\Http\Controllers\KartuBebas;

use App\Http\Controllers\Controller;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $generated = CardFormSubmission::query()
            ->where(function ($query) {
                $query->where('status', 'completed')
                    ->orWhereNotNull('drive_url')
                    ->orWhereNotNull('file_path');
            })
            ->count();

        $latest = CardFormSubmission::query()
            ->with('cardForm:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CardFormSubmission $submission) => [
                'id' => $submission->id,
                'form_name' => $submission->cardForm?->name,
                'status' => $submission->status,
                'has_file' => $submission->drive_url !== null || $submission->file_path !== null,
                'created_at' => $submission->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('kartu-bebas/dashboard', [
            'stats' => [
                'layouts' => CardForm::count(),
                'records' => CardFormSubmission::count(),
                'generated' => $generated,
            ],
            'latest' => $latest,
        ]);
    }
}
