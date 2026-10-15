import { Form, Head, Link, router } from '@inertiajs/react';
import { Check, Copy, ExternalLink, ImageIcon, Search, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import {
    LABEL_STATUS_IZIN,
    rentangTanggalIzin,
    WARNA_STATUS_IZIN,
} from '@/components/izin/izin-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { bukti, index, setujui, tolak } from '@/routes/admin/perizinan';

type Izin = {
    id: string;
    jenis: 'IZIN' | 'SAKIT';
    tanggal_mulai: string;
    tanggal_selesai: string;
    alasan: string;
    status: string;
    sumber: 'portal' | 'publik';
    pengirim_nama: string | null;
    pengirim_telepon: string | null;
    ada_bukti: boolean;
    catatan_review: string | null;
    hari_dicatat: number | null;
    reviewer: string | null;
    reviewed_at: string | null;
    dikirim: string | null;
    siswa: {
        id: string | null;
        nama: string | null;
        nis: string | null;
        kelas: string | null;
        foto: string | null;
    };
};

type Props = {
    izin: {
        data: Izin[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    ringkasan: { menunggu: number; disetujui: number; ditolak: number };
    classrooms: { id: string; name: string }[];
    filters: { status: string; kelas: string; q: string };
    linkPublik: string;
};

const TAB = [
    { nilai: 'menunggu', label: 'Menunggu' },
    { nilai: 'disetujui', label: 'Disetujui' },
    { nilai: 'ditolak', label: 'Ditolak' },
    { nilai: 'semua', label: 'Semua' },
] as const;

const waktu = (iso: string | null) =>
    iso
        ? new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(iso))
        : '—';

/** Data Perizinan: tinjau pengajuan izin/sakit dari orang tua. */
export default function Perizinan({
    izin,
    ringkasan,
    classrooms,
    filters,
    linkPublik,
}: Props) {
    const [tolakUntuk, setTolakUntuk] = useState<Izin | null>(null);
    const [lihatBukti, setLihatBukti] = useState<Izin | null>(null);
    const [tersalin, setTersalin] = useState(false);

    const muat = (patch: Partial<Props['filters']>) =>
        router.get(
            index.url(),
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );

    const salinLink = async () => {
        await navigator.clipboard.writeText(linkPublik);
        setTersalin(true);
        setTimeout(() => setTersalin(false), 2000);
    };

    return (
        <>
            <Head title="Data Perizinan" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Data Perizinan
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Pengajuan izin/sakit dari orang tua. Yang disetujui
                            langsung mengisi absensi Izin/Sakit.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={salinLink}>
                            {tersalin ? (
                                <Check className="size-4" />
                            ) : (
                                <Copy className="size-4" />
                            )}
                            {tersalin ? 'Tersalin' : 'Salin link form izin'}
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={linkPublik}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink className="size-4" /> Buka form
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2" role="tablist">
                    {TAB.map((t) => {
                        const jumlah =
                            t.nilai === 'semua' ? null : ringkasan[t.nilai];

                        return (
                            <button
                                key={t.nilai}
                                role="tab"
                                aria-selected={filters.status === t.nilai}
                                onClick={() => muat({ status: t.nilai })}
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors',
                                    filters.status === t.nilai
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'hover:bg-muted',
                                )}
                            >
                                {t.label}
                                {jumlah !== null && (
                                    <span
                                        className={cn(
                                            'rounded-full px-1.5 text-xs tabular-nums',
                                            filters.status === t.nilai
                                                ? 'bg-white/20'
                                                : 'bg-muted',
                                        )}
                                    >
                                        {jumlah}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <select
                        aria-label="Kelas"
                        className="h-9 rounded-md border bg-background px-2 text-sm sm:w-48"
                        value={filters.kelas}
                        onChange={(e) => muat({ kelas: e.target.value })}
                    >
                        <option value="">Semua kelas</option>
                        {classrooms.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                    <div className="relative sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-9"
                            placeholder="Cari nama siswa…"
                            defaultValue={filters.q}
                            onKeyDown={(e) =>
                                e.key === 'Enter' &&
                                muat({ q: e.currentTarget.value })
                            }
                        />
                    </div>
                </div>

                {izin.data.length === 0 ? (
                    <p className="rounded-lg border py-12 text-center text-sm text-muted-foreground">
                        {filters.status === 'menunggu'
                            ? 'Tidak ada pengajuan yang menunggu tinjauan.'
                            : 'Belum ada pengajuan.'}
                    </p>
                ) : (
                    <ul className="grid gap-3">
                        {izin.data.map((i) => (
                            <li
                                key={i.id}
                                className="rounded-xl border bg-card p-4"
                            >
                                <div className="flex flex-wrap items-start gap-4">
                                    <div className="flex min-w-0 flex-1 gap-3">
                                        {i.siswa.foto ? (
                                            <img
                                                src={i.siswa.foto}
                                                alt=""
                                                className="size-11 shrink-0 rounded-lg object-cover"
                                            />
                                        ) : (
                                            <span className="size-11 shrink-0 rounded-lg bg-muted" />
                                        )}
                                        <div className="min-w-0">
                                            <p className="font-semibold">
                                                {i.siswa.id ? (
                                                    <Link
                                                        href={`/admin/siswa/${i.siswa.id}?tab=absensi`}
                                                        className="hover:underline"
                                                    >
                                                        {i.siswa.nama}
                                                    </Link>
                                                ) : (
                                                    i.siswa.nama
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Kelas {i.siswa.kelas ?? '—'} ·
                                                NIS {i.siswa.nis ?? '—'}
                                            </p>
                                        </div>
                                    </div>
                                    <span
                                        className={cn(
                                            'rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                            WARNA_STATUS_IZIN[i.status],
                                        )}
                                    >
                                        {LABEL_STATUS_IZIN[i.status] ??
                                            i.status}
                                    </span>
                                </div>

                                <div className="mt-3 grid gap-1 text-sm">
                                    <p>
                                        <span className="font-medium">
                                            {i.jenis === 'SAKIT'
                                                ? 'Sakit'
                                                : 'Izin'}
                                        </span>{' '}
                                        ·{' '}
                                        {rentangTanggalIzin(
                                            i.tanggal_mulai,
                                            i.tanggal_selesai,
                                        )}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {i.alasan}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Dikirim {waktu(i.dikirim)} lewat{' '}
                                        {i.sumber === 'portal'
                                            ? 'portal orang tua'
                                            : 'form publik'}
                                        {i.pengirim_nama &&
                                            ` · ${i.pengirim_nama}`}
                                        {i.pengirim_telepon &&
                                            ` (${i.pengirim_telepon})`}
                                    </p>
                                    {i.status !== 'menunggu' && (
                                        <p className="text-xs text-muted-foreground">
                                            {i.status === 'disetujui'
                                                ? 'Disetujui'
                                                : 'Ditolak'}{' '}
                                            oleh {i.reviewer ?? '—'}{' '}
                                            {waktu(i.reviewed_at)}
                                            {i.status === 'disetujui' &&
                                                i.hari_dicatat !== null &&
                                                ` · ${i.hari_dicatat} hari dicatat`}
                                            {i.catatan_review &&
                                                ` · "${i.catatan_review}"`}
                                        </p>
                                    )}
                                </div>

                                <div className="mt-3 flex flex-wrap gap-2">
                                    {i.ada_bukti && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setLihatBukti(i)}
                                        >
                                            <ImageIcon className="size-4" />{' '}
                                            Lihat bukti
                                        </Button>
                                    )}
                                    {i.status === 'menunggu' && (
                                        <>
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    window.confirm(
                                                        `Setujui ${i.jenis === 'SAKIT' ? 'sakit' : 'izin'} ${i.siswa.nama}? Absensi hari sekolah dalam rentang tanggal akan dicatat.`,
                                                    ) &&
                                                    router.post(
                                                        setujui.url(i.id),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Check className="size-4" />{' '}
                                                Setujui
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setTolakUntuk(i)}
                                            >
                                                <X className="size-4" /> Tolak
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>{izin.total} pengajuan</span>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!izin.prev_page_url}
                            onClick={() =>
                                izin.prev_page_url &&
                                router.get(izin.prev_page_url)
                            }
                        >
                            Sebelumnya
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!izin.next_page_url}
                            onClick={() =>
                                izin.next_page_url &&
                                router.get(izin.next_page_url)
                            }
                        >
                            Berikutnya
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                open={tolakUntuk !== null}
                onOpenChange={(buka) => !buka && setTolakUntuk(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tolak pengajuan</DialogTitle>
                        <DialogDescription>
                            Alasan penolakan terlihat oleh orang tua di portal.
                        </DialogDescription>
                    </DialogHeader>
                    {tolakUntuk && (
                        <Form
                            {...tolak.form(tolakUntuk.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setTolakUntuk(null)}
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="catatan">
                                            Alasan penolakan
                                        </Label>
                                        <Textarea
                                            id="catatan"
                                            name="catatan"
                                            rows={3}
                                            required
                                            placeholder="Contoh: mohon lampirkan surat dokter."
                                        />
                                        <InputError
                                            message={
                                                errors.catatan ?? errors.status
                                            }
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setTolakUntuk(null)}
                                        >
                                            Batal
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            Tolak pengajuan
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={lihatBukti !== null}
                onOpenChange={(buka) => !buka && setLihatBukti(null)}
            >
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            Bukti — {lihatBukti?.siswa.nama}
                        </DialogTitle>
                    </DialogHeader>
                    {lihatBukti && (
                        <img
                            src={bukti.url(lihatBukti.id)}
                            alt="Bukti izin"
                            className="max-h-[70vh] w-full rounded-lg bg-muted object-contain"
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

Perizinan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Perizinan', href: index() },
    ],
};
