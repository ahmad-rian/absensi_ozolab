<?php

namespace App\Http\Controllers\General;

use App\Enums\ModulGeneral;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Peran staf General = template modul yang bisa dibuat sendiri (mis. "Operator
 * Haji", "Admin Kartu"). Staf mendapat modul dari perannya plus centang
 * tambahan per akun. Disimpan sebagai role Spatie ber-`lingkup = general`,
 * jadi tidak pernah muncul di Role & Hak Akses sekolah.
 */
class PeranStafController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        DB::transaction(function () use ($data, $request) {
            $peran = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $peran->forceFill(['lingkup' => Role::LINGKUP_GENERAL])->save();
            $peran->syncPermissions($this->modulBoleh($request, $data['modul'] ?? []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Peran {$data['name']} dibuat."]);

        return back();
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $peran = $this->temukan($role);
        $data = $this->validasi($request, $peran);

        DB::transaction(function () use ($peran, $data, $request) {
            $peran->update(['name' => $data['name']]);
            $peran->syncPermissions($this->modulBoleh($request, $data['modul'] ?? []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Peran {$peran->name} disimpan. Semua staf dengan peran ini langsung ikut berubah."]);

        return back();
    }

    public function destroy(string $role): RedirectResponse
    {
        $peran = $this->temukan($role);
        $dipakai = $peran->users()->count();

        if ($dipakai > 0) {
            return back()->withErrors(['peran' => "Peran {$peran->name} masih dipakai {$dipakai} staf. Ganti peran mereka dulu."]);
        }

        $peran->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => "Peran {$peran->name} dihapus."]);

        return back();
    }

    /**
     * @return array<int, array{id: string, name: string, modul: array<int, string>, jumlah_staf: int}>
     */
    public static function daftar(): array
    {
        return Role::query()->peranStaf()->with('permissions:id,name')->withCount('users')->orderBy('name')->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'modul' => $r->permissions->pluck('name')->values()->all(),
                'jumlah_staf' => $r->users_count,
            ])->all();
    }

    private function temukan(string $id): Role
    {
        return Role::query()->peranStaf()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Role $peran = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::notIn(array_map(fn (UserRole $r) => $r->value, UserRole::cases())),
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($peran?->id),
            ],
            'modul' => ['array'],
            'modul.*' => ['string', Rule::in(ModulGeneral::permissions())],
        ], [
            'name.required' => 'Nama peran wajib diisi.',
            'name.unique' => 'Nama peran sudah dipakai (termasuk peran sekolah). Pakai nama lain.',
            'name.not_in' => 'Nama ini dipakai peran bawaan sistem.',
            'modul.*.in' => 'Ada modul yang tidak dikenal.',
        ]);
    }

    /**
     * Modul khusus super admin (Staf & Peran Staf) tidak bisa dimasukkan ke
     * peran oleh staf — memasukkannya sama dengan menaikkan hak sendiri.
     *
     * @param  array<int, string>  $diminta
     * @return array<int, string>
     */
    private function modulBoleh(Request $request, array $diminta): array
    {
        $modul = array_values(array_unique($diminta));

        if ($request->user()->isSuperAdmin()) {
            return $modul;
        }

        $khusus = array_map(fn (ModulGeneral $m) => $m->permission(), array_filter(ModulGeneral::cases(), fn (ModulGeneral $m) => $m->khususSuperAdmin()));

        return array_values(array_diff($modul, $khusus));
    }
}
