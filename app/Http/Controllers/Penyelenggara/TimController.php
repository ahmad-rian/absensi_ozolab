<?php

namespace App\Http\Controllers\Penyelenggara;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Penyelenggara;
use App\Models\PenyelenggaraInvitation;
use App\Models\User;
use App\Support\PenyelenggaraUndangan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tim penyelenggara: undang admin/pendamping, cabut undangan, nonaktifkan akun.
 */
class TimController extends Controller
{
    public function index(): Response
    {
        $penyelenggara = $this->penyelenggara();

        return Inertia::render('penyelenggara/tim', [
            'users' => $this->anggota($penyelenggara)
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
            'invitations' => PenyelenggaraInvitation::query()
                ->whereNull('accepted_at')
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

    public function invite(Request $request, PenyelenggaraUndangan $undangan): RedirectResponse
    {
        $validated = $request->validate(PenyelenggaraUndangan::rules() + [
            'role' => ['required', Rule::in(array_map(fn (UserRole $r) => $r->value, UserRole::penyelenggaraRoles()))],
        ], PenyelenggaraUndangan::messages() + ['role.in' => 'Peran tidak dikenal.']);

        $undangan->kirim($this->penyelenggara(), $validated['email'], UserRole::from($validated['role']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Undangan dikirim ke {$validated['email']}."]);

        return to_route('penyelenggara.tim.index');
    }

    public function revoke(Request $request, PenyelenggaraInvitation $invitation): RedirectResponse
    {
        $invitation->forceFill(['revoked_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Undangan dibatalkan.']);

        return to_route('penyelenggara.tim.index');
    }

    public function status(Request $request, string $user): RedirectResponse
    {
        $penyelenggara = $this->penyelenggara();
        $anggota = $this->anggota($penyelenggara)->findOrFail($user);

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $aktif = (bool) $validated['is_active'];

        if (! $aktif && $anggota->hasRole(UserRole::AdminPenyelenggara->value) && $penyelenggara->jumlahAdminAktif() <= 1) {
            return back()->withErrors(['is_active' => 'Setiap penyelenggara harus punya minimal satu Admin Penyelenggara aktif.']);
        }

        $anggota->forceFill(['is_active' => $aktif])->save();

        activity()
            ->performedOn($anggota)
            ->causedBy($request->user())
            ->withProperties(['is_active' => $aktif, 'penyelenggara_id' => $penyelenggara->id])
            ->log($aktif ? 'akun diaktifkan' : 'akun dinonaktifkan');

        Inertia::flash('toast', ['type' => 'success', 'message' => $aktif ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.']);

        return to_route('penyelenggara.tim.index');
    }

    public function peran(Request $request, string $user): RedirectResponse
    {
        $penyelenggara = $this->penyelenggara();
        $anggota = $this->anggota($penyelenggara)->findOrFail($user);

        $validated = $request->validate([
            'role' => ['required', Rule::in(array_map(fn (UserRole $r) => $r->value, UserRole::penyelenggaraRoles()))],
        ], ['role.in' => 'Pilih Admin Penyelenggara atau Pendamping.']);

        $turunDariAdmin = $validated['role'] !== UserRole::AdminPenyelenggara->value
            && $anggota->hasRole(UserRole::AdminPenyelenggara->value);
        if ($turunDariAdmin && $anggota->is_active && $penyelenggara->jumlahAdminAktif() <= 1) {
            return back()->withErrors(['role' => 'Setiap penyelenggara harus punya minimal satu Admin Penyelenggara aktif.']);
        }

        $anggota->syncRoles([$validated['role']]);

        activity()
            ->performedOn($anggota)
            ->causedBy($request->user())
            ->withProperties(['role' => $validated['role'], 'penyelenggara_id' => $penyelenggara->id])
            ->log('peran diubah');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Peran diubah ke '.UserRole::from($validated['role'])->label().'.']);

        return to_route('penyelenggara.tim.index');
    }

    private function penyelenggara(): Penyelenggara
    {
        abort_unless(app()->bound('currentPenyelenggara'), 404);

        return app('currentPenyelenggara');
    }

    /**
     * Akun milik penyelenggara aktif. User tidak memakai trait penyelenggara
     * (guard memuatnya lintas tenant), jadi batasnya ditegakkan di sini —
     * akun penyelenggara lain → 404, sama dengan tidak ada.
     */
    private function anggota(Penyelenggara $penyelenggara)
    {
        return User::where('penyelenggara_id', $penyelenggara->id);
    }
}
