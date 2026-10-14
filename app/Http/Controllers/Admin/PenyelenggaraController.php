<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Penyelenggara;
use App\Models\PenyelenggaraInvitation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Penyelenggara\KartuDrive;
use App\Support\PenyelenggaraUndangan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kelola penyelenggara (tenant modul Absensi Multi-Kategori) — super admin saja.
 */
class PenyelenggaraController extends Controller
{
    public function index(Request $request): Response
    {
        $penyelenggaras = Penyelenggara::withCount('users')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Penyelenggara $p) => $this->ringkas($p) + ['users_count' => $p->users_count]);

        return Inertia::render('admin/penyelenggara/index', [
            'penyelenggaras' => $penyelenggaras,
            'filters' => ['search' => $request->search ?? ''],
            'driveRootFolderId' => app(KartuDrive::class)->rootFolderId(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $penyelenggara = Penyelenggara::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Penyelenggara {$penyelenggara->name} dibuat."]);

        return to_route('admin.penyelenggara.show', $penyelenggara);
    }

    public function show(Penyelenggara $penyelenggara): Response
    {
        return Inertia::render('admin/penyelenggara/show', [
            'penyelenggara' => $this->ringkas($penyelenggara),
            'users' => User::where('penyelenggara_id', $penyelenggara->id)
                ->with('roles')
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'is_active' => $u->is_active,
                    'role' => $u->roles->first()?->name,
                ]),
            'invitations' => $penyelenggara->invitations()
                ->latest()
                ->get()
                ->map(fn (PenyelenggaraInvitation $i) => [
                    'id' => $i->id,
                    'email' => $i->email,
                    'role' => $i->role,
                    'status' => $i->status(),
                    'expires_at' => $i->expires_at->toIso8601String(),
                ]),
        ]);
    }

    public function update(Request $request, Penyelenggara $penyelenggara): RedirectResponse
    {
        $penyelenggara->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penyelenggara diperbarui.']);

        return to_route('admin.penyelenggara.show', $penyelenggara);
    }

    public function invite(Request $request, Penyelenggara $penyelenggara, PenyelenggaraUndangan $undangan): RedirectResponse
    {
        $validated = $request->validate(PenyelenggaraUndangan::rules(), PenyelenggaraUndangan::messages());

        $undangan->kirim($penyelenggara, $validated['email'], UserRole::AdminPenyelenggara, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Undangan dikirim ke {$validated['email']}."]);

        return to_route('admin.penyelenggara.show', $penyelenggara);
    }

    /**
     * Folder root Drive untuk kartu semua penyelenggara ("Tyas General Apps").
     * Menerima tautan folder Drive utuh atau ID-nya saja.
     */
    public function drive(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'folder' => ['nullable', 'string', 'max:300'],
        ]);

        $nilai = trim($validated['folder'] ?? '');
        if (preg_match('~/folders/([A-Za-z0-9_-]{10,})~', $nilai, $m)) {
            $nilai = $m[1];
        }

        if ($nilai !== '' && ! preg_match('/^[A-Za-z0-9_-]{10,}$/', $nilai)) {
            return back()->withErrors(['folder' => 'Bukan tautan atau ID folder Google Drive yang valid.']);
        }

        Setting::setValue(KartuDrive::SETTING_ROOT, $nilai !== '' ? $nilai : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => $nilai !== '' ? 'Folder Drive penyelenggara disimpan.' : 'Folder Drive penyelenggara dikosongkan.']);

        return to_route('admin.penyelenggara.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
            'participant_quota' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'wa_daily_quota' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ], [
            'name.required' => 'Nama penyelenggara wajib diisi.',
            'participant_quota.min' => 'Kuota peserta minimal 1, atau kosongkan untuk tanpa batas.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkas(Penyelenggara $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'is_active' => $p->is_active,
            'participant_quota' => $p->participant_quota,
            'wa_daily_quota' => $p->wa_daily_quota,
        ];
    }
}
