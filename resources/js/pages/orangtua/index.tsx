import { Head, Link } from '@inertiajs/react';
import { anak } from '@/routes/orangtua';
import type { Child, Panel } from './anak';
export default function Beranda({
    children,
}: {
    children: (Child & {
        panels: Record<string, Panel>;
        today: { status_label: string }[];
    })[];
}) {
    return (
        <main className="space-y-6 p-6">
            <Head title="Anak saya" />
            <header>
                <h1 className="text-2xl font-semibold">Anak saya</h1>
                <p className="text-muted-foreground">
                    Kehadiran hari ini dan ringkasan 30 hari terakhir.
                </p>
            </header>
            {children.length === 0 && (
                <p className="rounded-xl border p-8">
                    Belum ada anak tertaut. Hubungi sekolah untuk melengkapi
                    data.
                </p>
            )}
            <div className="grid gap-6 lg:grid-cols-2">
                {children.map((child) => (
                    <Link
                        href={anak(child.id)}
                        key={child.id}
                        className="space-y-5 rounded-xl border bg-card p-6"
                    >
                        <div className="flex gap-4">
                            {child.photo && (
                                <img
                                    src={child.photo}
                                    alt={child.name}
                                    className="h-24 w-18 rounded-lg object-cover"
                                />
                            )}
                            <div>
                                <h2 className="text-xl font-semibold">
                                    {child.name}
                                </h2>
                                <p>{child.classroom ?? 'Kelas belum diisi'}</p>
                                <p className="mt-2 text-sm">
                                    Hari ini:{' '}
                                    {child.today[0]?.status_label ??
                                        'Belum ada catatan'}
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                            {Object.entries(child.panels).map(
                                ([key, panel]) => (
                                    <div key={key}>
                                        <p className="text-2xl font-semibold">
                                            {panel.summary.rate}%
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {panel.label}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    </Link>
                ))}
            </div>
        </main>
    );
}
