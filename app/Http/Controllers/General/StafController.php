<?php

namespace App\Http\Controllers\General;

use App\Enums\AppModule;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
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
 * dicentang per akun (permission langsung `general-*.access`).
 *
 * Akun staf tidak punya sekolah maupun penyelenggara. Hanya super admin yang
 * boleh memberi modul Pengguna — supaya staf tidak bisa menaikkan haknya
 * sendiri — dan staf tidak pernah bisa mengubah akunnya sendiri.
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

        $staf = DB::transaction(function () use ($data, $modul) {
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
            $staf->assignRole(UserRole::StafGeneral->value);
            $staf->syncPermissions($modul);

            return $staf;
        });

        $this->catat($request, $staf, 'staf general dibuat', ['modul' => $modul]);
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

        DB::transaction(function () use ($staf, $data, $modul) {
            $staf->update(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
            $staf->syncPermissions($modul);
        });

        $this->catat($request, $staf, 'staf general diubah', ['modul' => $modul]);
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
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'is_active' => $u->is_active,
                'modul' => $u->permissions->pluck('name')->values(),
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Katalog modul General untuk matriks centang.
     *
     * @return array<int, array{permission: string, label: string, keterangan: ?string}>
     */
    public static function katalogModul(): array
    {
        return array_map(fn (AppModule $m) => [
            'permission' => $m->permission(),
            'label' => $m->label(),
            'keterangan' => $m->keterangan(),
        ], AppModule::modulGeneral());
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
     * Hanya modul General yang sah; modul Pengguna hanya bisa diberikan (atau
     * dicabut) oleh super admin.
     *
     * @param  array<int, string>  $diminta
     * @return array<int, string>
     */
    private function modulBoleh(Request $request, array $diminta, ?User $staf = null): array
    {
        $pengguna = AppModule::GeneralPengguna->permission();
        $modul = array_values(array_unique($diminta));

        if (! $request->user()->isSuperAdmin()) {
            $punyaSebelumnya = $staf?->hasDirectPermission($pengguna) ?? false;
            $modul = array_values(array_filter($modul, fn (string $p) => $p !== $pengguna));

            if ($punyaSebelumnya) {
                $modul[] = $pengguna;
            }
        }

        return $modul;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function aturanData(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'modul' => ['array'],
            'modul.*' => ['string', Rule::in(array_map(fn (AppModule $m) => $m->permission(), AppModule::modulGeneral()))],
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
