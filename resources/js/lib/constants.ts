export const ATTENDANCE_STATUS = {
    HADIR: { label: 'Hadir', color: 'var(--color-chart-1)' },
    TERLAMBAT: { label: 'Terlambat', color: 'var(--color-chart-3)' },
    IZIN: { label: 'Izin', color: 'var(--color-chart-2)' },
    SAKIT: { label: 'Sakit', color: 'var(--color-chart-4)' },
    ALPA: { label: 'Alpa', color: 'var(--color-chart-5)' },
} as const;

export const GENDER = {
    LAKI_LAKI: { label: 'Laki-laki' },
    PEREMPUAN: { label: 'Perempuan' },
} as const;

export const PARENT_RELATION = {
    AYAH: { label: 'Ayah' },
    IBU: { label: 'Ibu' },
    WALI: { label: 'Wali' },
} as const;

export const USER_ROLE = {
    ADMIN: { label: 'Admin' },
    GURU: { label: 'Guru' },
    ORANG_TUA: { label: 'Orang Tua' },
} as const;

export const DAYS_OF_WEEK = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as const;
