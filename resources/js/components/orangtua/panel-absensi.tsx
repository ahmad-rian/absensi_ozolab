import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type {
    BarisHari,
    Catatan,
    Panel,
    Ringkasan,
} from '@/components/orangtua/portal-page';
import { TOOLTIP_STYLE } from '@/components/shared/chart-frame';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

/*
    Warna status dipakai SAMA di kartu angka, donat, dan batang.

    Kalau "Terlambat" kuning di satu tempat dan oranye di tempat lain, orang tua
    harus membaca ulang legenda setiap kali pindah grafik.
*/
export const WARNA = {
    hadir: '#059669',
    terlambat: '#d97706',
    izin: '#2563eb',
    sakit: '#7c3aed',
    alpa: '#dc2626',
    tidak_hadir: '#dc2626',
} as const;

export function KartuAngka({
    label,
    nilai,
    satuan,
    warna,
}: {
    label: string;
    nilai: number | string;
    satuan?: string;
    warna?: string;
}) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p
                className="mt-1.5 text-2xl font-bold tabular-nums"
                style={warna ? { color: warna } : undefined}
            >
                {nilai}
                {satuan && (
                    <span className="ml-1 text-sm font-normal text-muted-foreground">
                        {satuan}
                    </span>
                )}
            </p>
        </div>
    );
}

export function RingkasanAngka({
    summary,
    sholat = false,
}: {
    summary: Ringkasan;
    sholat?: boolean;
}) {
    const kartu = sholat
        ? [
              { label: 'Ikut', nilai: summary.hadir, warna: WARNA.hadir },
              {
                  label: 'Tidak ikut',
                  nilai: summary.tidak_hadir ?? 0,
                  warna: WARNA.alpa,
              },
              { label: 'Hari efektif', nilai: summary.effective_days },
              { label: 'Kehadiran', nilai: `${summary.rate}%` },
          ]
        : [
              { label: 'Hadir', nilai: summary.hadir, warna: WARNA.hadir },
              {
                  label: 'Terlambat',
                  nilai: summary.terlambat ?? 0,
                  warna: WARNA.terlambat,
              },
              {
                  label: 'Izin / Sakit',
                  nilai: (summary.izin ?? 0) + (summary.sakit ?? 0),
                  warna: WARNA.izin,
              },
              { label: 'Alpa', nilai: summary.alpa ?? 0, warna: WARNA.alpa },
              { label: 'Hari efektif', nilai: summary.effective_days },
              { label: 'Kehadiran', nilai: `${summary.rate}%` },
          ];

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            {kartu.map((k) => (
                <KartuAngka
                    key={k.label}
                    label={k.label}
                    nilai={k.nilai}
                    warna={k.warna}
                />
            ))}
        </div>
    );
}

