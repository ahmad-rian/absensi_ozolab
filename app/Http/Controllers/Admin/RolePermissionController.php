<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppModule;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    public function index(): Response
    {
        $roles = Role::with('permissions')->orderBy('name')->get()->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $this->roleLabel($role->name),
            'is_builtin' => $this->isBuiltin($role->name),
            'is_locked' => $role->name === UserRole::SuperAdmin->value,
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => $role->users()->count(),
        ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'modules' => $this->modulePayload(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(AppModule::permissions())],
        ], [
            'name.required' => 'Nama role wajib diisi.',
            'name.unique' => 'Nama role sudah digunakan.',
        ]);

        Role::create(['name' => $validated['name'], 'guard_name' => 'web'])
            ->syncPermissions($validated['permissions'] ?? []);

        return to_route('admin.roles');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === UserRole::SuperAdmin->value) {
            return to_route('admin.roles')
                ->withErrors(['permissions' => 'Super Admin selalu punya akses penuh dan tidak bisa diubah.']);
        }

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(AppModule::permissions())],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return to_route('admin.roles');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($this->isBuiltin($role->name)) {
            return to_route('admin.roles')->withErrors(['delete' => 'Role bawaan tidak bisa dihapus.']);
        }

        if ($role->users()->count() > 0) {
            return to_route('admin.roles')->withErrors(['delete' => 'Role masih digunakan oleh pengguna.']);
        }

        $role->delete();

        return to_route('admin.roles');
    }

    /**
     * Modul dikelompokkan supaya matrix di UI terbaca, bukan 24 checkbox datar.
     *
     * @return array<int, array{group: string, modules: array<int, array{permission: string, label: string}>}>
     */
    private function modulePayload(): array
    {
        return collect(AppModule::cases())
            ->groupBy(fn (AppModule $module) => $module->group())
            ->map(fn ($modules, $group) => [
                'group' => $group,
                'modules' => $modules
                    ->map(fn (AppModule $module) => [
                        'permission' => $module->permission(),
                        'label' => $module->label(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function isBuiltin(string $name): bool
    {
        return UserRole::tryFrom($name) !== null;
    }

    private function roleLabel(string $name): string
    {
        return UserRole::tryFrom($name)?->label() ?? $name;
    }
}
