// Hero product mockup — a centered browser-window card containing a dashboard
// mockup, with a floating digital student card overlapping a corner. Motion is
// driven by GSAP in the parent hero section (entrance pop + float loop + scroll
// parallax). Elements are tagged with `data-hero-mockup` / `data-hero-card`
// so the GSAP context can target them. Everything is theme-var driven and
// dark-mode safe.

import { CheckCircle2, LayoutDashboard, QrCode, Search, TrendingUp, Users } from 'lucide-react';

function QrPattern() {
    const cells = [
        [1, 1, 1, 0, 1, 0, 1, 1, 1],
        [1, 0, 1, 0, 0, 1, 1, 0, 1],
        [1, 1, 1, 0, 1, 0, 1, 1, 1],
        [0, 0, 0, 1, 0, 1, 0, 0, 0],
        [1, 0, 1, 1, 0, 0, 1, 0, 1],
        [0, 1, 0, 0, 1, 1, 0, 1, 0],
        [1, 1, 1, 0, 1, 0, 1, 1, 1],
        [1, 0, 1, 1, 0, 0, 1, 0, 1],
        [1, 1, 1, 0, 0, 1, 1, 1, 1],
    ];

    return (
        <svg viewBox="0 0 54 54" className="size-16" aria-hidden="true">
            {cells.map((row, rowIdx) =>
                row.map((cell, colIdx) =>
                    cell ? (
                        <rect key={`${rowIdx}-${colIdx}`} x={colIdx * 6} y={rowIdx * 6} width="5" height="5" rx="0.5" className="fill-foreground" />
                    ) : null,
                ),
            )}
        </svg>
    );
}

function AttendanceChart() {
    const bars = [62, 74, 68, 82, 78, 95, 88, 84, 91, 76, 86, 93];
    return (
        <div className="flex h-28 items-end gap-1.5 sm:gap-2" aria-hidden="true">
            {bars.map((h, i) => (
                <div
                    key={i}
                    className="flex-1 rounded-t-md transition-all"
                    style={{
                        height: `${h}%`,
                        background:
                            i === 5
                                ? 'var(--chart-1)'
                                : 'color-mix(in oklch, var(--chart-1) 38%, transparent)',
                    }}
                />
            ))}
        </div>
    );
}

const STUDENTS = [
    { name: 'Andi Pratama', meta: 'IX-A · 06:45', status: 'Hadir' },
    { name: 'Siti Nurhaliza', meta: 'IX-A · 06:48', status: 'Hadir' },
    { name: 'Budi Santoso', meta: 'IX-B · 07:02', status: 'Terlambat' },
];

