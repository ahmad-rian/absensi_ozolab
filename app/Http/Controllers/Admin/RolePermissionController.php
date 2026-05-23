<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    public function index(): Response
    {
        $roles = Role::with('permissions')->get()->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => $role->users()->count(),
        ]);

        $permissions = Permission::orderBy('name')->pluck('name');

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.required' => 'Nama role wajib diisi.',
            'name.unique' => 'Nama role sudah digunakan.',
        ]);

        $role = Role::create(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return to_route('admin.roles');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return to_route('admin.roles');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->count() > 0) {
            return to_route('admin.roles')->withErrors(['delete' => 'Role masih digunakan oleh pengguna.']);
        }

        $role->delete();

        return to_route('admin.roles');
    }
}
