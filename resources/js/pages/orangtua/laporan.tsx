import { Download } from 'lucide-react';
import { KosongTanpaAnak, PortalPage } from '@/components/orangtua/portal-page';
import type { Anak, Filters } from '@/components/orangtua/portal-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { laporan } from '@/routes/orangtua';

export default function Laporan({
    student,
    kinds,
    filters,
}: {
    student: Anak | null;
    kinds: { slug: string; label: string }[];
    filters: Filters;
}) {
    return (
        <PortalPage
            title="Unduh Laporan"
            description="Rekap kehadiran anak dalam berkas PDF yang bisa dicetak atau dikirim."
            student={student}
        >
            {!student ? (
                <KosongTanpaAnak />
            ) : (
                /*
                    Form GET biasa, bukan router.get: balasannya berkas unduhan,
                    dan navigasi Inertia tidak bisa menerima itu. `anak` ikut
                    sebagai hidden agar konteks anaknya tidak hilang.
                */
                <form
                    action={laporan().url}
                    method="get"
                    className="max-w-xl space-y-5 rounded-xl border bg-card p-5 sm:p-6"
                >
                    <input type="hidden" name="download" value="1" />
                    <input type="hidden" name="anak" value={student.id} />

                    <div className="grid gap-1.5">
                        <Label htmlFor="jenis">Jenis laporan</Label>
                        <select
                            id="jenis"
                            name="jenis"
                            defaultValue="semuanya"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="semuanya">Semuanya</option>
                            {kinds.map((kind) => (
                                <option key={kind.slug} value={kind.slug}>
                                    {kind.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="start_date">Dari tanggal</Label>
                            <Input
                                id="start_date"
                                name="start_date"
                                type="date"
                                defaultValue={filters.start_date}
                                required
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="end_date">Sampai tanggal</Label>
                            <Input
                                id="end_date"
                                name="end_date"
                                type="date"
                                defaultValue={filters.end_date}
                                required
                            />
                        </div>
                    </div>

                    <Button type="submit" className="w-full sm:w-auto">
                        <Download className="size-4" />
                        Unduh PDF
                    </Button>
                </form>
            )}
        </PortalPage>
    );
}
