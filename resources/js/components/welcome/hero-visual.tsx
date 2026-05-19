// Hero visual — pure CSS animations, no framer-motion for performance.

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
        <svg viewBox="0 0 54 54" className="size-20" aria-hidden="true">
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

function MiniBarChart() {
    const bars = [65, 80, 72, 90, 85, 95, 88];
    return (
        <div className="flex items-end gap-1" aria-hidden="true">
            {bars.map((h, i) => (
                <div key={i} className="w-2.5 rounded-sm" style={{ height: `${h * 0.28}px`, background: 'var(--chart-1)', opacity: i === 5 ? 1 : 0.5 }} />
            ))}
        </div>
    );
}

export function HeroVisual() {
    return (
        <div className="relative hidden h-[520px] w-full max-w-lg lg:block" aria-hidden="true">
            {/* Student Card */}
            <div className="animate-float absolute top-0 left-8 bg-card border-border w-64 rounded-[1.3rem] border p-5">
                <div className="text-muted-foreground mb-3 text-xs font-medium uppercase tracking-wider">Kartu Siswa Digital</div>
                <div className="flex items-start gap-4">
                    <div className="bg-muted flex size-12 shrink-0 items-center justify-center rounded-xl">
                        <div className="bg-muted-foreground/30 size-8 rounded-lg" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="text-foreground text-sm font-semibold">Andi Pratama</div>
                        <div className="text-muted-foreground mt-0.5 text-xs">Kelas IX-A</div>
                        <div className="text-muted-foreground text-xs">NIS: 2024.0847</div>
                    </div>
                </div>
                <div className="border-border mt-4 flex items-center justify-center rounded-xl border p-3">
                    <QrPattern />
                </div>
            </div>

            {/* WhatsApp Bubble */}
            <div className="animate-float-delayed absolute top-12 right-0 bg-card border-border w-56 rounded-[1.3rem] border p-4">
                <div className="mb-2 flex items-center gap-2">
                    <div className="flex size-6 items-center justify-center rounded-full bg-green-500">
                        <svg viewBox="0 0 24 24" className="size-3.5 fill-white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" /></svg>
                    </div>
                    <span className="text-foreground text-xs font-semibold">Notifikasi Absensi</span>
                </div>
                <div className="bg-muted rounded-xl p-3">
                    <p className="text-foreground text-xs leading-relaxed">Andi Pratama (IX-A) telah hadir di sekolah pada pukul 06:45 WIB.</p>
                </div>
                <div className="text-muted-foreground mt-2 text-right text-[10px]">06:45</div>
            </div>

            {/* Stat Card */}
            <div className="animate-float-slow absolute bottom-8 left-16 bg-card border-border w-48 rounded-[1.3rem] border p-4">
                <div className="text-muted-foreground mb-1 text-xs font-medium">Tingkat Kehadiran</div>
                <div className="text-foreground text-2xl font-bold tracking-tight">98.5%</div>
                <div className="mt-3"><MiniBarChart /></div>
            </div>
        </div>
    );
}
