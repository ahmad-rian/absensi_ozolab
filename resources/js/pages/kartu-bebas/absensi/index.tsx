import {
    Head,
    Link,
    router,
    useForm,
    usePoll,
    usePage,
} from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import {
    downloadCards,
    index,
    regenerate,
    rotate,
    store,
} from '@/routes/kartu-bebas/absensi';
import { index as report } from '@/routes/kartu-bebas/laporan';
import { edit } from '@/routes/kartu-bebas/layouts';

type Attendance = {
    status: string;
    check_in: string | null;
    check_out: string | null;
    note: string | null;
};
type Participant = {
    id: string;
    name: string;
    photo_url: string | null;
    attendance: Attendance | null;
};
type Layout = {
    id: string;
    name: string;
    is_active: boolean;
    scan_url: string;
    light_url: string;
    qr_ready: boolean;
};
type Props = {
    layouts: Layout[];
    filters: { layout: string | null; date: string; q: string };
    participants: {
        data: Participant[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

function AttendanceRow({
    participant,
    date,
}: {
    participant: Participant;
    date: string;
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        date,
        status: participant.attendance?.status ?? 'hadir',
        check_in: participant.attendance?.check_in?.slice(0, 5) ?? '',
        check_out: participant.attendance?.check_out?.slice(0, 5) ?? '',
        note: participant.attendance?.note ?? '',
    });

    return (
        <TableRow>
            <TableCell>
                <span className="flex items-center gap-2">
                    {participant.photo_url && (
                        <img
                            src={participant.photo_url}
                            alt=""
                            className="h-12 w-9 rounded object-cover"
                        />
                    )}
                    {participant.name}
                </span>
            </TableCell>
            <TableCell>
                {participant.attendance?.status ?? 'Belum dicatat'}
            </TableCell>
            <TableCell>{participant.attendance?.check_in ?? '—'}</TableCell>
            <TableCell>{participant.attendance?.check_out ?? '—'}</TableCell>
            <TableCell>
                {editing ? (
                    <form
                        className="min-w-60 space-y-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(store.url(participant.id), {
                                preserveScroll: true,
                                onSuccess: () => setEditing(false),
                            });
                        }}
                    >
                        <select
                            aria-label="Status kehadiran"
                            className="rounded border bg-background p-2"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
                            }
                        >
                            {['hadir', 'izin', 'sakit', 'alpa'].map(
                                (status) => (
                                    <option key={status}>{status}</option>
                                ),
                            )}
                        </select>
                        {form.data.status === 'hadir' && (
                            <div className="flex gap-2">
                                <label>
                                    Masuk
                                    <Input
                                        type="time"
                                        value={form.data.check_in}
                                        onChange={(event) =>
                                            form.setData(
                                                'check_in',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <label>
                                    Pulang
                                    <Input
                                        type="time"
                                        value={form.data.check_out}
                                        onChange={(event) =>
                                            form.setData(
                                                'check_out',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>
                            </div>
                        )}
                        <Input
                            aria-label="Catatan absensi"
                            placeholder="Catatan (opsional)"
                            maxLength={1000}
                            value={form.data.note}
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                        />
                        {Object.entries(form.errors).map(([key, message]) => (
                            <p
                                role="alert"
                                className="text-destructive"
                                key={key}
                            >
                                {message}
                            </p>
                        ))}
                        <div className="flex gap-2">
                            <Button disabled={form.processing} size="sm">
                                Simpan
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setEditing(false)}
                            >
                                Batal
                            </Button>
                        </div>
                    </form>
                ) : (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setEditing(true)}
                    >
                        Catat / Koreksi
                    </Button>
                )}
            </TableCell>
        </TableRow>
    );
}

