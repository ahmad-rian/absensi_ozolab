<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolChannelType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ParentProfile;
use App\Models\SchoolNotificationChannel;
use App\Models\Student;
use App\Models\User;
use App\Support\ParentProfileForm;
use App\Support\StudentAssetPurge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

        $telegramActive = SchoolNotificationChannel::where('school_id', auth()->user()->school_id)
            ->where('channel', SchoolChannelType::Telegram->value)
            ->where('is_active', true)
            ->exists();

        return Inertia::render('admin/orang-tua/index', [
            'parents' => $parents,
            'telegramActive' => $telegramActive,
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
        $schoolId = auth()->user()->school_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'regex:/^[^\\r\\n]*$/', 'unique:users,email'],
            'notification_email' => ['nullable', 'email', 'max:255', 'regex:/^[^\\r\\n]*$/'],
            'phone' => [
                'required', 'string', 'max:20', 'regex:/^[0-9]+$/',
                Rule::unique('parent_profiles', 'whatsapp_number')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'relation' => ['required', 'in:AYAH,IBU,WALI'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'telegram_chat_id' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'nik' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
            'phone.regex' => 'Nomor WhatsApp hanya boleh angka.',
            'phone.unique' => 'Nomor WhatsApp sudah dipakai orang tua lain.',
            'telegram_chat_id.regex' => 'Telegram Chat ID hanya boleh angka.',
            'nik.regex' => 'NIK hanya boleh angka.',
            'relation.required' => 'Hubungan wajib dipilih.',
            'relation.in' => 'Hubungan tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        DB::transaction(function () use ($validated, $schoolId) {
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
                'email' => $validated['notification_email'] ?? $validated['email'],
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
        ParentProfileForm::apply($parentProfile, $request->validate(
            ParentProfileForm::rules($parentProfile),
            ParentProfileForm::messages(),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data orang tua berhasil diperbarui.']);

        return to_route('admin.orang-tua.index');
    }

    /**
     * Pintasan ubah orang tua dari halaman detail siswa.
     *
     * Aturan dan penyimpanannya sama persis dengan `update()`; yang berbeda
     * hanya tujuannya — operator dikembalikan ke siswa yang sedang dilihatnya,
     * bukan dilempar ke daftar orang tua.
     *
     * Rutenya duduk di grup `permission:orang-tua.access`, jadi Guru — yang
     * memegang `siswa.access` tapi tidak `orang-tua.access` — tertahan di
     * middleware tanpa penjaga tambahan di sini.
     */
    public function updateFromStudent(Request $request, Student $siswa): RedirectResponse
    {
        $parentProfile = $siswa->parentProfile;

        abort_if($parentProfile === null, 404, 'Siswa ini belum punya orang tua tertaut.');

        ParentProfileForm::apply($parentProfile, $request->validate(
            ParentProfileForm::rules($parentProfile),
            ParentProfileForm::messages(),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data orang tua berhasil diperbarui.']);

        return to_route('admin.siswa.show', $siswa);
    }

    public function destroy(ParentProfile $parentProfile): RedirectResponse
    {
        DB::transaction(function () use ($parentProfile) {
            $user = $parentProfile->user;

            // `students.parent_profile_id` ber-cascadeOnDelete dan ParentProfile
            // TIDAK memakai SoftDeletes, jadi baris siswanya benar-benar lenyap
            // di sini. Cascade tingkat database tidak memicu event Eloquent,
            // jadi tidak ada observer yang akan tahu — antreannya harus disusun
            // eksplisit, dan sebelum induknya dihapus selagi bahannya masih ada.
            foreach ($parentProfile->students()->withoutGlobalScope('school')->get() as $student) {
                StudentAssetPurge::queue($student, auth()->user());
            }

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
