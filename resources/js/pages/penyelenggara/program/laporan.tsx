import { Deferred, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { RingkasanKecamatan } from '@/components/shared/ringkasan-kecamatan';
import type { BarisKecamatan } from '@/components/shared/ringkasan-kecamatan';
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
    kecamatan: string[];
    periode: { dari: string; sampai: string };
    ringkasanKecamatan?: BarisKecamatan[];
};

type Mode = 'grup' | 'kecamatan' | 'sesi' | 'peserta';

const MODE: { nilai: Mode; label: string }[] = [
    { nilai: 'grup', label: 'Per grup' },
    { nilai: 'kecamatan', label: 'Per kecamatan' },
    { nilai: 'sesi', label: 'Per sesi' },
    { nilai: 'peserta', label: 'Per peserta' },
];

const KOLOM_RINGKASAN = [
    { key: 'hadir', label: 'Hadir' },
    { key: 'terlambat', label: 'Terlambat' },
    { key: 'izin', label: 'Izin' },
    { key: 'sakit', label: 'Sakit' },
    { key: 'alpa', label: 'Alpa' },
    { key: 'belum', label: 'Belum' },
];

export default function LaporanProgram({
    program,
    grups,
    sesis,
    peserta,
    kecamatan,
    periode,
    ringkasanKecamatan,
}: Props) {
    const akses = useAksesPenyelenggara();
    const [mode, setMode] = useState<Mode>('grup');
    const [dari, setDari] = useState(periode.dari);
    const [sampai, setSampai] = useState(periode.sampai);
    const [kec, setKec] = useState('');
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
                ...(kec && mode !== 'peserta' ? { kecamatan: kec } : {}),
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
                            {MODE.map((m) => (
                                <Button
                                    key={m.nilai}
                                    variant={
                                        mode === m.nilai ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    onClick={() => setMode(m.nilai)}
                                >
                                    {m.label}
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
                        {mode !== 'peserta' && kecamatan.length > 0 && (
                            <select
                                aria-label="Kecamatan"
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                value={kec}
                                onChange={(e) => setKec(e.target.value)}
                            >
                                <option value="">Semua kecamatan</option>
                                {kecamatan.map((k) => (
                                    <option key={k} value={k}>
                                        {k}
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
                            Per grup: satu sheet per grup, kolom per sesi. Per
                            kecamatan: ringkasan + satu sheet per kecamatan.
                            Sesi di hari libur tidak dihitung. Rentang maksimal
                            1 tahun.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Ringkasan per kecamatan
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {periode.dari} s/d {periode.sampai}. Klik kecamatan
                            untuk memilihnya di filter laporan.{' '}
                            <button
                                type="button"
                                className="underline"
                                onClick={() =>
                                    router.reload({
                                        data: { dari, sampai },
                                        only: ['periode', 'ringkasanKecamatan'],
                                    })
                                }
                            >
                                Pakai tanggal di atas
                            </button>
                        </p>
                    </CardHeader>
                    <CardContent>
                        <Deferred
                            data="ringkasanKecamatan"
                            fallback={
                                <div className="h-24 animate-pulse rounded-lg bg-muted" />
                            }
                        >
                            {ringkasanKecamatan &&
                            ringkasanKecamatan.length > 0 ? (
                                <RingkasanKecamatan
                                    baris={ringkasanKecamatan}
                                    kolom={KOLOM_RINGKASAN}
                                    aktif={kec}
                                    onPilih={(nama) =>
                                        setKec(kec === nama ? '' : nama)
                                    }
                                />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada peserta atau sesi pada periode
                                    ini.
                                </p>
                            )}
                        </Deferred>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LaporanProgram.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
