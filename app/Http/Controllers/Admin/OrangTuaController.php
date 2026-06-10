<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class OrangTuaController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $parents = ParentProfile::forSchool()
            ->with(['user', 'students.classroom'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    })->orWhere('whatsapp_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/orang-tua/index', [
            'parents' => $parents,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/orang-tua/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'relation' => ['required', 'in:AYAH,IBU,WALI'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'telegram_chat_id' => ['nullable', 'string', 'max:50'],
            'nik' => ['nullable', 'string', 'max:20'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
            'relation.required' => 'Hubungan wajib dipilih.',
            'relation.in' => 'Hubungan tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        DB::transaction(function () use ($validated) {
            $schoolId = auth()->user()->school_id;

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'school_id' => $schoolId,
            ]);

            $user->assignRole(UserRole::OrangTua);

            $user->parentProfile()->create([
                'whatsapp_number' => $validated['phone'],
                'telegram_chat_id' => $validated['telegram_chat_id'] ?? null,
                'relation' => $validated['relation'],
                'nik' => $validated['nik'] ?? null,
                'occupation' => $validated['occupation'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'school_id' => $schoolId,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data orang tua berhasil ditambahkan.']);

        return to_route('admin.orang-tua.index');
    }

    public function show(ParentProfile $parentProfile): Response
    {
        $parentProfile->load(['user', 'students.classroom']);

        return Inertia::render('admin/orang-tua/show', [
            'parent' => $parentProfile,
        ]);
    }

    public function edit(ParentProfile $parentProfile): Response
    {
        $parentProfile->load('user');

        return Inertia::render('admin/orang-tua/edit', [
            'parent' => $parentProfile,
        ]);
    }

    public function update(Request $request, ParentProfile $parentProfile): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$parentProfile->user_id],
            'phone' => ['required', 'string', 'max:20'],
            'relation' => ['required', 'in:AYAH,IBU,WALI'],
            'telegram_chat_id' => ['nullable', 'string', 'max:50'],
            'nik' => ['nullable', 'string', 'max:20'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
            'relation.required' => 'Hubungan wajib dipilih.',
            'relation.in' => 'Hubungan tidak valid.',
        ]);

        $parentProfile->user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);

        $parentProfile->update([
            'whatsapp_number' => $validated['phone'],
            'telegram_chat_id' => $validated['telegram_chat_id'] ?? null,
            'relation' => $validated['relation'],
            'nik' => $validated['nik'] ?? null,
            'occupation' => $validated['occupation'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data orang tua berhasil diperbarui.']);

        return to_route('admin.orang-tua.index');
    }

    public function destroy(ParentProfile $parentProfile): RedirectResponse
    {
        DB::transaction(function () use ($parentProfile) {
            $user = $parentProfile->user;
            $parentProfile->delete();

            $hasOtherProfiles = $user->roles()->where('name', '!=', UserRole::OrangTua->value)->exists();
            if (! $hasOtherProfiles) {
                $user->delete();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data orang tua berhasil dihapus.']);

        return to_route('admin.orang-tua.index');
    }
}
