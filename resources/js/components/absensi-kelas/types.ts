export type Status = 'HADIR' | 'TERLAMBAT' | 'IZIN' | 'SAKIT' | 'ALPA';

export type SiswaKelas = {
    id: string;
    nis: string | null;
    nisn: string | null;
    nama: string;
    status: Status | null;
    catatan: string | null;
    sumber: 'scan' | 'manual' | null;
    jam: string | null;
};

export type KelasRingkas = {
    id: string;
    name: string;
    grade_level: number | null;
    wali: string | null;
};

export type RekapBulanan = {
    bulan: string;
    hari: { tanggal: string; tgl: number; nama: string; lewat: boolean }[];
    baris: {
        id: string;
        nama: string;
        nis: string | null;
        status: Record<string, string>;
        jumlah: Record<'H' | 'T' | 'I' | 'S' | 'A', number>;
        persen: number | null;
    }[];
};

export const PILIHAN_STATUS: {
    nilai: Status;
    singkat: string;
    label: string;
    aktif: string;
}[] = [
    {
        nilai: 'HADIR',
        singkat: 'H',
        label: 'Hadir',
        aktif: 'bg-green-600 text-white border-green-600',
    },
    {
        nilai: 'TERLAMBAT',
        singkat: 'T',
        label: 'Terlambat',
        aktif: 'bg-amber-500 text-white border-amber-500',
    },
    {
        nilai: 'IZIN',
        singkat: 'I',
        label: 'Izin',
        aktif: 'bg-blue-600 text-white border-blue-600',
    },
    {
        nilai: 'SAKIT',
        singkat: 'S',
        label: 'Sakit',
        aktif: 'bg-purple-600 text-white border-purple-600',
    },
    {
        nilai: 'ALPA',
        singkat: 'A',
        label: 'Alpa',
        aktif: 'bg-red-600 text-white border-red-600',
    },
];
