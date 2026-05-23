<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $schoolId = auth()->user()->school_id;
        $isSuperAdmin = auth()->user()->hasRole(UserRole::SuperAdmin->value);

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
        $roles = Role::whereIn('name', [
            UserRole::Admin->value,
            UserRole::Guru->value,
        ])->get(['id', 'name']);

        return Inertia::render('admin/users/create', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'role' => ['required', 'string', 'exists:roles,name'],
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
        $isSuperAdmin = auth()->user()->hasRole(UserRole::SuperAdmin->value);
        abort_unless($isSuperAdmin || $user->school_id === auth()->user()->school_id, 403);

        $roles = Role::whereIn('name', [
            UserRole::Admin->value,
            UserRole::Guru->value,
        ])->get(['id', 'name']);

        return Inertia::render('admin/users/edit', [
            'editUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'role' => $user->roles->first()?->name ?? '',
            ],
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $isSuperAdmin = auth()->user()->hasRole(UserRole::SuperAdmin->value);
        abort_unless($isSuperAdmin || $user->school_id === auth()->user()->school_id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $user->syncRoles([$validated['role']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna berhasil diperbarui.']);

        return to_route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $isSuperAdmin = auth()->user()->hasRole(UserRole::SuperAdmin->value);
        abort_unless($isSuperAdmin || $user->school_id === auth()->user()->school_id, 403);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['delete' => 'Tidak bisa menghapus akun sendiri.']);
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna berhasil dihapus.']);

        return to_route('admin.users.index');
    }
}
