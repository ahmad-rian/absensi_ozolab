<?php

namespace App\Http\Controllers\General;

use App\Enums\ModulGeneral;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Penyelenggara;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akun semua penyelenggara (admin & pendamping) di satu tempat. Akun sekolah
 * dan orang tua tidak pernah tersentuh di sini — selalu 404.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $pengguna = $this->akunPenyelenggara()
            ->with(['roles:id,name', 'penyelenggara:id,name'])
            ->when($request->penyelenggara, fn (Builder $q, $id) => $q->where('penyelenggara_id', $id))
            ->when($request->peran, fn (Builder $q, $peran) => $q->role($peran))
            ->when($request->status !== null && $request->status !== '', fn (Builder $q) => $q->where('is_active', $request->status === 'aktif'))
            ->when($request->q, fn (Builder $q, $s) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', '%'.addcslashes($s, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($s, '%_\\').'%')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => $u->is_active,
                'role' => $u->roles->first()?->name,
                'penyelenggara' => ['id' => $u->penyelenggara_id, 'name' => $u->penyelenggara?->name],
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('general/pengguna', [
            'tab' => $request->query('tab') === 'penyelenggara' || ! $request->user()->can(ModulGeneral::PenggunaStaf->permission()) ? 'penyelenggara' : 'staf',
            'staf' => StafController::daftar(),
            'katalogModul' => ModulGeneral::katalog(),
            'peranStaf' => PeranStafController::daftar(),
            'bolehKelolaStaf' => $request->user()->can(ModulGeneral::PenggunaStaf->permission()),
            'bolehKelolaPenyelenggara' => $request->user()->can(ModulGeneral::PenggunaPenyelenggara->permission()),
            'bolehMasukSebagai' => $request->user()->isSuperAdmin(),
            'bolehBeriPengguna' => $request->user()->isSuperAdmin(),
            'pengguna' => $pengguna,
            'penyelenggaras' => Penyelenggara::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'penyelenggara' => $request->penyelenggara ?? '',
                'peran' => $request->peran ?? '',
                'status' => $request->status ?? '',
                'q' => $request->q ?? '',
            ],
        ]);
    }

    public function status(Request $request, string $user): RedirectResponse
    {
        $akun = $this->akunPenyelenggara()->findOrFail($user);
        $aktif = (bool) $request->validate(['is_active' => ['required', 'boolean']])['is_active'];

        if (! $aktif && $akun->is_active && $akun->hasRole(UserRole::AdminPenyelenggara->value)
            && Penyelenggara::findOrFail($akun->penyelenggara_id)->jumlahAdminAktif() <= 1) {
            return back()->withErrors(['is_active' => "{$akun->name} adalah satu-satunya admin aktif penyelenggaranya. Aktifkan admin lain dulu."]);
        }

        $akun->forceFill(['is_active' => $aktif])->save();

        activity()->performedOn($akun)->causedBy($request->user())
            ->withProperties(['is_active' => $aktif, 'penyelenggara_id' => $akun->penyelenggara_id])
            ->log($aktif ? 'akun diaktifkan' : 'akun dinonaktifkan');

        Inertia::flash('toast', ['type' => 'success', 'message' => $aktif ? "Akun {$akun->name} diaktifkan." : "Akun {$akun->name} dinonaktifkan."]);

        return back();
    }

    /**
     * Setel sandi baru. Pemilik akun wajib menggantinya saat login berikutnya,
     * sama seperti reset sandi di Pengguna sekolah.
     */
    public function sandi(Request $request, string $user): RedirectResponse
    {
        $akun = $this->akunPenyelenggara()->findOrFail($user);
        $validated = $request->validate([
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], [
            'password.required' => 'Sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi sandi tidak cocok.',
        ]);

        $akun->forceFill(['password' => Hash::make($validated['password']), 'must_change_password' => true])->save();

        activity()->performedOn($akun)->causedBy($request->user())
            ->withProperties(['penyelenggara_id' => $akun->penyelenggara_id])
            ->log('sandi direset');

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sandi {$akun->name} direset. Ia wajib menggantinya saat login."]);

        return back();
    }

    /**
     * @return Builder<User>
     */
    private function akunPenyelenggara(): Builder
    {
        return User::query()
            ->whereNotNull('penyelenggara_id')
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', array_map(fn (UserRole $r) => $r->value, UserRole::penyelenggaraRoles())));
    }
}
