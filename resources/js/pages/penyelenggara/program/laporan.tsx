import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAksesPenyelenggara } from '@/hooks/use-akses-penyelenggara';
import { dashboard } from '@/routes/penyelenggara';
import { exportMethod } from '@/routes/penyelenggara/laporan';
import {
    dashboard as programDashboard,
    show as programShow,
} from '@/routes/penyelenggara/program';

type Props = {
    program: { id: string; name: string; starts_on: string; ends_on: string };
    grups: { id: string; name: string }[];
    sesis: { id: string; name: string; tanggal: string }[];
    peserta: { id: string; nama: string }[];
};

export default function LaporanProgram({
    program,
    grups,
    sesis,
    peserta,
}: Props) {
    const akses = useAksesPenyelenggara();
    const [mode, setMode] = useState<'grup' | 'sesi' | 'peserta'>('grup');
    const [dari, setDari] = useState(program.starts_on);
    const [sampai, setSampai] = useState(program.ends_on);
    const [grup, setGrup] = useState('');
    const [sesi, setSesi] = useState(sesis[0]?.id ?? '');
    const [orang, setOrang] = useState(peserta[0]?.id ?? '');

    const url = (format: 'xlsx' | 'pdf') =>
        exportMethod.url(program.id, {
            query: {
                mode,
                format,
                dari,
                sampai,
                ...(grup ? { grup } : {}),
                ...(mode === 'sesi' ? { sesi } : {}),
                ...(mode === 'peserta' ? { peserta: orang } : {}),
            },
        });

    return (
        <>
            <Head title={`Laporan — ${program.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Laporan kehadiran
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        <Link
                            href={
                                akses.program
                                    ? programShow(program.id)
                                    : programDashboard(program.id)
                            }
                            className="underline"
                        >
                            {program.name}
                        </Link>
                    </p>
                </div>
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Pilih laporan
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="flex flex-wrap gap-2">
                            {(['grup', 'sesi', 'peserta'] as const).map((m) => (
                                <Button
                                    key={m}
                                    variant={mode === m ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setMode(m)}
                                >
                                    Per {m}
                                </Button>
                            ))}
                        </div>
                        {mode !== 'sesi' && (
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="dari">Dari</Label>
                                    <Input
                                        id="dari"
                                        type="date"
                                        value={dari}
                                        onChange={(e) =>
                                            setDari(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="sampai">Sampai</Label>
                                    <Input
                                        id="sampai"
                                        type="date"
                                        value={sampai}
                                        onChange={(e) =>
                                            setSampai(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                        )}
                        {mode === 'sesi' && (
                            <select
                                aria-label="Sesi"
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                value={sesi}
                                onChange={(e) => setSesi(e.target.value)}
                            >
                                {sesis.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.tanggal} · {s.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        {mode === 'peserta' && (
                            <select
                                aria-label="Peserta"
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                value={orang}
                                onChange={(e) => setOrang(e.target.value)}
                            >
                                {peserta.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.nama}
                                    </option>
                                ))}
                            </select>
                        )}
                        {mode !== 'peserta' && (
                            <select
                                aria-label="Grup"
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                value={grup}
                                onChange={(e) => setGrup(e.target.value)}
                            >
                                <option value="">Semua grup</option>
                                {grups.map((g) => (
                                    <option key={g.id} value={g.id}>
                                        {g.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <div className="flex gap-2">
                            <Button asChild>
                                <a href={url('xlsx')}>Unduh Excel</a>
                            </Button>
                            <Button asChild variant="outline">
                                <a href={url('pdf')}>Unduh PDF</a>
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Mode per grup: satu sheet per grup, kolom per sesi.
                            Rentang maksimal 1 tahun.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LaporanProgram.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