export function DonatStatus({
    panel,
    sholat = false,
}: {
    panel: Panel;
    sholat?: boolean;
}) {
    const data = sholat
        ? [
              { name: 'Ikut', value: panel.summary.hadir, color: WARNA.hadir },
              {
                  name: 'Tidak ikut',
                  value: panel.summary.tidak_hadir ?? 0,
                  color: WARNA.alpa,
              },
          ]
        : [
              { name: 'Hadir', value: panel.summary.hadir, color: WARNA.hadir },
              {
                  name: 'Terlambat',
                  value: panel.summary.terlambat ?? 0,
                  color: WARNA.terlambat,
              },
              {
                  name: 'Izin',
                  value: panel.summary.izin ?? 0,
                  color: WARNA.izin,
              },
              {
                  name: 'Sakit',
                  value: panel.summary.sakit ?? 0,
                  color: WARNA.sakit,
              },
              {
                  name: 'Alpa',
                  value: panel.summary.alpa ?? 0,
                  color: WARNA.alpa,
              },
          ];

    const terisi = data.filter((d) => d.value > 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Sebaran Status</CardTitle>
                <CardDescription>
                    {panel.label} pada periode terpilih
                </CardDescription>
            </CardHeader>
            <CardContent>
                {terisi.length === 0 ? (
                    <Kosong tinggi={260} />
                ) : (
                    <ResponsiveContainer width="100%" height={260}>
                        <PieChart>
                            <Pie
                                data={terisi}
                                cx="50%"
                                cy="50%"
                                innerRadius={58}
                                outerRadius={92}
                                paddingAngle={3}
                                dataKey="value"
                                nameKey="name"
                            >
                                {terisi.map((baris) => (
                                    <Cell key={baris.name} fill={baris.color} />
                                ))}
                            </Pie>
                            <Tooltip contentStyle={TOOLTIP_STYLE} />
                            <Legend
                                verticalAlign="bottom"
                                height={32}
                                formatter={(value: string) => (
                                    <span className="text-xs text-foreground">
                                        {value}
                                    </span>
                                )}
                            />
                        </PieChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}

export function BatangPerHari({ series }: { series: BarisHari[] }) {
    const adaIsi = series.some((baris) => baris.effective > 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Pola per Hari</CardTitle>
                <CardDescription>
                    Hari mana anak paling sering terlambat
                </CardDescription>
            </CardHeader>
            <CardContent>
                {!adaIsi ? (
                    <Kosong tinggi={260} />
                ) : (
                    <ResponsiveContainer width="100%" height={260}>
                        <BarChart
                            data={series}
                            margin={{ top: 4, right: 4, left: -20, bottom: 0 }}
                        >
                            <CartesianGrid
                                strokeDasharray="3 3"
                                vertical={false}
                                className="stroke-border"
                            />
                            <XAxis
                                dataKey="weekday"
                                tickLine={false}
                                axisLine={false}
                                fontSize={11}
                            />
                            <YAxis
                                allowDecimals={false}
                                tickLine={false}
                                axisLine={false}
                                fontSize={11}
                            />
                            <Tooltip
                                contentStyle={TOOLTIP_STYLE}
                                cursor={{ fillOpacity: 0.08 }}
                            />
                            <Legend
                                verticalAlign="bottom"
                                height={32}
                                formatter={(value: string) => (
                                    <span className="text-xs text-foreground">
                                        {value}
                                    </span>
                                )}
                            />
                            <Bar
                                dataKey="hadir"
                                name="Hadir"
                                stackId="a"
                                fill={WARNA.hadir}
                                radius={[0, 0, 0, 0]}
                            />
                            <Bar
                                dataKey="terlambat"
                                name="Terlambat"
                                stackId="a"
                                fill={WARNA.terlambat}
                            />
                            <Bar
                                dataKey="izin"
                                name="Izin"
                                stackId="a"
                                fill={WARNA.izin}
                            />
                            <Bar
                                dataKey="sakit"
                                name="Sakit"
                                stackId="a"
                                fill={WARNA.sakit}
                            />
                            <Bar
                                dataKey="alpa"
                                name="Alpa"
                                stackId="a"
                                fill={WARNA.alpa}
                                radius={[4, 4, 0, 0]}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * Status ditandai warna DAN kata. Lencana yang hanya berwarna tidak terbaca
 * oleh yang buta warna, dan tidak terbaca sama sekali saat dicetak.
 */
export function LencanaStatus({ label }: { label: string }) {
    const kunci = label.toLowerCase();
    const warna = kunci.includes('terlambat')
        ? WARNA.terlambat
        : kunci.includes('hadir') || kunci.includes('ikut')
          ? WARNA.hadir
          : kunci.includes('izin')
            ? WARNA.izin
            : kunci.includes('sakit')
              ? WARNA.sakit
              : WARNA.alpa;

    return (
        <Badge variant="outline" className="gap-1.5 font-normal">
            <span
                className="size-2 shrink-0 rounded-full"
                style={{ backgroundColor: warna }}
            />
            {label}
        </Badge>
    );
}

export function TabelCatatan({ recent }: { recent: Catatan[] }) {
    if (recent.length === 0) {
        return (
            <p className="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground">
                Belum ada catatan pada periode ini.
            </p>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border bg-card">
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="bg-muted/50">
                        <tr className="text-xs tracking-wide text-muted-foreground uppercase">
                            <th className="px-4 py-3 font-medium">Tanggal</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Jam</th>
                        </tr>
                    </thead>
                    <tbody>
                        {recent.map((baris) => (
                            <tr key={baris.id} className="border-t">
                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                    {baris.date}
                                </td>
                                <td className="px-4 py-3">
                                    <LencanaStatus label={baris.status_label} />
                                </td>
                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                    {baris.time ?? '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function Kosong({ tinggi }: { tinggi: number }) {
    return (
        <div
            className="flex items-center justify-center text-sm text-muted-foreground"
            style={{ height: tinggi }}
        >
            Belum ada data pada periode ini
        </div>
    );
}
