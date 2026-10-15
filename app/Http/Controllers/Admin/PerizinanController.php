<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\PengajuanIzin;
use App\Services\Perizinan\PerizinanService;
use App\Support\StudentPhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data Perizinan: sekolah meninjau pengajuan izin/sakit dari orang tua.
 * Binding `{izin}` tersaring scope sekolah — pengajuan sekolah lain 404.
 */
class PerizinanController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->status, [PengajuanIzin::MENUNGGU, PengajuanIzin::DISETUJUI, PengajuanIzin::DITOLAK, 'semua'], true)
            ? $request->status : PengajuanIzin::MENUNGGU;

        $izin = PengajuanIzin::query()
            ->with(['student:id,full_name,nis,classroom_id,photo_path', 'student.classroom:id,name', 'reviewer:id,name'])
            ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
            ->when($request->kelas, fn ($q, $kelas) => $q->whereHas('student', fn ($s) => $s->where('classroom_id', $kelas)))
            ->when($request->q, fn ($q, $cari) => $q->whereHas('student', fn ($s) => $s->where('full_name', 'like', '%'.addcslashes($cari, '%_\\').'%')))
            ->orderByRaw("case when status = 'menunggu' then 0 else 1 end")
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PengajuanIzin $i) => [
                'id' => $i->id,
                'jenis' => $i->jenis,
                'tanggal_mulai' => $i->tanggal_mulai,
                'tanggal_selesai' => $i->tanggal_selesai,
                'alasan' => $i->alasan,
                'status' => $i->status,
                'sumber' => $i->sumber,
                'pengirim_nama' => $i->pengirim_nama,
                'pengirim_telepon' => $i->pengirim_telepon,
                'ada_bukti' => (bool) $i->bukti_path,
                'catatan_review' => $i->catatan_review,
                'hari_dicatat' => $i->hari_dicatat,
                'reviewer' => $i->reviewer?->name,
                'reviewed_at' => $i->reviewed_at?->toIso8601String(),
                'dikirim' => $i->created_at?->toIso8601String(),
                'siswa' => [
                    'id' => $i->student?->id,
                    'nama' => $i->student?->full_name,
                    'nis' => $i->student?->nis,
                    'kelas' => $i->student?->classroom?->name,
                    'foto' => StudentPhotoStorage::displayUrl($i->student?->photo_path),
                ],
            ]);

        $hitung = PengajuanIzin::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('admin/perizinan/index', [
            'izin' => $izin,
            'ringkasan' => [
                'menunggu' => (int) ($hitung[PengajuanIzin::MENUNGGU] ?? 0),
                'disetujui' => (int) ($hitung[PengajuanIzin::DISETUJUI] ?? 0),
                'ditolak' => (int) ($hitung[PengajuanIzin::DITOLAK] ?? 0),
            ],
            'classrooms' => Classroom::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['status' => $status, 'kelas' => $request->kelas ?? '', 'q' => $request->q ?? ''],
            'linkPublik' => route('izin.index'),
        ]);
    }

    public function setujui(Request $request, PengajuanIzin $izin, PerizinanService $perizinan): RedirectResponse
    {
        $hari = $perizinan->setujui($izin, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => $hari > 0
            ? "Disetujui. {$hari} hari sekolah dicatat ".($izin->jenis === 'SAKIT' ? 'Sakit' : 'Izin').'.'
            : 'Disetujui. Tidak ada hari sekolah yang perlu dicatat (libur atau sudah hadir).']);

        return back();
    }

    public function tolak(Request $request, PengajuanIzin $izin, PerizinanService $perizinan): RedirectResponse
    {
        $catatan = $request->validate(['catatan' => ['required', 'string', 'min:3', 'max:300']], [
            'catatan.required' => 'Tuliskan alasan penolakan supaya orang tua tahu.',
        ])['catatan'];

        $perizinan->tolak($izin, $request->user(), $catatan);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan ditolak.']);

        return back();
    }

    /**
     * Foto bukti disimpan privat; hanya staf sekolah pemilik yang bisa membuka.
     */
    public function bukti(PengajuanIzin $izin): StreamedResponse
    {
        abort_unless($izin->bukti_path && Storage::disk('local')->exists($izin->bukti_path), 404);

        return Storage::disk('local')->response($izin->bukti_path, 'bukti-izin.webp', ['Cache-Control' => 'private, max-age=600']);
    }
}
