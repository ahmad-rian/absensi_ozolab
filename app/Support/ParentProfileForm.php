<?php

namespace App\Support;

use App\Models\ParentProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Aturan dan penyimpanan data orang tua, dipakai bersama dua jalur masuk:
 * halaman Orang Tua dan pintasan di halaman detail siswa.
 *
 * Alasan ini berdiri sendiri, bukan disalin: satu field menulis ke DUA tabel.
 * `phone` masuk ke `users.phone` sekaligus `parent_profiles.whatsapp_number`,
 * dan `notification_email` punya logika mundur `@internal.app`. Dua salinan
 * aturan seperti itu pasti menyimpang, dan menyimpangnya berarti nomor tujuan
 * notifikasi WhatsApp berbeda tergantung lewat halaman mana ia diubah.
 */
class ParentProfileForm
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(ParentProfile $parent): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'regex:/^[^\\r\\n]*$/', 'unique:users,email,'.$parent->user_id],
            'notification_email' => ['nullable', 'email', 'max:255', 'regex:/^[^\\r\\n]*$/'],
            'phone' => [
                'required', 'string', 'max:20', 'regex:/^[0-9]+$/',
                // Dikunci ke sekolahnya sendiri: nomor yang sama boleh dipakai
                // di sekolah lain, dan `parent_profiles` memang punya unique
                // index `(school_id, whatsapp_number)`.
                Rule::unique('parent_profiles', 'whatsapp_number')
                    ->where(fn ($q) => $q->where('school_id', $parent->school_id))
                    ->ignore($parent->id),
            ],
            'relation' => ['required', 'in:AYAH,IBU,WALI'],
            'telegram_chat_id' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'nik' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function apply(ParentProfile $parent, array $validated): void
    {
        DB::transaction(function () use ($parent, $validated) {
            $parent->user?->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ]);

            $parent->update([
                'whatsapp_number' => $validated['phone'],
                'telegram_chat_id' => $validated['telegram_chat_id'] ?? null,
                // Email notifikasi kosong berarti pakai email akun — kecuali
                // akun itu sendiri memakai alamat `@internal.app` yang dibuat
                // sistem dan tidak akan pernah sampai ke siapa pun.
                'email' => ($validated['notification_email'] ?? null) ?: (str_contains($validated['email'], '@internal.app') ? null : $validated['email']),
                'relation' => $validated['relation'],
                'nik' => $validated['nik'] ?? null,
                'occupation' => $validated['occupation'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
            ]);
        });
    }
}