export default function CardAttendanceIndex({
    layouts,
    filters,
    participants,
}: Props) {
    const errors = usePage<{ errors: Record<string, string> }>().props.errors;
    const [q, setQ] = useState(filters.q);
    const [copied, setCopied] = useState(false);
    const selected = layouts.find((layout) => layout.id === filters.layout);
    usePoll(10000, { only: ['participants'] });

    return (
        <>
            <Head title="Absensi & Link Scan" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap justify-between gap-3">
                    <h1 className="text-2xl font-semibold">
                        Absensi & Link Scan
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={report.url()}>Laporan absensi</Link>
                    </Button>
                </div>
                <div className="flex flex-wrap gap-3">
                    <select
                        aria-label="Layout peserta"
                        className="rounded-md border bg-background p-2"
                        value={filters.layout ?? ''}
                        onChange={(event) => {
                            setCopied(false);
                            router.get(index.url(), {
                                ...filters,
                                layout: event.target.value,
                            });
                        }}
                    >
                        {layouts.map((layout) => (
                            <option key={layout.id} value={layout.id}>
                                {layout.name}
                            </option>
                        ))}
                    </select>
                    <Input
                        className="w-auto"
                        aria-label="Tanggal absensi"
                        type="date"
                        value={filters.date}
                        onChange={(event) =>
                            router.get(index.url(), {
                                ...filters,
                                date: event.target.value,
                            })
                        }
                    />
                </div>
                {selected ? (
                    <section className="space-y-3 rounded-lg border p-4">
                        <h2 className="font-semibold">
                            Link scan {selected.name}
                        </h2>
                        <Input
                            readOnly
                            aria-label="Link scan peserta"
                            value={selected.scan_url}
                            onFocus={(event) => event.target.select()}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button asChild disabled={!selected.is_active}>
                                <a
                                    href={selected.scan_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Buka scanner
                                </a>
                            </Button>
                            <Button
                                variant="outline"
                                onClick={async () => {
                                    try {
                                        await navigator.clipboard.writeText(
                                            selected.scan_url,
                                        );
                                        setCopied(true);
                                    } catch {
                                        window.prompt(
                                            'Salin link scan:',
                                            selected.scan_url,
                                        );
                                    }
                                }}
                            >
                                {copied ? 'Tersalin' : 'Salin link'}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Ganti link scan? Link lama tidak dapat dipakai lagi. QR peserta tetap berlaku.',
                                        )
                                    ) {
                                        router.post(rotate.url(selected.id));
                                    }
                                }}
                            >
                                Ganti link scan
                            </Button>
                            <Button
                                variant="outline"
                                disabled={!selected.qr_ready}
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Generate ulang kartu semua peserta layout ini? Kartu yang sudah tercetak perlu dicetak ulang.',
                                        )
                                    ) {
                                        router.post(
                                            regenerate.url(selected.id),
                                        );
                                    }
                                }}
                            >
                                Generate ulang semua kartu
                            </Button>
                            <Button asChild variant="outline">
                                <a href={downloadCards.url(selected.id)}>
                                    Unduh semua kartu (ZIP)
                                </a>
                            </Button>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Setelah generate ulang, tunggu sampai semua kartu
                            selesai dibuat sebelum mengunduh ZIP untuk dicetak.
                        </p>
                        <div className="rounded-md bg-muted/50 p-3 text-sm">
                            <p className="font-medium">
                                Link ringan (box TV / HP lama)
                            </p>
                            <p className="mt-1 text-muted-foreground">
                                Tampilan sama dengan gerbang sekolah, lebih
                                ringan. Ketik alamat ini di box TV:
                            </p>
                            <a
                                href={selected.light_url}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-1 block font-mono font-semibold break-all underline"
                            >
                                {selected.light_url}
                            </a>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Untuk scan pulang, tambahkan{' '}
                                <code>?mode=pulang</code> atau tekan PULANG di
                                halamannya.
                            </p>
                        </div>
                        {!selected.is_active && (
                            <p className="text-destructive">
                                Layout tidak aktif. Aktifkan layout untuk
                                membuka scanner.
                            </p>
                        )}
                        {!selected.qr_ready && (
                            <p className="text-amber-700 dark:text-amber-400">
                                Layout belum memakai QR absensi peserta.{' '}
                                <Link
                                    className="underline"
                                    href={edit.url(selected.id)}
                                >
                                    Atur QR di editor layout
                                </Link>
                                , pilih QR absensi peserta, simpan, lalu
                                generate ulang semua kartu.
                            </p>
                        )}
                        <p className="text-sm text-muted-foreground">
                            Link ini digunakan petugas untuk scan kamera atau
                            barcode reader. Pilih Masuk/Hadir untuk mencatat
                            hadir, lalu Pulang saat peserta pulang. Scan
                            mencatat tanggal hari ini, bukan tanggal filter di
                            bawah.
                        </p>
                    </section>
                ) : (
                    <p>
                        Buat layout dan peserta terlebih dahulu untuk
                        menggunakan absensi.
                    </p>
                )}
                <form
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(index.url(), { ...filters, q });
                    }}
                >
                    <Input
                        aria-label="Cari peserta"
                        placeholder="Cari nama atau data peserta"
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                    />
                    <Button variant="outline">Cari</Button>
                </form>
                <p className="text-sm text-muted-foreground">
                    Peserta yang belum discan tidak otomatis dianggap alpa.
                    Gunakan Catat / Koreksi untuk mengisi hadir, izin, sakit,
                    atau alpa.
                </p>
                {Object.entries(errors).map(([key, message]) => (
                    <p key={key} role="alert" className="text-destructive">
                        {message}
                    </p>
                ))}
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Peserta</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Masuk</TableHead>
                            <TableHead>Pulang</TableHead>
                            <TableHead>Catat / Koreksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {participants.data.map((participant) => (
                            <AttendanceRow
                                key={`${participant.id}-${filters.date}-${JSON.stringify(participant.attendance)}`}
                                participant={participant}
                                date={filters.date}
                            />
                        ))}
                        {!participants.data.length && (
                            <TableRow>
                                <TableCell colSpan={5}>
                                    Tidak ada peserta yang sesuai.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
                <div className="flex gap-3">
                    <Button
                        variant="outline"
                        disabled={!participants.prev_page_url}
                        onClick={() =>
                            participants.prev_page_url &&
                            router.visit(participants.prev_page_url)
                        }
                    >
                        Sebelumnya
                    </Button>
                    <Button
                        variant="outline"
                        disabled={!participants.next_page_url}
                        onClick={() =>
                            participants.next_page_url &&
                            router.visit(participants.next_page_url)
                        }
                    >
                        Berikutnya
                    </Button>
                </div>
            </div>
        </>
    );
}
CardAttendanceIndex.layout = (page: React.ReactNode) => (
    <KartuBebasLayout breadcrumbs={[{ title: 'Absensi', href: index.url() }]}>
        {page}
    </KartuBebasLayout>
);
