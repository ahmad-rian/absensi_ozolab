import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes/penyelenggara';
import { edit, index, store } from '@/routes/penyelenggara/kategori';

type Kategori = {
    id: string;
    name: string;
    template: string | null;
    istilah_peserta: string;
    istilah_grup: string;
    programs_count: number;
    archived: boolean;
};

type Template = {
    key: string;
    name: string;
    istilah_peserta: string;
    istilah_grup: string;
    fields: string[];
};

type Props = { kategoris: Kategori[]; templates: Template[] };

export default function KategoriIndex({ kategoris, templates }: Props) {
    return (
        <>
            <Head title="Kategori" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Kategori
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Jenis kegiatan yang Anda kelola. Mulai dari template,
                        lalu sesuaikan kolom data peserta.
                    </p>
                </div>

                <section className="grid gap-3">
                    <h2 className="text-sm font-semibold text-muted-foreground">
                        Buat dari template
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        {templates.map((t) => (
                            <Card key={t.key} className="flex flex-col">
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base">
                                        {t.name}
                                    </CardTitle>
                                    <p className="text-xs text-muted-foreground">
                                        {t.istilah_peserta} · {t.istilah_grup}
                                    </p>
                                </CardHeader>
                                <CardContent className="flex flex-1 flex-col justify-between gap-3">
                                    <p className="text-xs text-muted-foreground">
                                        {t.fields.join(', ')}
                                    </p>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(store.url(), {
                                                template: t.key,
                                            })
                                        }
                                    >
                                        <Plus className="size-4" /> Pakai
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>

                <section className="grid gap-3">
                    <h2 className="text-sm font-semibold text-muted-foreground">
                        Kategori Anda
                    </h2>
                    {kategoris.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada kategori. Pilih salah satu template di
                            atas.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-3 font-medium">
                                            Nama
                                        </th>
                                        <th className="p-3 font-medium">
                                            Istilah
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Program
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {kategoris.map((k) => (
                                        <tr key={k.id} className="border-t">
                                            <td className="p-3">
                                                <Link
                                                    href={edit.url(k.id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {k.name}
                                                </Link>{' '}
                                                {k.archived && (
                                                    <Badge variant="secondary">
                                                        Arsip
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="p-3 text-muted-foreground">
                                                {k.istilah_peserta} ·{' '}
                                                {k.istilah_grup}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {k.programs_count}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

KategoriIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Kategori', href: index() },
    ],
};