export function HeroVisual() {
    return (
        <div data-hero-visual className="relative mx-auto w-full max-w-5xl" aria-hidden="true">
            {/* Glow pad behind the mockup for depth */}
            <div
                className="pointer-events-none absolute top-1/2 left-1/2 h-[70%] w-[80%] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-40 blur-[110px] dark:opacity-50"
                style={{ background: 'radial-gradient(circle, var(--chart-1) 0%, transparent 70%)' }}
            />

            {/* ---------- Browser-window product mockup ---------- */}
            <div
                data-hero-mockup
                className="border-border/60 bg-card/80 relative overflow-hidden rounded-[1.6rem] border shadow-2xl shadow-black/10 ring-1 ring-white/10 backdrop-blur-xl dark:shadow-black/50"
            >
                {/* Top bar: 3 dots + fake url pill */}
                <div className="border-border/60 bg-muted/40 flex items-center gap-3 border-b px-4 py-3">
                    <div className="flex items-center gap-1.5">
                        <span className="size-3 rounded-full bg-red-400/80" />
                        <span className="size-3 rounded-full bg-amber-400/80" />
                        <span className="size-3 rounded-full bg-green-400/80" />
                    </div>
                    <div className="border-border/50 bg-background/60 text-muted-foreground mx-auto flex w-full max-w-xs items-center justify-center gap-1.5 rounded-full border px-3 py-1 text-[11px]">
                        <span className="text-chart-2">🔒</span>
                        app.absensiozolab.id/dashboard
                    </div>
                </div>

                {/* Body: dashboard layout */}
                <div className="flex">
                    {/* Sidebar (hidden on small) */}
                    <aside className="border-border/60 bg-muted/20 hidden w-44 shrink-0 border-r p-4 md:block">
                        <div className="mb-5 flex items-center gap-2">
                            <div className="from-primary to-chart-2 flex size-7 items-center justify-center rounded-lg bg-gradient-to-br">
                                <LayoutDashboard className="size-4 text-white" />
                            </div>
                            <span className="text-foreground text-sm font-semibold">Ozolab</span>
                        </div>
                        <nav className="space-y-1.5">
                            {[
                                { icon: LayoutDashboard, label: 'Dashboard', active: true },
                                { icon: Users, label: 'Siswa', active: false },
                                { icon: TrendingUp, label: 'Rekap', active: false },
                                { icon: QrCode, label: 'Scan', active: false },
                            ].map(({ icon: Icon, label, active }) => (
                                <div
                                    key={label}
                                    className={`flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs font-medium ${
                                        active
                                            ? 'bg-primary/10 text-primary'
                                            : 'text-muted-foreground'
                                    }`}
                                >
                                    <Icon className="size-3.5" />
                                    {label}
                                </div>
                            ))}
                        </nav>
                    </aside>

                    {/* Main panel */}
                    <div className="min-w-0 flex-1 p-4 sm:p-5">
                        {/* Header row */}
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <div className="text-foreground text-sm font-semibold sm:text-base">Dashboard Kehadiran</div>
                                <div className="text-muted-foreground text-[11px]">Senin, 15 Juli · Semester Ganjil</div>
                            </div>
                            <div className="border-border/50 bg-background/60 text-muted-foreground hidden items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] sm:flex">
                                <Search className="size-3.5" />
                                Cari siswa…
                            </div>
                        </div>

                        {/* Stat cards */}
                        <div className="mb-4 grid grid-cols-3 gap-2.5 sm:gap-3">
                            <div className="border-border/60 bg-background/50 rounded-2xl border p-3">
                                <div className="text-muted-foreground text-[10px] font-medium sm:text-[11px]">Kehadiran</div>
                                <div className="mt-1 flex items-end gap-1.5">
                                    <span className="text-foreground text-lg font-bold tracking-tight sm:text-2xl">98.5%</span>
                                    <span className="text-chart-2 mb-0.5 text-[10px] font-semibold">+2.1%</span>
                                </div>
                            </div>
                            <div className="border-border/60 bg-background/50 rounded-2xl border p-3">
                                <div className="text-muted-foreground text-[10px] font-medium sm:text-[11px]">Hadir</div>
                                <div className="mt-1 flex items-end gap-1.5">
                                    <span className="text-foreground text-lg font-bold tracking-tight sm:text-2xl">1.284</span>
                                    <span className="text-muted-foreground mb-0.5 text-[10px]">siswa</span>
                                </div>
                            </div>
                            <div className="border-border/60 bg-background/50 rounded-2xl border p-3">
                                <div className="text-muted-foreground text-[10px] font-medium sm:text-[11px]">Notif WA</div>
                                <div className="mt-1 flex items-end gap-1.5">
                                    <span className="text-foreground text-lg font-bold tracking-tight sm:text-2xl">1.284</span>
                                    <span className="text-chart-2 mb-0.5 text-[10px] font-semibold">terkirim</span>
                                </div>
                            </div>
                        </div>

                        {/* Chart + student list */}
                        <div className="grid gap-3 lg:grid-cols-[1.4fr_1fr]">
                            <div className="border-border/60 bg-background/50 rounded-2xl border p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="text-foreground text-xs font-semibold">Kehadiran Mingguan</span>
                                    <TrendingUp className="text-chart-2 size-4" />
                                </div>
                                <AttendanceChart />
                            </div>

                            <div className="border-border/60 bg-background/50 rounded-2xl border p-4">
                                <div className="text-foreground mb-3 text-xs font-semibold">Absensi Terbaru</div>
                                <div className="space-y-2.5">
                                    {STUDENTS.map((s) => (
                                        <div key={s.name} className="flex items-center gap-2.5">
                                            <div className="from-primary/20 to-chart-2/20 ring-border/50 flex size-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br ring-1">
                                                <div className="from-primary to-chart-2 size-4 rounded-full bg-gradient-to-br opacity-80" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="text-foreground truncate text-[11px] font-semibold">{s.name}</div>
                                                <div className="text-muted-foreground text-[10px]">{s.meta}</div>
                                            </div>
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-[9px] font-semibold ${
                                                    s.status === 'Hadir'
                                                        ? 'bg-chart-2/15 text-chart-2'
                                                        : 'bg-chart-3/20 text-chart-3'
                                                }`}
                                            >
                                                {s.status}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* ---------- Floating digital student card (overlaps corner) ---------- */}
            <div
                data-hero-card
                data-hero-float="1"
                data-parallax="-70"
                className="border-border/60 bg-card/90 absolute -bottom-8 -left-4 w-52 rounded-[1.4rem] border p-4 shadow-2xl shadow-black/15 ring-1 ring-white/10 backdrop-blur-xl sm:-left-8 sm:w-60 dark:shadow-black/50"
            >
                <div className="mb-3 flex items-center justify-between">
                    <span className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">Kartu Siswa Digital</span>
                    <QrCode className="text-primary size-4" />
                </div>
                <div className="flex items-start gap-3">
                    <div className="from-primary/20 to-chart-2/20 ring-border/50 flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ring-1">
                        <div className="from-primary to-chart-2 size-7 rounded-lg bg-gradient-to-br opacity-80" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="text-foreground text-sm font-semibold">Andi Pratama</div>
                        <div className="text-muted-foreground mt-0.5 text-xs">Kelas IX-A</div>
                        <div className="text-muted-foreground text-xs">NIS: 2024.0847</div>
                    </div>
                </div>
                <div className="border-border/60 bg-background/40 mt-3 flex items-center justify-center rounded-xl border p-2.5">
                    <QrPattern />
                </div>
            </div>

            {/* ---------- Floating WhatsApp notification (overlaps top-right) ---------- */}
            <div
                data-hero-card
                data-hero-float="2"
                data-parallax="-140"
                className="border-border/60 bg-card/90 absolute -top-6 -right-3 hidden w-56 rounded-[1.4rem] border p-4 shadow-2xl shadow-black/15 ring-1 ring-white/10 backdrop-blur-xl sm:-right-8 sm:block dark:shadow-black/50"
            >
                <div className="mb-2 flex items-center gap-2">
                    <div className="flex size-6 items-center justify-center rounded-full bg-green-500 shadow-sm shadow-green-500/40">
                        <svg viewBox="0 0 24 24" className="size-3.5 fill-white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" /></svg>
                    </div>
                    <span className="text-foreground text-xs font-semibold">Notifikasi Absensi</span>
                </div>
                <div className="bg-muted/70 rounded-xl p-3">
                    <p className="text-foreground text-xs leading-relaxed">Andi Pratama (IX-A) telah hadir di sekolah pada pukul 06:45 WIB.</p>
                </div>
                <div className="text-muted-foreground mt-2 text-right text-[10px]">06:45 · Terkirim</div>
            </div>

            {/* Small floating pill — extra depth */}
            <div
                data-hero-card
                data-hero-float="3"
                data-parallax="-180"
                className="border-border/60 bg-card/90 absolute -bottom-4 right-6 hidden items-center gap-2 rounded-full border px-3.5 py-2 shadow-xl shadow-black/10 backdrop-blur-xl sm:flex dark:shadow-black/50"
            >
                <CheckCircle2 className="text-chart-2 size-4" />
                <span className="text-foreground text-xs font-semibold">Absen tercatat</span>
            </div>
        </div>
    );
}
