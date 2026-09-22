import {
    BatangPerHari,
    DonatStatus,
    RingkasanAngka,
    TabelCatatan,
} from '@/components/orangtua/panel-absensi';
import { KosongTanpaAnak, PortalPage } from '@/components/orangtua/portal-page';
import type { Anak, Filters, Panel } from '@/components/orangtua/portal-page';

export default function Absensi({
    student,
    panel,
    filters,
}: {
    student: Anak | null;
    panel: Panel | null;
    filters: Filters;
}) {
    return (
        <PortalPage
            title="Absensi Sekolah"
            description={
                student
                    ? `${student.name}${student.classroom ? ` · ${student.classroom}` : ''}`
                    : undefined
            }
            student={student}
            filters={student ? filters : null}
        >
            {!student || !panel ? (
                <KosongTanpaAnak />
            ) : (
                <>
                    <RingkasanAngka summary={panel.summary} />
                    <div className="grid gap-4 lg:grid-cols-2">
                        <DonatStatus panel={panel} />
                        {panel.by_weekday?.series && (
                            <BatangPerHari series={panel.by_weekday.series} />
                        )}
                    </div>
                    <section className="space-y-3">
                        <h2 className="text-base font-semibold">
                            Rincian Catatan
                        </h2>
                        <TabelCatatan recent={panel.recent} />
                    </section>
                </>
            )}
        </PortalPage>
    );
}
