import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/penyelenggara';
import { index as kategoriIndex } from '@/routes/penyelenggara/kategori';
import { index, show, store } from '@/routes/penyelenggara/program';

type Program = {
    id: string;
    name: string;
    kategori: string | null;
    starts_on: string;
    ends_on: string;
    grups_count: number;
    peserta_count: number;
    archived: boolean;
};

type Props = { programs: Program[]; kategoris: { id: string; name: string }[] };

export function rentangTanggal(mulai: string, selesai: string): string {
    const f = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

    return `${f.format(new Date(`${mulai}T00:00:00`))} – ${f.format(new Date(`${selesai}T00:00:00`))}`;
}

export default function ProgramIndex({ programs, kategoris }: Props) {
    return (
        <>
            <Head title="Program" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Program
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Satu program = satu periode berjalan, mis. "Haji 1448H"
                        atau "Pelatihan Batch 3".
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Program baru
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {kategoris.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Buat kategori dulu di{' '}
                                <Link
                                    href={kategoriIndex()}
                                    className="underline"
                                >
                                    halaman Kategori
                                </Link>
                                .
                            </p>
                        ) : (
                            <Form
                                {...store.form()}
                                resetOnSuccess
                                className="grid gap-4 md:grid-cols-[1fr_2fr_1fr_1fr_auto] md:items-end"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="kategori_id">
                                                Kategori
                                            </Label>
                                            <select
                                                id="kategori_id"
                                                name="kategori_id"
                                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                                required
                                            >
                                                {kategoris.map((k) => (
                                                    <option
                                                        key={k.id}
                                                        value={k.id}
                                                    >
                                                        {k.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.kategori_id}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                Nama program
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                placeholder="Haji 1448H"
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="starts_on">
                                                Mulai
                                            </Label>
                                            <Input
                                                id="starts_on"
                                                name="starts_on"
                                                type="date"
                                                required
                                            />
                                            <InputError
                                                message={errors.starts_on}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="ends_on">
                                                Selesai
                                            </Label>
                                            <Input
                                                id="ends_on"
                                                name="ends_on"
                                                type="date"
                                                required
                                            />
                                            <InputError
                                                message={errors.ends_on}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Buat
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </CardContent>
                </Card>

                {programs.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada program.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3 font-medium">Program</th>
                                    <th className="p-3 font-medium">
                                        Kategori
                                    </th>
                                    <th className="p-3 font-medium">Tanggal</th>
                                    <th className="p-3 text-right font-medium">
                                        Grup
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Peserta
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {programs.map((p) => (
                                    <tr key={p.id} className="border-t">
                                        <td className="p-3">
                                            <Link
                                                href={show.url(p.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {p.name}
                                            </Link>{' '}
                                            {p.archived && (
                                                <Badge variant="secondary">
                                                    Arsip
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {p.kategori}
                                        </td>
                                        <td className="p-3 whitespace-nowrap">
                                            {rentangTanggal(
                                                p.starts_on,
                                                p.ends_on,
                                            )}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {p.grups_count}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {p.peserta_count}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

ProgramIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Program', href: index() },
    ],
};
