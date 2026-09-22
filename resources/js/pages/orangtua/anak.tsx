import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { anak, laporan, galeri } from '@/routes/orangtua';
export type Child = {
    id: string;
    name: string;
    nis: string;
    classroom: string | null;
    photo: string | null;
};
export type Panel = {
    label: string;
    summary: { rate: number; hadir: number; effective_days: number };
    recent: {
        id: string;
        date: string;
        status_label: string;
        time: string | null;
    }[];
};
export type Filters = { start_date: string; end_date: string };
export default function Anak({
    student,
    panels,
    filters,
}: {
    student: Child;
    panels: Record<string, Panel>;
    filters: Filters;
}) {
    return (
        <main className="space-y-6 p-6">
            <Head title={student.name} />
            <header>
                <h1 className="text-2xl font-semibold">{student.name}</h1>
                <p>
                    {student.classroom} · {student.nis}
                </p>
            </header>
            <div className="flex gap-3">
                <Button asChild>
                    <Link href={laporan(student.id)}>Unduh laporan</Link>
                </Button>
                <Button asChild variant="outline">
                    <Link href={galeri(student.id)}>Foto dan kartu</Link>
                </Button>
            </div>
            <Form
                action={anak(student.id).url}
                method="get"
                className="flex flex-wrap items-end gap-3"
            >
                <label>
                    Dari
                    <Input
                        type="date"
                        name="start_date"
                        defaultValue={filters.start_date}
                        required
                    />
                </label>
                <label>
                    Sampai
                    <Input
                        type="date"
                        name="end_date"
                        defaultValue={filters.end_date}
                        required
                    />
                </label>
                <Button>Tampilkan</Button>
            </Form>
            {Object.entries(panels).map(([key, panel]) => (
                <section key={key} className="space-y-4 rounded-xl border p-6">
                    <h2 className="text-lg font-semibold">{panel.label}</h2>
                    <p>
                        <strong className="text-3xl">
                            {panel.summary.rate}%
                        </strong>{' '}
                        kehadiran · {panel.summary.effective_days} hari efektif
                    </p>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr>
                                    <th className="p-2">Tanggal</th>
                                    <th>Status</th>
                                    <th>Jam</th>
                                </tr>
                            </thead>
                            <tbody>
                                {panel.recent.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="p-2">{row.date}</td>
                                        <td>{row.status_label}</td>
                                        <td>{row.time ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {panel.recent.length === 0 && (
                            <p className="py-4 text-muted-foreground">
                                Belum ada catatan pada periode ini.
                            </p>
                        )}
                    </div>
                </section>
            ))}
        </main>
    );
}
