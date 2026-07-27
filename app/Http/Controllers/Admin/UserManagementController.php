<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppModule;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolId = auth()->user()->school_id;
        $isSuperAdmin = auth()->user()->isSuperAdmin();

        $query = User::with('roles')
            ->when(! $isSuperAdmin, fn ($q) => $q->where('school_id', $schoolId))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->role, fn ($q, $role) => $q->role($role))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/users/index', [
            'users' => $query,
            'roles' => $roles,
            'filters' => [
                'search' => $request->search ?? '',
                'role' => $request->role ?? '',
            ],
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/create', [
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'role' => ['required', 'string', Rule::in($this->assignableRoles()->pluck('name'))],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah digunakan.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'role.required' => 'Role wajib dipilih.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            'school_id' => auth()->user()->school_id,
            'is_active' => true,
        ]);

        $user->assignRole($validated['role']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna berhasil ditambahkan.']);

        return to_route('admin.users.index');
    }

    public function edit(User $user): Response
    {
        $isSuperAdmin = auth()->user()->isSuperAdmin();
        abort_unless($isSuperAdmin || $user->school_id === auth()->user()->school_id, 403);

        return Inertia::render('admin/users/edit', [
            'editUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'role' => $user->roles->first()?->name ?? '',
                'extra_permissions' => $user->getDirectPermissions()->pluck('name'),
            ],
            'roles' => $this->assignableRoles(),
            'modules' => $this->modulePayload(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $isSuperAdmin = auth()->user()->isSuperAdmin();
        abort_unless($isSuperAdmin || $user->school_id === auth()->user()->school_id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', Rule::in($this->assignableRoles()->pluck('name'))],
            'is_active' => ['sometimes', 'boolean'],
            'extra_permissions' => ['array'],
            'extra_permissions.*' => [Rule::in(AppModule::permissions())],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->syncRoles([$validated['role']]);

        // Akses tambahan di luar role — inilah bagian "custom per pengguna".
        $user->syncPermissions($validated['extra_permissions'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna berhasil diperbarui.']);

        return to_route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $isSuperAdmin = auth()->user()->isSuperAdmin();
        abort_unless($isSuperAdmin || $user->school_id === auth()->user()->school_id, 403);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['delete' => 'Tidak bisa menghapus akun sendiri.']);
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna berhasil dihapus.']);

        return to_route('admin.users.index');
    }

    /**
     * Role yang boleh diberikan oleh user yang sedang login. Hanya SUPER_ADMIN
     * yang boleh mengangkat SUPER_ADMIN lain; role custom ikut terdaftar.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles()
    {
        return Role::query()
            ->when(
                ! auth()->user()->isSuperAdmin(),
                fn ($q) => $q->where('name', '!=', UserRole::SuperAdmin->value),
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
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
}
