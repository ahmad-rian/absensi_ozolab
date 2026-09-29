import { Head, router, usePage } from '@inertiajs/react';
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
import { index, exportMethod } from '@/routes/kartu-bebas/laporan';

type Filters = {
    start_date: string;
    end_date: string;
    layout?: string;
    status?: string;
};
type Row = {
    date: string;
    name: string;
    layout: string;
    status: string;
    check_in: string | null;
    check_out: string | null;
    note: string | null;
};
export default function CardAttendanceReport({
    filters,
    layouts,
    counts,
    records,
}: {
    filters: Filters;
    layouts: { id: string; name: string }[];
    counts: Record<string, number>;
    records: {
        data: Row[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    const errors = usePage<{ errors: Record<string, string> }>().props.errors;
    const [values, setValues] = useState(filters);

    return (
        <>
            <Head title="Laporan Absensi Peserta" />
            <div className="space-y-6 p-4 md:p-6">
                <h1 className="text-2xl font-semibold">
                    Laporan absensi peserta
                </h1>
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(index.url(), values);
                    }}
                >
                    <label>
                        Dari
                        <Input
                            type="date"
                            required
                            value={values.start_date}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    start_date: event.target.value,
                                })
                            }
                        />
                    </label>
                    <label>
                        Sampai
                        <Input
                            type="date"
                            required
                            value={values.end_date}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    end_date: event.target.value,
                                })
                            }
                        />
                    </label>
                    <select
                        aria-label="Filter layout"
                        className="rounded border bg-background p-2"
                        value={values.layout ?? ''}
                        onChange={(event) =>
                            setValues({ ...values, layout: event.target.value })
                        }
                    >
                        <option value="">Semua layout</option>
                        {layouts.map((layout) => (
                            <option key={layout.id} value={layout.id}>
                                {layout.name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Filter status"
                        className="rounded border bg-background p-2"
                        value={values.status ?? ''}
                        onChange={(event) =>
                            setValues({ ...values, status: event.target.value })
                        }
                    >
                        <option value="">Semua status</option>
                        {['hadir', 'izin', 'sakit', 'alpa'].map((status) => (
                            <option key={status}>{status}</option>
                        ))}
                    </select>
                    <Button>Tampilkan</Button>
                </form>
                <div className="flex flex-wrap gap-4">
                    {['hadir', 'izin', 'sakit', 'alpa'].map((status) => (
                        <span
                            key={status}
                            className="rounded border p-3 capitalize"
                        >
                            {status}: {counts[status] ?? 0}
                        </span>
                    ))}
                </div>
                <div className="flex gap-3">
                    <Button asChild variant="outline">
                        <a href={exportMethod.url('xlsx', { query: filters })}>
                            Unduh Excel
                        </a>
                    </Button>
                    <Button asChild variant="outline">
                        <a href={exportMethod.url('pdf', { query: filters })}>
                            Unduh PDF
                        </a>
                    </Button>
                </div>
                <p className="text-sm text-muted-foreground">
                    {records.total} catatan. Ekspor mengikuti filter yang
                    ditampilkan dan dipisahkan per layout. Peserta yang belum
                    dicatat tidak dihitung sebagai alpa.
                </p>
                {Object.entries(errors).map(([key, message]) => (
                    <p key={key} role="alert" className="text-destructive">
                        {message}
                    </p>
                ))}
                <Table>
                    <TableHeader>
                        <TableRow>
                            {[
                                'Tanggal',
                                'Peserta',
                                'Layout',
                                'Status',
                                'Masuk',
                                'Pulang',
                                'Catatan',
                            ].map((title) => (
                                <TableHead key={title}>{title}</TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {records.data.map((row, i) => (
                            <TableRow key={i}>
                                {Object.values(row).map((value, j) => (
                                    <TableCell key={j}>
                                        {value ?? '—'}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                        {!records.data.length && (
                            <TableRow>
                                <TableCell colSpan={7}>
                                    Belum ada catatan absensi pada periode ini.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
                <div className="flex gap-3">
                    <Button
                        variant="outline"
                        disabled={!records.prev_page_url}
                        onClick={() =>
                            records.prev_page_url &&
                            router.visit(records.prev_page_url)
                        }
                    >
                        Sebelumnya
                    </Button>
                    <Button
                        variant="outline"
                        disabled={!records.next_page_url}
                        onClick={() =>
                            records.next_page_url &&
                            router.visit(records.next_page_url)
                        }
                    >
                        Berikutnya
                    </Button>
                </div>
            </div>
        </>
    );
}
CardAttendanceReport.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[{ title: 'Laporan absensi', href: index.url() }]}
    >
        {page}
    </KartuBebasLayout>
);
