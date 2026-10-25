import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCheck, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    show,
    store,
} from '@/actions/App/Http/Controllers/Admin/AbsensiKelasController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type Status = 'HADIR' | 'TERLAMBAT' | 'IZIN' | 'SAKIT' | 'ALPA';

type Siswa = {
    id: string;
    nis: string | null;
    nama: string;
    status: Status | null;
    catatan: string | null;
    sumber: 'scan' | 'manual' | null;
    jam: string | null;
};

type PageProps = {
    kelas: { id: string; name: string };
    daftarKelas: { id: string; name: string }[];
    tanggal: string;
    siswa: Siswa[];
    flash?: { success?: string; error?: string };
    errors: Record<string, string>;
};

type Pilihan = { status: Status | null; catatan: string };

const PILIHAN_STATUS: {
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

function awal(siswa: Siswa[]): Record<string, Pilihan> {
    return Object.fromEntries(
        siswa.map((s) => [
            s.id,
            { status: s.status, catatan: s.catatan ?? '' },
        ]),
    );
}

export default function AbsensiKelas() {
    const { kelas, tanggal } = usePage<PageProps>().props;

    // Form dipasang ulang saat kelas / tanggal berganti supaya pilihan lama tidak terbawa.
    return <FormKelas key={`${kelas.id}-${tanggal}`} />;
}

function FormKelas() {
    const { kelas, daftarKelas, tanggal, siswa, flash, errors } =
        usePage<PageProps>().props;
    const [pilihan, setPilihan] = useState<Record<string, Pilihan>>(() =>
        awal(siswa),
    );
    const [menyimpan, setMenyimpan] = useState(false);

    const berubah = useMemo(
        () =>
            siswa.filter((s) => {
                const p = pilihan[s.id];

                return (
                    p?.status !== null &&
                    p !== undefined &&
                    (p.status !== s.status || p.catatan !== (s.catatan ?? ''))
                );
            }),
        [siswa, pilihan],
    );

    const ringkasan = useMemo(() => {
        const hitung: Record<string, number> = {};
        Object.values(pilihan).forEach((p) => {
            const kunci = p.status ?? 'KOSONG';
            hitung[kunci] = (hitung[kunci] ?? 0) + 1;
        });

        return hitung;
    }, [pilihan]);

    function pilih(id: string, status: Status) {
        setPilihan((lama) => ({ ...lama, [id]: { ...lama[id], status } }));
    }

    function ubahCatatan(id: string, catatan: string) {
        setPilihan((lama) => ({ ...lama, [id]: { ...lama[id], catatan } }));
    }

    function tandaiSisaHadir() {
        setPilihan((lama) =>
            Object.fromEntries(
                Object.entries(lama).map(([id, p]) => [
                    id,
                    p.status === null ? { ...p, status: 'HADIR' as Status } : p,
                ]),
            ),
        );
    }

    function pindah(kelasId: string, tgl: string) {
        router.get(show.url(kelasId, { query: { tanggal: tgl } }));
    }

    function simpan() {
        router.post(
            store.url(kelas.id),
            {
                tanggal,
                baris: berubah.map((s) => ({
                    student_id: s.id,
                    status: pilihan[s.id].status,
                    catatan: pilihan[s.id].catatan || null,
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setMenyimpan(true),
                onFinish: () => setMenyimpan(false),
            },
        );
    }

    return (
        <>
            <Head title={`Absen Kelas ${kelas.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <Link
                            href="/admin/absensi"
                            className="mb-1 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                        >
                            <ArrowLeft className="size-4" /> Rekap Absensi
                        </Link>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Absen Kelas {kelas.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Pilih status masuk tiap siswa lalu simpan. Siswa
                            yang dibiarkan kosong tidak diubah.
                        </p>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}
                {(flash?.error || errors.baris || errors.tanggal) && (
                    <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                        {flash?.error ?? errors.baris ?? errors.tanggal}
                    </div>
                )}

                <Card>
                    <CardContent className="grid gap-4 pt-0 sm:grid-cols-3">
                        <div className="space-y-1.5">
                            <Label>Kelas</Label>
                            <Select
                                value={kelas.id}
                                onValueChange={(id) => pindah(id, tanggal)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {daftarKelas.map((k) => (
                                        <SelectItem key={k.id} value={k.id}>
                                            {k.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="tanggal">Tanggal</Label>
                            <Input
                                id="tanggal"
                                type="date"
                                value={tanggal}
                                onChange={(e) =>
                                    e.target.value &&
                                    pindah(kelas.id, e.target.value)
                                }
                            />
                        </div>
                        <div className="flex items-end">
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                onClick={tandaiSisaHadir}
                            >
                                <CheckCheck className="mr-2 size-4" /> Tandai
                                sisanya Hadir
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-2 text-sm">
                    {PILIHAN_STATUS.map((s) => (
                        <Badge key={s.nilai} variant="outline">
                            {s.label}: {ringkasan[s.nilai] ?? 0}
                        </Badge>
                    ))}
                    <Badge variant="outline">
                        Belum diisi: {ringkasan.KOSONG ?? 0}
                    </Badge>
                </div>

                {siswa.length === 0 ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">
                        Kelas ini belum punya siswa aktif.
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-2xl border bg-card">
                        {siswa.map((s, i) => {
                            const p = pilihan[s.id];

                            return (
                                <li
                                    key={s.id}
                                    className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:gap-4"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            <span className="mr-2 text-muted-foreground tabular-nums">
                                                {i + 1}.
                                            </span>
                                            {s.nama}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {s.nis ?? '-'}
                                            {s.sumber && (
                                                <span className="ml-2">
                                                    ·{' '}
                                                    {s.sumber === 'scan'
                                                        ? `scan ${s.jam ?? ''}`
                                                        : 'diisi manual'}
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                    <div
                                        className="flex gap-1.5"
                                        role="radiogroup"
                                        aria-label={`Status ${s.nama}`}
                                    >
                                        {PILIHAN_STATUS.map((opsi) => (
                                            <button
                                                key={opsi.nilai}
                                                type="button"
                                                role="radio"
                                                aria-checked={
                                                    p.status === opsi.nilai
                                                }
                                                title={opsi.label}
                                                onClick={() =>
                                                    pilih(s.id, opsi.nilai)
                                                }
                                                className={cn(
                                                    'size-9 rounded-lg border text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                    p.status === opsi.nilai
                                                        ? opsi.aktif
                                                        : 'hover:bg-muted',
                                                )}
                                            >
                                                {opsi.singkat}
                                            </button>
                                        ))}
                                    </div>
                                    <Input
                                        className="sm:w-48"
                                        placeholder="Catatan (opsional)"
                                        maxLength={500}
                                        value={p.catatan}
                                        onChange={(e) =>
                                            ubahCatatan(s.id, e.target.value)
                                        }
                                    />
                                </li>
                            );
                        })}
                    </ul>
                )}

                <div className="sticky bottom-0 z-20 -mx-4 border-t bg-background/95 p-3 backdrop-blur md:-mx-6">
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            {berubah.length} perubahan belum disimpan
                        </p>
                        <Button
                            type="button"
                            disabled={berubah.length === 0 || menyimpan}
                            onClick={simpan}
                        >
                            <Save className="mr-2 size-4" />
                            {menyimpan ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

AbsensiKelas.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Absensi', href: '/admin/absensi' },
        { title: 'Absen per kelas', href: '#' },
    ],
};
