import {
    DonatStatus,
    RingkasanAngka,
    TabelCatatan,
} from '@/components/orangtua/panel-absensi';
import { KosongTanpaAnak, PortalPage } from '@/components/orangtua/portal-page';
import type { Anak, Filters, Panel } from '@/components/orangtua/portal-page';

export default function Sholat({
    student,
    panels,
    filters,
}: {
    student: Anak | null;
    panels: Record<string, Panel>;
    filters: Filters;
}) {
    const daftar = Object.entries(panels);

    return (
        <PortalPage
            title="Absen Sholat"
            description={
                student
                    ? `${student.name}${student.classroom ? ` · ${student.classroom}` : ''}`
                    : undefined
            }
            student={student}
            filters={student ? filters : null}
        >
            {!student ? (
                <KosongTanpaAnak />
            ) : daftar.length === 0 ? (
                <p className="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground">
                    Sekolah belum mengaktifkan absen sholat.
                </p>
            ) : (
                daftar.map(([kunci, panel]) => (
                    <section key={kunci} className="space-y-4">
                        <h2 className="text-lg font-semibold">{panel.label}</h2>
                        <RingkasanAngka summary={panel.summary} sholat />
                        <div className="grid gap-4 lg:grid-cols-2">
                            <DonatStatus panel={panel} sholat />
                            <div className="min-w-0">
                                <TabelCatatan recent={panel.recent} />
                            </div>
                        </div>
                    </section>
                ))
            )}
        </PortalPage>
    );
}
