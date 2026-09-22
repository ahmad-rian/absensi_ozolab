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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as DownloadResponse;

/**
 * Portal orang tua: satu anak sebagai konteks, satu menu per kebutuhan.
 *
 * Seluruh angka datang dari StudentStatsBuilder yang juga dipakai halaman
 * admin dan berkas PDF, jadi layar dan unduhan tidak bisa berbeda isi.
 */
class PortalController extends Controller
{
    public function __construct(
        private readonly StudentStatsBuilder $stats,
        private readonly StudentReportBuilder $reports,
    ) {}

    public function index(Request $request): Response
    {
        $student = $this->student($request);

        if (! $student instanceof Student) {
            return Inertia::render('orangtua/beranda', $this->shell($request, ['panels' => [], 'today' => null, 'filters' => null]));
        }

        ['start' => $start, 'end' => $end] = $this->range($request);
        $hariIni = $this->stats->attendanceFor($student, SchoolTime::now()->toDateString(), SchoolTime::now()->toDateString());

        return Inertia::render('orangtua/beranda', $this->shell($request, [
            'panels' => $this->panels($student, $start, $end),
            'today' => $hariIni['recent'][0] ?? null,
            'filters' => ['start_date' => $start, 'end_date' => $end],
        ]));
    }

    public function absensi(Request $request): Response
    {
        $student = $this->student($request);
        ['start' => $start, 'end' => $end] = $this->range($request);

        return Inertia::render('orangtua/absensi', $this->shell($request, [
            'panel' => $student ? ['label' => 'Absensi Sekolah', ...$this->stats->attendanceFor($student, $start, $end)] : null,
            'filters' => ['start_date' => $start, 'end_date' => $end],
        ]));
    }

    public function sholat(Request $request): Response
    {
        $student = $this->student($request);
        ['start' => $start, 'end' => $end] = $this->range($request);
        $panels = $student ? $this->panels($student, $start, $end) : [];
        unset($panels['absensi']);

        return Inertia::render('orangtua/sholat', $this->shell($request, [
            'panels' => $panels,
            'filters' => ['start_date' => $start, 'end_date' => $end],
        ]));
    }

    public function laporan(Request $request): Response|DownloadResponse
    {
        $student = $this->student($request);
        ['start' => $start, 'end' => $end] = $this->range($request);
        $request->validate(['jenis' => ['nullable', 'string', 'in:absensi,dhuha,dzuhur,semuanya']]);
        $panels = $student ? $this->panels($student, $start, $end) : [];

        if ($request->boolean('download')) {
            abort_unless($student instanceof Student, 404);
            $kind = $request->input('jenis', 'semuanya');
            abort_unless($kind === 'semuanya' || isset($panels[$kind]), 403);

            return $this->reports->combinedPdf($student, $start, $end, $kind);
        }

        return Inertia::render('orangtua/laporan', $this->shell($request, [
            'kinds' => array_map(fn (string $slug, array $panel) => ['slug' => $slug, 'label' => $panel['label']], array_keys($panels), $panels),
            'filters' => ['start_date' => $start, 'end_date' => $end],
        ]));
    }

    public function galeri(Request $request): Response
    {
        $student = $this->student($request);
        $cards = $student ? CardGenerationLog::where('student_id', $student->id)
            ->where('status', 'completed')
            ->where('type', 'card')
            ->with('cardLayout')
            ->latest()
            ->get()
            ->unique('school_card_layout_id') : collect();

        return Inertia::render('orangtua/galeri', $this->shell($request, [
            'cards' => $cards->map(fn (CardGenerationLog $card): array => [
                'id' => $card->id,
                'name' => $card->cardLayout?->name ?? 'Kartu OSIS',
                'url' => $card->file_path && Storage::disk('public')->exists($card->file_path)
                    ? route('orangtua.download', ['asset' => $card->id, 'anak' => $card->student_id])
                    : null,
                'drive_url' => $card->drive_url,
            ])->values(),
        ]));
    }

    public function download(Request $request, string $asset): DownloadResponse
    {
        $student = $this->student($request);
        abort_unless($student instanceof Student, 404);

        // Kartu dicari DI DALAM lingkup siswanya, jadi id kartu milik anak lain
        // tidak bisa ditukar lewat URL.
        $path = $asset === 'foto'
            ? $student->photo_path
            : CardGenerationLog::where('student_id', $student->id)
                ->where('status', 'completed')
                ->where('type', 'card')
                ->findOrFail($asset)
                ->file_path;

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->download($path);
    }

    /**
     * Prop yang dibutuhkan SETIAP halaman portal: anak yang sedang dilihat dan
     * daftar anak untuk pemilih di sidebar.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function shell(Request $request, array $extra): array
    {
        $student = $this->student($request);
        /** @var Collection<int, Student> $children */
        $children = $request->attributes->get('portalChildren') ?? collect();

        return [
            'student' => $student ? $this->identity($student) : null,
            'daftarAnak' => $children->map(fn (Student $child): array => $this->identity($child))->values(),
            // Menu Sholat disembunyikan kalau sekolahnya memang tidak memakai
            // fitur itu — menu yang selalu kosong hanya bikin orang tua ragu
            // apakah anaknya yang tidak pernah ikut atau sistemnya yang rusak.
            'fiturSholat' => $student && $student->school
                ? SchoolFeatures::for($student->school)->enabled(SchoolFeature::SholatDhuha)
                    || SchoolFeatures::for($student->school)->enabled(SchoolFeature::SholatDzuhur)
                : false,
            ...$extra,
        ];
    }

    private function student(Request $request): ?Student
    {
        $student = $request->attributes->get('portalStudent');

        return $student instanceof Student ? $student : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(Student $student): array
    {
        $student->loadMissing(['classroom', 'school']);

        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'nis' => $student->nis,
            'classroom' => $student->classroom?->name,
            'school' => $student->school?->name,
            'photo' => StudentPhotoStorage::displayUrl($student->photo_path),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array{start: string, end: string}
     */
    private function range(Request $request): array
    {
        $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        return $this->stats->resolveRange($request->input('start_date'), $request->input('end_date'));
    }
}
