import { Bell, Building2, ImageIcon, MoonStar, ToggleRight, type LucideIcon } from 'lucide-react';

export type SettingsSection = 'umum' | 'tampilan' | 'fitur' | 'sholat' | 'notifikasi';

export type SettingsTab = {
    value: SettingsSection;
    label: string;
    icon: LucideIcon;
    /** Tab tanpa form (upload langsung) tidak punya tombol Simpan. */
    savable: boolean;
};

/**
 * Registri, bukan JSX hardcode: menambah bagian ke-6 cukup satu entri di sini
 * dan satu `<TabsContent>` — daftar tab, whitelist URL, dan indikator perubahan
 * semuanya diturunkan dari array ini.
 */
export const SETTINGS_TABS: SettingsTab[] = [
    { value: 'umum', label: 'Umum', icon: Building2, savable: true },
    { value: 'tampilan', label: 'Tampilan', icon: ImageIcon, savable: false },
    { value: 'fitur', label: 'Fitur', icon: ToggleRight, savable: true },
    { value: 'sholat', label: 'Absen Sholat', icon: MoonStar, savable: true },
    { value: 'notifikasi', label: 'Notifikasi', icon: Bell, savable: true },
];

export const SETTINGS_SECTIONS: SettingsSection[] = SETTINGS_TABS.map((tab) => tab.value);

export function resolveSettingsTab(url: string): SettingsSection {
    // `usePage().url` sudah berisi path + query, aman dipakai saat SSR — beda
    // dengan window.location yang butuh guard tersendiri.
    const requested = new URL(url, 'http://localhost').searchParams.get('tab');

    return SETTINGS_SECTIONS.includes(requested as SettingsSection)
        ? (requested as SettingsSection)
        : 'umum';
}

export type SettingsValues = Record<string, string | number | boolean>;
