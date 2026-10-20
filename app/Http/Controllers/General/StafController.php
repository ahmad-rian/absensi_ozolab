<?php

namespace App\Http\Controllers\General;

use App\Enums\ModulGeneral;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/**
 * Akun staf Tyas di workspace General: CRUD + modul yang boleh dibuka,
 * dari peran staf (template) ditambah centang langsung per akun.
 *
 * Akun staf tidak punya sekolah maupun penyelenggara. Modul Staf & Peran Staf
 * hanya bisa diberikan super admin — staf tidak bisa menaikkan hak siapa pun —
 * dan staf tidak pernah bisa mengubah akunnya sendiri.
 */
class StafController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->aturanData(),
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], $this->pesan());

        $modul = $this->modulBoleh($request, $data['modul'] ?? []);
        $peran = $this->peranBoleh($request, $data['peran_id'] ?? null);

        $staf = DB::transaction(function () use ($data, $modul, $peran) {
            $staf = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'school_id' => null,
                'penyelenggara_id' => null,
                'must_change_password' => true,
            ]);
            // Hook BelongsToSchool mengisi school_id dari sekolah pembuat;
            // akun staf General wajib tanpa sekolah.
            $staf->forceFill(['school_id' => null])->saveQuietly();
            $staf->syncRoles(array_filter([UserRole::StafGeneral->value, $peran?->name]));
            $staf->syncPermissions($modul);

            return $staf;
        });

        $this->catat($request, $staf, 'staf general dibuat', ['peran' => $peran?->name, 'modul' => $modul]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Akun {$staf->name} dibuat. Ia wajib mengganti sandi saat login pertama."]);

        return back();
    }

    public function update(Request $request, string $user): RedirectResponse
    {
        $staf = $this->temukan($request, $user);
        $data = $request->validate([
            ...$this->aturanData(),
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staf->id)],
        ], $this->pesan());

        $modul = $this->modulBoleh($request, $data['modul'] ?? [], $staf);
        $peran = $this->peranBoleh($request, $data['peran_id'] ?? null, $staf);

        DB::transaction(function () use ($staf, $data, $modul, $peran) {
            $staf->update(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
            $staf->syncRoles(array_filter([UserRole::StafGeneral->value, $peran?->name]));
            $staf->syncPermissions($modul);
        });

        $this->catat($request, $staf, 'staf general diubah', ['peran' => $peran?->name, 'modul' => $modul]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Akun {$staf->name} disimpan."]);

        return back();
    }

    public function status(Request $request, string $user): RedirectResponse
    {
        $staf = $this->temukan($request, $user);
        $aktif = (bool) $request->validate(['is_active' => ['required', 'boolean']])['is_active'];

        $staf->forceFill(['is_active' => $aktif])->save();

        $this->catat($request, $staf, $aktif ? 'staf general diaktifkan' : 'staf general dinonaktifkan');
        Inertia::flash('toast', ['type' => 'success', 'message' => $aktif ? "Akun {$staf->name} diaktifkan." : "Akun {$staf->name} dinonaktifkan; ia tidak bisa membuka workspace General."]);

        return back();
    }

    public function sandi(Request $request, string $user): RedirectResponse
    {
        $staf = $this->temukan($request, $user);
        $validated = $request->validate([
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], [
            'password.required' => 'Sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi sandi tidak cocok.',
        ]);

        $staf->forceFill(['password' => Hash::make($validated['password']), 'must_change_password' => true])->save();

        $this->catat($request, $staf, 'sandi staf general direset');
        Inertia::flash('toast', ['type' => 'success', 'message' => "Sandi {$staf->name} direset. Ia wajib menggantinya saat login."]);

        return back();
    }

    public function destroy(Request $request, string $user): RedirectResponse
    {
        $staf = $this->temukan($request, $user);

        DB::transaction(function () use ($staf) {
            $staf->syncPermissions([]);
            $staf->syncRoles([UserRole::StafGeneral->value]);
            $staf->delete();
        });

        $this->catat($request, $staf, 'staf general dihapus');
        Inertia::flash('toast', ['type' => 'success', 'message' => "Akun {$staf->name} dihapus."]);

        return back();
    }

    /**
     * Daftar staf untuk halaman Pengguna.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function daftar(): array
    {
        return self::queryStaf()
            ->with(['permissions:id,name', 'roles' => fn ($q) => $q->with('permissions:id,name')])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'is_active' => $u->is_active,
                'peran' => ($p = $u->roles->firstWhere('lingkup', Role::LINGKUP_GENERAL)) ? ['id' => $p->id, 'name' => $p->name] : null,
                // Modul centang langsung (tambahan di luar peran).
                'modul' => $u->permissions->pluck('name')->values(),
                // Modul yang benar-benar dipegang: dari peran + tambahan.
                'modul_efektif' => $u->roles->flatMap(fn ($r) => $r->permissions->pluck('name'))
                    ->merge($u->permissions->pluck('name'))->unique()->values(),
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return Builder<User>
     */
    private static function queryStaf(): Builder
    {
        return User::query()
            ->whereNull('school_id')
            ->whereNull('penyelenggara_id')
            ->role(UserRole::StafGeneral->value);
    }

    private function temukan(Request $request, string $id): User
    {
        $staf = self::queryStaf()->findOrFail($id);

        abort_if($staf->is($request->user()), 403, 'Akun sendiri diubah lewat menu Profil, bukan di sini.');
        abort_if($staf->isSuperAdmin(), 403);

        return $staf;
    }

    /**
     * Modul khusus super admin (Staf & Peran Staf) hanya bisa diberikan atau
     * dicabut oleh super admin — staf tidak bisa menaikkan hak siapa pun.
     *
     * @param  array<int, string>  $diminta
     * @return array<int, string>
     */
    private function modulBoleh(Request $request, array $diminta, ?User $staf = null): array
    {
        $modul = array_values(array_unique($diminta));

        if ($request->user()->isSuperAdmin()) {
            return $modul;
        }

        foreach (ModulGeneral::cases() as $m) {
            if (! $m->khususSuperAdmin()) {
                continue;
            }

            $modul = array_values(array_filter($modul, fn (string $p) => $p !== $m->permission()));

            if ($staf?->hasDirectPermission($m->permission())) {
                $modul[] = $m->permission();
            }
        }

        return $modul;
    }

    /**
     * Peran staf yang boleh dipasang. Staf tidak bisa memasang peran yang
     * berisi modul khusus super admin.
     */
    private function peranBoleh(Request $request, ?string $id, ?User $staf = null): ?Role
    {
        if (! $id) {
            return null;
        }

        $peran = Role::query()->peranStaf()->findOrFail($id);

        if (! $request->user()->isSuperAdmin()
            && ! $staf?->hasRole($peran->name)
            && $peran->permissions->contains(fn ($p) => in_array($p->name, array_map(fn (ModulGeneral $m) => $m->permission(), array_filter(ModulGeneral::cases(), fn (ModulGeneral $m) => $m->khususSuperAdmin())), true))) {
            abort(403, 'Peran ini berisi modul yang hanya bisa diberikan super admin.');
        }

        return $peran;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function aturanData(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'peran_id' => ['nullable', 'string'],
            'modul' => ['array'],
            'modul.*' => ['string', Rule::in(ModulGeneral::permissions())],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function pesan(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.unique' => 'Email ini sudah dipakai akun lain (termasuk akun yang sudah dihapus).',
            'password.confirmed' => 'Konfirmasi sandi tidak cocok.',
            'modul.*.in' => 'Ada modul yang tidak dikenal.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function catat(Request $request, User $staf, string $pesan, array $data = []): void
    {
        activity()->performedOn($staf)->causedBy($request->user())->withProperties($data)->log($pesan);
    }
}
