import { Timer } from 'lucide-react';
import { useState } from 'react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

export type SiswaCepat = {
    peringkat: number;
    id: string;
    nama: string;
    kelas: string | null;
    rataRata: string;
    hari: number;
    inisial: string;
};

export type KategoriCepat = {
    kunci: string;
    label: string;
    periode: Record<string, SiswaCepat[]>;
};

const PERIODE = [
    { nilai: '1', label: '1 bulan' },
    { nilai: '2', label: '2 bulan' },
    { nilai: '3', label: '3 bulan' },
];

/** Warna medali tiga besar; selebihnya nomor biasa. */
const MEDALI: Record<number, string> = {
    1: 'bg-amber-400 text-amber-950',
    2: 'bg-zinc-300 text-zinc-800 dark:bg-zinc-500 dark:text-zinc-50',
    3: 'bg-orange-300 text-orange-950',
};

function Daftar({ data }: { data: SiswaCepat[] }) {
    if (data.length === 0) {
        return (
            <p className="py-10 text-center text-sm text-muted-foreground">
                Belum cukup data. Siswa masuk peringkat setelah hadir minimal
                separuh hari aktif pada periode ini.
            </p>
        );
    }

    return (
        <ol className="divide-y">
            {data.map((s) => (
                <li key={s.id} className="flex items-center gap-3 py-2.5">
                    <span
                        className={`grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold ${MEDALI[s.peringkat] ?? 'bg-muted text-muted-foreground'}`}
                    >
                        {s.peringkat}
                    </span>
                    <Avatar className="size-8">
                        <AvatarFallback className="bg-primary/10 text-xs text-primary">
                            {s.inisial}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">{s.nama}</p>
                        <p className="text-xs text-muted-foreground">
                            {s.kelas ?? 'Tanpa kelas'} · {s.hari} hari hadir
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="font-mono text-sm font-semibold tabular-nums">
                            {s.rataRata}
                        </p>
                        <p className="text-[0.7rem] text-muted-foreground">
                            rata-rata
                        </p>
                    </div>
                </li>
            ))}
        </ol>
    );
}

export function FastestStudentsCard({ data }: { data?: KategoriCepat[] }) {
    const [kategori, setKategori] = useState('pagi');
    const [periode, setPeriode] = useState('1');

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <Timer className="size-5" /> Siswa Tercepat Hadir
                    </CardTitle>
                    <CardDescription>
                        10 siswa dengan rata-rata jam datang paling awal
                    </CardDescription>
                </div>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={periode}
                    onValueChange={(v) => v && setPeriode(v)}
                    aria-label="Periode"
                >
                    {PERIODE.map((p) => (
                        <ToggleGroupItem
                            key={p.nilai}
                            value={p.nilai}
                            className="px-3"
                        >
                            {p.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
            </CardHeader>
            <CardContent>
                {!data ? (
                    <div className="space-y-3">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <Skeleton key={i} className="h-11 w-full" />
                        ))}
                    </div>
                ) : (
                    <Tabs
                        value={
                            data.some((k) => k.kunci === kategori)
                                ? kategori
                                : 'pagi'
                        }
                        onValueChange={setKategori}
                        className="gap-4"
                    >
                        <TabsList>
                            {data.map((k) => (
                                <TabsTrigger key={k.kunci} value={k.kunci}>
                                    {k.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                        {data.map((k) => (
                            <TabsContent key={k.kunci} value={k.kunci}>
                                <Daftar data={k.periode[periode] ?? []} />
                            </TabsContent>
                        ))}
                    </Tabs>
                )}
            </CardContent>
        </Card>
    );
}
