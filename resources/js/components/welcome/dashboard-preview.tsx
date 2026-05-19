import { Check } from 'lucide-react';
import { useState } from 'react';

const checklist = [
    'Rekap harian, mingguan, dan bulanan otomatis',
    'Filter berdasarkan kelas, guru, atau tanggal',
    'Ekspor laporan ke Excel dan PDF',
    'Notifikasi keterlambatan secara real-time',
];

function StatCard({ color, label, value }: { color: string; label: string; value: string }) {
    return (
        <div className="bg-background flex-1 rounded-lg border border-border p-2.5">
            <div className={`mb-1.5 h-1 w-8 rounded-full ${color}`} />
            <p className="text-muted-foreground text-[10px]">{label}</p>
            <p className="text-foreground text-sm font-bold">{value}</p>
        </div>
    );
}

function MockChart() {
    return (
        <div className="rounded-lg border border-border bg-background p-3">
            <p className="text-muted-foreground mb-2 text-[10px] font-medium">Kehadiran Mingguan</p>
            <svg viewBox="0 0 280 60" className="w-full" aria-hidden="true">
                <path
                    d="M0 45 Q30 20, 60 30 T120 15 T180 25 T240 10 T280 20"
                    fill="none"
                    stroke="var(--primary)"
                    strokeWidth="2"
                    strokeLinecap="round"
                />
                <path
                    d="M0 45 Q30 20, 60 30 T120 15 T180 25 T240 10 T280 20 V60 H0 Z"
                    fill="var(--primary)"
                    opacity="0.08"
                />
            </svg>
        </div>
    );
}

function MockTable() {
    const rows = [
        { name: 'Budi Santoso', kelas: '7A', status: 'Hadir', time: '07:02' },
        { name: 'Siti Rahayu', kelas: '7B', status: 'Hadir', time: '07:05' },
        { name: 'Ahmad Fauzi', kelas: '8A', status: 'Terlambat', time: '07:22' },
    ];

    return (
        <div className="overflow-hidden rounded-lg border border-border">
            <table className="w-full text-[10px]">
                <thead>
                    <tr className="bg-muted text-muted-foreground">
                        <th className="px-2.5 py-1.5 text-left font-medium">Nama</th>
                        <th className="px-2.5 py-1.5 text-left font-medium">Kelas</th>
                        <th className="px-2.5 py-1.5 text-left font-medium">Status</th>
                        <th className="px-2.5 py-1.5 text-left font-medium">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.name} className="border-t border-border bg-card">
                            <td className="text-foreground px-2.5 py-1.5 font-medium">{row.name}</td>
                            <td className="text-muted-foreground px-2.5 py-1.5">{row.kelas}</td>
                            <td className="px-2.5 py-1.5">
                                <span
                                    className={`inline-block rounded-full px-1.5 py-0.5 text-[9px] font-medium ${
                                        row.status === 'Hadir'
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'
                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                                    }`}
                                >
                                    {row.status}
                                </span>
                            </td>
                            <td className="text-muted-foreground px-2.5 py-1.5">{row.time}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function DashboardPreview() {
    const [tilt, setTilt] = useState({ x: 0, y: 0 });

    function handleMouseMove(e: React.MouseEvent<HTMLDivElement>) {
        const rect = e.currentTarget.getBoundingClientRect();
        const x = ((e.clientY - rect.top) / rect.height - 0.5) * -6;
        const y = ((e.clientX - rect.left) / rect.width - 0.5) * 6;
        setTilt({ x, y });
    }

    function handleMouseLeave() {
        setTilt({ x: 0, y: 0 });
    }

    return (
        <section id="dashboard" className="relative overflow-hidden bg-gradient-to-b from-amber-50/60 to-orange-50/40 py-24 sm:py-32 lg:py-40 dark:from-amber-950/20 dark:to-orange-950/15">
            <div className="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
                <div className="animate-fade-up">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">
                        Pantau Kehadiran dari Satu Layar
                    </h2>
                    <p className="text-muted-foreground mt-4 text-lg leading-relaxed">
                        Dashboard intuitif yang menampilkan seluruh data kehadiran secara real-time. Tidak perlu
                        lagi membuka banyak spreadsheet atau buku absen manual.
                    </p>
                    <ul className="mt-8 space-y-4">
                        {checklist.map((item) => (
                            <li key={item} className="flex items-start gap-3">
                                <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950">
                                    <Check className="size-3 text-emerald-600 dark:text-emerald-400" />
                                </span>
                                <span className="text-foreground text-sm">{item}</span>
                            </li>
                        ))}
                    </ul>
                </div>

                <div
                    onMouseMove={handleMouseMove}
                    onMouseLeave={handleMouseLeave}
                    style={{ perspective: 800 }}
                    className="animate-fade-up animation-delay-200"
                >
                    <div
                        className="overflow-hidden rounded-xl border border-border shadow-2xl shadow-black/10 transition-transform duration-200 ease-out dark:shadow-black/30"
                        style={{
                            transform: `rotateX(${tilt.x}deg) rotateY(${tilt.y}deg)`,
                        }}
                    >
                        {/* Browser chrome */}
                        <div className="flex items-center gap-1.5 bg-muted px-3 py-2.5">
                            <span className="size-2.5 rounded-full bg-red-400" />
                            <span className="size-2.5 rounded-full bg-yellow-400" />
                            <span className="size-2.5 rounded-full bg-green-400" />
                            <span className="text-muted-foreground ml-3 text-[10px]">absensi.ozolab.id/dashboard</span>
                        </div>

                        {/* Content */}
                        <div className="space-y-3 bg-card p-4">
                            <div className="flex gap-2">
                                <StatCard color="bg-blue-500" label="Total Siswa" value="847" />
                                <StatCard color="bg-emerald-500" label="Hadir" value="812" />
                                <StatCard color="bg-amber-500" label="Terlambat" value="23" />
                                <StatCard color="bg-red-500" label="Absen" value="12" />
                            </div>
                            <MockChart />
                            <MockTable />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
