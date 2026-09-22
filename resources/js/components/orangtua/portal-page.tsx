import { Head, router } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type Anak = {
    id: string;
    name: string;
    nis: string | null;
    classroom: string | null;
    school: string | null;
    photo: string | null;
};

export type Ringkasan = {
    rate: number;
    hadir: number;
    effective_days: number;
    terlambat?: number;
    izin?: number;
    sakit?: number;
    alpa?: number;
    tidak_hadir?: number;
};

export type Catatan = {
    id: string;
    date: string;
    status_label: string;
    time: string | null;
    type_label?: string | null;
};

export type Panel = {
    label: string;
    summary: Ringkasan;
    recent: Catatan[];
    by_weekday?: { series: BarisHari[] };
};

export type BarisHari = {
    weekday: string;
    effective: number;
    hadir: number;
    terlambat: number;
    izin: number;
    sakit: number;
    alpa: number;
};

export type Filters = { start_date: string; end_date: string };

/**
 * Kerangka setiap halaman portal: judul, identitas anak, dan penyaring periode.
 *
 * Penyaringnya hidup di sini, bukan disalin ke tiap halaman — periode yang
 * dipilih orang tua harus berarti sama di Absensi, Sholat, dan Laporan.
 */
export function PortalPage({
    title,
    description,
    student,
    filters,
    action,
    children,
}: {
    title: string;
    description?: string;
    student: Anak | null;
    filters?: Filters | null;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <main className="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
            <Head title={title} />

            <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <h1 className="truncate text-xl font-bold tracking-tight sm:text-2xl">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </header>

            {student && filters && (
                <PenyaringPeriode filters={filters} anak={student.id} />
            )}

            {children}
        </main>
    );
}

/**
 * Submit lewat `router.get` dan bukan `<form method=get>` supaya `?anak=`
 * ikut terbawa; form GET biasa akan menghapus query yang tidak jadi field.
 */
function PenyaringPeriode({
    filters,
    anak,
}: {
    filters: Filters;
    anak: string;
}) {
    function kirim(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.get(
            window.location.pathname,
            {
                anak,
                start_date: String(data.get('start_date') ?? ''),
                end_date: String(data.get('end_date') ?? ''),
            },
            { preserveScroll: true, preserveState: false },
        );
    }

    return (
        <form
            onSubmit={kirim}
            className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4"
        >
            <CalendarRange className="mb-2.5 hidden size-4 text-muted-foreground sm:block" />
            <div className="grid min-w-36 flex-1 gap-1.5">
                <Label htmlFor="start_date" className="text-xs">
                    Dari tanggal
                </Label>
                <Input
                    id="start_date"
                    type="date"
                    name="start_date"
                    defaultValue={filters.start_date}
                    required
                />
            </div>
            <div className="grid min-w-36 flex-1 gap-1.5">
                <Label htmlFor="end_date" className="text-xs">
                    Sampai tanggal
                </Label>
                <Input
                    id="end_date"
                    type="date"
                    name="end_date"
                    defaultValue={filters.end_date}
                    required
                />
            </div>
            <Button type="submit" className="min-w-28">
                Tampilkan
            </Button>
        </form>
    );
}

export function KosongTanpaAnak() {
    return (
        <div className="rounded-xl border bg-card p-8 text-center">
            <p className="font-medium">
                Belum ada anak yang tertaut ke akun ini.
            </p>
            <p className="mt-1 text-sm text-muted-foreground">
                Hubungi wali kelas atau operator sekolah untuk menghubungkan
                data anak Anda.
            </p>
        </div>
    );
}
