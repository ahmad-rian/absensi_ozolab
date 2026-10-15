import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { statusBadgeClass } from '@/components/student/history-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    Halaman,
    RiwayatSekolahRow,
    RiwayatSholatRow,
} from '@/types/student-stats';

export type RingkasanItem = {
    label: string;
    value: number | string | null | undefined;
};

/**
 * Tanggal disimpan sebagai tanggal sekolah (Y-m-d). Dirender di zona UTC
 * supaya browser di zona lain tidak menggesernya ke hari sebelumnya.
 */
function formatTanggal(tanggal: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${tanggal}T00:00:00Z`));
}

function Ringkasan({ items }: { items: RingkasanItem[] }) {
    return (
        <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {items.map((item) => (
                <div key={item.label} className="rounded-lg border p-3">
                    <dt className="text-xs text-muted-foreground">
                        {item.label}
                    </dt>
                    <dd className="mt-1 text-lg font-semibold tabular-nums">
                        {item.value ?? '—'}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function Navigasi({
    halaman,
    satuan,
    onPage,
}: {
    halaman: Halaman<unknown>;
    satuan: string;
    onPage: (page: number) => void;
}) {
    if (halaman.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-2 pt-4 sm:flex-row">
            <p className="text-xs text-muted-foreground">
                {halaman.from}–{halaman.to} dari {halaman.total} {satuan}
            </p>
            {halaman.last_page > 1 && (
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={halaman.current_page <= 1}
                        onClick={() => onPage(halaman.current_page - 1)}
                    >
                        <ChevronLeft className="size-4" /> Sebelumnya
                    </Button>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {halaman.current_page} / {halaman.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={halaman.current_page >= halaman.last_page}
                        onClick={() => onPage(halaman.current_page + 1)}
                    >
                        Berikutnya <ChevronRight className="size-4" />
                    </Button>
                </div>
            )}
        </div>
    );
}

function Kerangka({
    title,
    description,
    ringkasan,
    children,
}: {
    title: string;
    description: string;
    ringkasan: RingkasanItem[];
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <Ringkasan items={ringkasan} />
                {children}
            </CardContent>
        </Card>
    );
}

function BarisStatus({
    colSpan,
    children,
}: {
    colSpan: number;
    children: ReactNode;
}) {
    return (
        <TableRow>
            <TableCell
                colSpan={colSpan}
                className="py-8 text-center text-muted-foreground"
            >
                {children}
            </TableCell>
        </TableRow>
    );
}

function StatusBadge({
    status,
    label,
}: {
    status: string | null;
    label: string;
}) {
    return (
        <Badge
            variant="outline"
            className={status ? (statusBadgeClass[status] ?? '') : ''}
        >
            {label}
        </Badge>
    );
}

/**
 * Riwayat absensi sekolah per hari: jam masuk dan pulang dalam satu baris,
 * berhalaman di server, dengan ringkasan periode di atasnya.
 */
export function RiwayatSekolahCard({
    halaman,
    range,
    ringkasan,
    onPage,
}: {
    halaman?: Halaman<RiwayatSekolahRow>;
    range: string;
    ringkasan: RingkasanItem[];
    onPage: (page: number) => void;
}) {
    const kolom = 6;

    return (
        <Kerangka
            title="Riwayat Absensi"
            description={`Semua catatan per hari, ${range}`}
            ringkasan={ringkasan}
        >
            <div className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Tanggal</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Jam Masuk</TableHead>
                            <TableHead>Jam Pulang</TableHead>
                            <TableHead>Keterangan</TableHead>
                            <TableHead>Sumber</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {!halaman ? (
                            <TableRow>
                                <TableCell colSpan={kolom}>
                                    <Skeleton className="h-24 w-full" />
                                </TableCell>
                            </TableRow>
                        ) : halaman.data.length === 0 ? (
                            <BarisStatus colSpan={kolom}>
                                Belum ada catatan absensi pada rentang ini.
                            </BarisStatus>
                        ) : (
                            halaman.data.map((row) => (
                                <TableRow key={row.tanggal}>
                                    <TableCell className="whitespace-nowrap">
                                        <span className="font-medium">
                                            {formatTanggal(row.tanggal)}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {row.hari}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={row.status}
                                            label={row.status_label}
                                        />
                                    </TableCell>
                                    <TableCell className="font-mono tabular-nums">
                                        {row.jam_masuk ?? '-'}
                                    </TableCell>
                                    <TableCell className="font-mono tabular-nums">
                                        {row.jam_pulang ?? '-'}
                                        {row.pulang_otomatis && (
                                            <span className="block font-sans text-xs text-muted-foreground">
                                                otomatis
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="max-w-64 text-sm whitespace-normal">
                                        {row.keterangan ?? '-'}
                                    </TableCell>
                                    <TableCell className="text-xs text-muted-foreground">
                                        {row.sumber}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
            {halaman && (
                <Navigasi halaman={halaman} satuan="hari" onPage={onPage} />
            )}
        </Kerangka>
    );
}

/** Riwayat satu jenis sholat, berhalaman di server. */
export function RiwayatSholatCard({
    title,
    halaman,
    range,
    ringkasan,
    onPage,
}: {
    title: string;
    halaman?: Halaman<RiwayatSholatRow>;
    range: string;
    ringkasan: RingkasanItem[];
    onPage: (page: number) => void;
}) {
    const kolom = 5;

    return (
        <Kerangka
            title={title}
            description={`Semua catatan, ${range}`}
            ringkasan={ringkasan}
        >
            <div className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Tanggal</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Jam</TableHead>
                            <TableHead>Keterangan</TableHead>
                            <TableHead>Sumber</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {!halaman ? (
                            <TableRow>
                                <TableCell colSpan={kolom}>
                                    <Skeleton className="h-24 w-full" />
                                </TableCell>
                            </TableRow>
                        ) : halaman.data.length === 0 ? (
                            <BarisStatus colSpan={kolom}>
                                Belum ada catatan absen sholat pada rentang ini.
                            </BarisStatus>
                        ) : (
                            halaman.data.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="whitespace-nowrap">
                                        <span className="font-medium">
                                            {formatTanggal(row.tanggal)}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {row.hari}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={row.status}
                                            label={row.status_label}
                                        />
                                    </TableCell>
                                    <TableCell className="font-mono tabular-nums">
                                        {row.jam ?? '-'}
                                    </TableCell>
                                    <TableCell className="max-w-64 text-sm whitespace-normal">
                                        {row.keterangan ?? '-'}
                                    </TableCell>
                                    <TableCell className="text-xs text-muted-foreground">
                                        {row.sumber}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
            {halaman && (
                <Navigasi halaman={halaman} satuan="catatan" onPage={onPage} />
            )}
        </Kerangka>
    );
}
