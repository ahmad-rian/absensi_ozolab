import { usePage } from '@inertiajs/react';

export type NamaSholat = {
    dhuha: string;
    dzuhur: string;
};

const BAWAAN: NamaSholat = { dhuha: 'Dhuha', dzuhur: 'Dzuhur' };

/**
 * Nama tampilan sholat milik sekolah aktif, dari shared prop `namaSholat`.
 *
 * Halaman tanpa konteks sekolah (atau yang dirender sebelum prop ada) jatuh
 * ke nama bawaan, jadi aman dipanggil di mana saja.
 */
export function useNamaSholat(): NamaSholat {
    const { namaSholat } = usePage().props as { namaSholat?: NamaSholat };

    return namaSholat ?? BAWAAN;
}

/**
 * "Sholat Dhuha", kecuali nama sekolah sudah diawali kata sholat.
 * Cermin dari PrayerType::label() di server.
 */
export function labelSholat(nama: string): string {
    return /^(sholat|shalat|salat|solat)\b/i.test(nama)
        ? nama
        : `Sholat ${nama}`;
}
