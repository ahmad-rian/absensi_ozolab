import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes/penyelenggara';
import { simpan } from '@/routes/penyelenggara/impor';
import { index } from '@/routes/penyelenggara/peserta';

type Props = {
    program: { id: string; name: string };
    pratinjau: {
        kunci: string;
        filename: string;
        total: number;
        valid: number;
        gagal: number;
        errors: { baris: number; alasan: string }[];
        contoh: Record<string, string | null>[];
    };
};

export default function ImporPratinjau({ program, pratinjau }: Props) {
    const kolom = Object.keys(pratinjau.contoh[0] ?? {});

    return (
        <>
            <Head title="Pratinjau impor" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Pratinjau impor
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {pratinjau.filename} · {program.name}. Belum ada yang
                        disimpan.
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-2xl font-semibold tabular-nums">
                                {pratinjau.total}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                baris dibaca
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-2xl font-semibold text-emerald-600 tabular-nums">
                                {pratinjau.valid}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                siap disimpan
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-2xl font-semibold text-red-600 tabular-nums">
                                {pratinjau.gagal}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                akan dilewati
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {pratinjau.errors.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Baris yang dilewati
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y text-sm">
                                {pratinjau.errors.map((e) => (
                                    <li
                                        key={e.baris}
                                        className="flex gap-3 py-2"
                                    >
                                        <span className="w-16 shrink-0 text-muted-foreground tabular-nums">
                                            Baris {e.baris}
                                        </span>
                                        <span>{e.alasan}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {pratinjau.contoh.length > 0 && (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    {kolom.map((k) => (
                                        <th key={k} className="p-3 font-medium">
                                            {k}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {pratinjau.contoh.map((row, i) => (
                                    <tr key={i} className="border-t">
                                        {kolom.map((k) => (
                                            <td key={k} className="p-3">
                                                {row[k] ?? '—'}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="flex gap-2">
                    <Button
                        disabled={pratinjau.valid === 0}
                        onClick={() =>
                            router.post(
                                simpan.url({
                                    program: program.id,
                                    kunci: pratinjau.kunci,
                                }),
                            )
                        }
                    >
                        Simpan {pratinjau.valid} baris
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={index(program.id)}>Batal</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

ImporPratinjau.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
