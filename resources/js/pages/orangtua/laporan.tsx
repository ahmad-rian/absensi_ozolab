import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { laporan } from '@/routes/orangtua';
import type { Child, Filters } from './anak';
export default function Laporan({
    student,
    kinds,
    filters,
}: {
    student: Child;
    kinds: string[];
    filters: Filters;
}) {
    const labels: Record<string, string> = {
        absensi: 'Absensi Sekolah',
        dhuha: 'Sholat Dhuha',
        dzuhur: 'Sholat Dzuhur',
    };

    return (
        <main className="space-y-6 p-6">
            <Head title="Laporan anak" />
            <h1 className="text-2xl font-semibold">Laporan {student.name}</h1>
            <form
                action={laporan(student.id).url}
                className="max-w-lg space-y-5 rounded-xl border p-6"
            >
                <input type="hidden" name="download" value="1" />
                <label className="block">
                    Jenis laporan
                    <select
                        name="jenis"
                        className="mt-2 block w-full rounded-md border bg-background p-2"
                    >
                        <option value="semuanya">Semuanya</option>
                        {kinds.map((kind) => (
                            <option key={kind} value={kind}>
                                {labels[kind]}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="block">
                    Dari
                    <Input
                        name="start_date"
                        type="date"
                        defaultValue={filters.start_date}
                        required
                    />
                </label>
                <label className="block">
                    Sampai
                    <Input
                        name="end_date"
                        type="date"
                        defaultValue={filters.end_date}
                        required
                    />
                </label>
                <Button>Unduh PDF</Button>
            </form>
        </main>
    );
}
