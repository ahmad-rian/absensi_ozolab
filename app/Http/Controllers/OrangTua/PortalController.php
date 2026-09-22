<?php

namespace App\Http\Controllers\OrangTua;

use App\Enums\PrayerType;
use App\Enums\SchoolFeature;
use App\Http\Controllers\Controller;
use App\Models\CardGenerationLog;
use App\Models\Student;
use App\Services\Student\StudentReportBuilder;
use App\Services\Student\StudentStatsBuilder;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use App\Support\StudentPhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as DownloadResponse;

class PortalController extends Controller
{
    public function __construct(private readonly StudentStatsBuilder $stats, private readonly StudentReportBuilder $reports) {}

    public function index(Request $request): Response
    {
        $end = SchoolTime::now()->toDateString();
        $start = SchoolTime::now()->subDays(29)->toDateString();
        $students = $request->user()->parentProfile?->students()->with(['classroom', 'school'])->get() ?? collect();

        return Inertia::render('orangtua/index', ['children' => $students->map(function (Student $student) use ($start, $end): array {
            return [...$this->identity($student), 'panels' => $this->panels($student, $start, $end), 'today' => $this->stats->attendanceFor($student, $end, $end)['recent']];
        })]);
    }

    public function show(Request $request): Response
    {
        $student = $request->attributes->get('portalStudent');
        ['start' => $start, 'end' => $end] = $this->range($request);

        return Inertia::render('orangtua/anak', ['student' => $this->identity($student), 'panels' => $this->panels($student, $start, $end), 'filters' => ['start_date' => $start, 'end_date' => $end]]);
    }

    public function laporan(Request $request): Response|DownloadResponse
    {
        $student = $request->attributes->get('portalStudent');
        ['start' => $start, 'end' => $end] = $this->range($request);
        $panels = $this->panels($student, $start, $end);
        $request->validate(['jenis' => ['nullable', 'string', 'in:absensi,dhuha,dzuhur,semuanya']]);
        if ($request->boolean('download')) {
            $kind = $request->input('jenis', 'semuanya');
            abort_unless($kind === 'semuanya' || isset($panels[$kind]), 403);

            return $this->reports->combinedPdf($student, $start, $end, $kind);
        }

        return Inertia::render('orangtua/laporan', ['student' => $this->identity($student), 'kinds' => array_keys($panels), 'filters' => ['start_date' => $start, 'end_date' => $end]]);
    }

    public function galeri(Request $request): Response
    {
        $student = $request->attributes->get('portalStudent');
        $cards = CardGenerationLog::where('student_id', $student->id)->where('status', 'completed')->where('type', 'card')->with('cardLayout')->latest()->get()->unique('school_card_layout_id');

        return Inertia::render('orangtua/galeri', ['student' => $this->identity($student), 'cards' => $cards->map(fn ($card) => [
            'id' => $card->id, 'name' => $card->cardLayout?->name ?? 'Kartu OSIS',
            'url' => $card->file_path && Storage::disk('public')->exists($card->file_path) ? route('orangtua.download', [$student->id, $card->id]) : null,
            'drive_url' => $card->drive_url,
        ])->values()]);
    }

    public function download(Request $request, string $anak, string $asset): DownloadResponse
    {
        $student = $request->attributes->get('portalStudent');
        $path = $asset === 'foto' ? $student->photo_path : CardGenerationLog::where('student_id', $student->id)->where('status', 'completed')->where('type', 'card')->findOrFail($asset)->file_path;
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->download($path);
    }

    private function identity(Student $student): array
    {
        $student->loadMissing(['classroom', 'school']);

        return ['id' => $student->id, 'name' => $student->full_name, 'nis' => $student->nis, 'classroom' => $student->classroom?->name, 'photo' => StudentPhotoStorage::displayUrl($student->photo_path)];
    }

    private function panels(Student $student, string $start, string $end): array
    {
        $panels = ['absensi' => ['label' => 'Absensi Sekolah', ...$this->stats->attendanceFor($student, $start, $end)]];
        foreach (['dhuha' => SchoolFeature::SholatDhuha, 'dzuhur' => SchoolFeature::SholatDzuhur] as $slug => $feature) {
            if ($student->school && SchoolFeatures::for($student->school)->enabled($feature)) {
                $panels[$slug] = ['label' => 'Sholat '.ucfirst($slug), ...$this->stats->prayerFor($student, $start, $end, PrayerType::fromSlug($slug))];
            }
        }

        return $panels;
    }

    private function range(Request $request): array
    {
        $request->validate(['start_date' => ['nullable', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date']]);

        return $this->stats->resolveRange($request->input('start_date'), $request->input('end_date'));
    }
}
