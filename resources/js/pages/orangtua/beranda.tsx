import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import {
    BatangPerHari,
    DonatStatus,
    KartuAngka,
    LencanaStatus,
    WARNA,
} from '@/components/orangtua/panel-absensi';
import { KosongTanpaAnak, PortalPage } from '@/components/orangtua/portal-page';
import type {
    Anak,
    Catatan,
    Filters,
    Panel,
} from '@/components/orangtua/portal-page';
import { Button } from '@/components/ui/button';
import { absensi, sholat } from '@/routes/orangtua';

export default function Beranda({
    student,
    panels,
    today,
    filters,
}: {
    student: Anak | null;
    panels: Record<string, Panel>;
    today: Catatan | null;
    filters: Filters | null;
}) {
    if (!student) {
        return (
            <PortalPage title="Beranda" student={null}>
                <KosongTanpaAnak />
            </PortalPage>
        );
    }

    const absen = panels.absensi;
    const sholatPanels = Object.entries(panels).filter(
        ([kunci]) => kunci !== 'absensi',
    );

    return (
        <PortalPage
            title={student.name}
            description={[student.classroom, student.nis, student.school]
                .filter(Boolean)
                .join(' · ')}
            student={student}
            filters={filters}
            action={
                student.photo ? (
                    <img
                        src={student.photo}
                        alt={student.name}
                        className="size-16 shrink-0 rounded-xl border object-cover sm:size-20"
                    />
                ) : undefined
            }
        >
            {/* Hari ini lebih dulu: itu satu-satunya pertanyaan yang dibawa
                orang tua saat membuka portal di pagi hari. */}
            <div className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4">
                <span className="text-sm font-medium">Hari ini</span>
                {today ? (
                    <>
                        <LencanaStatus label={today.status_label} />
                        {today.time && (
                            <span className="text-sm text-muted-foreground tabular-nums">
                                pukul {today.time}
                            </span>
                        )}
                    </>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        Belum ada catatan.
                    </span>
                )}
            </div>

            {absen && (
                <>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <KartuAngka
                            label="Kehadiran"
                            nilai={`${absen.summary.rate}%`}
                        />
                        <KartuAngka
                            label="Hadir"
                            nilai={absen.summary.hadir}
                            satuan="hari"
                            warna={WARNA.hadir}
                        />
                        <KartuAngka
                            label="Terlambat"
                            nilai={absen.summary.terlambat ?? 0}
                            satuan="hari"
                            warna={WARNA.terlambat}
                        />
                        <KartuAngka
                            label="Alpa"
                            nilai={absen.summary.alpa ?? 0}
                            satuan="hari"
                            warna={WARNA.alpa}
                        />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <DonatStatus panel={absen} />
                        {absen.by_weekday?.series && (
                            <BatangPerHari series={absen.by_weekday.series} />
                        )}
                    </div>

                    <div className="flex justify-end">
                        <Button asChild variant="outline">
                            <Link href={`${absensi().url}?anak=${student.id}`}>
                                Rincian absensi
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </>
            )}

            {sholatPanels.length > 0 && (
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">Absen Sholat</h2>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {sholatPanels.map(([kunci, panel]) => (
                            <div
                                key={kunci}
                                className="rounded-xl border bg-card p-4"
                            >
                                <p className="text-sm font-medium">
                                    {panel.label}
                                </p>
                                <p className="mt-1 text-2xl font-bold tabular-nums">
                                    {panel.summary.rate}%
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {panel.summary.hadir} ikut dari{' '}
                                    {panel.summary.effective_days} hari efektif
                                </p>
                            </div>
                        ))}
                    </div>
                    <div className="flex justify-end">
                        <Button asChild variant="outline">
                            <Link href={`${sholat().url}?anak=${student.id}`}>
                                Rincian sholat
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </section>
            )}
        </PortalPage>
    );
}
