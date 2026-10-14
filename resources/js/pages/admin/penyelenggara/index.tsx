import { Form, Head, Link, router } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { drive, index, show, store } from '@/routes/admin/penyelenggara';

type Row = {
    id: string;
    name: string;
    slug: string;
    is_active: boolean;
    participant_quota: number | null;
    wa_daily_quota: number;
    users_count: number;
};

type Props = {
    penyelenggaras: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search: string };
    driveRootFolderId: string | null;
};

export default function PenyelenggaraIndex({
    penyelenggaras,
    filters,
    driveRootFolderId,
}: Props) {
    return (
        <>
            <Head title="Penyelenggara" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Penyelenggara
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Biro haji, panitia, atau instansi yang memakai absensi
                        multi-kategori. Data tiap penyelenggara terpisah dari
                        sekolah dan dari penyelenggara lain.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Plus className="size-4" /> Tambah penyelenggara
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form()}
                            resetOnSuccess
                            className="grid gap-4 md:grid-cols-[2fr_1fr_1fr_auto] md:items-end"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Nama</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            placeholder="Al-Hijrah Travel"
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="participant_quota">
                                            Kuota peserta
                                        </Label>
                                        <Input
                                            id="participant_quota"
                                            name="participant_quota"
                                            type="number"
                                            min={1}
                                            placeholder="Tanpa batas"
                                        />
                                        <InputError
                                            message={errors.participant_quota}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="wa_daily_quota">
                                            Kuota WA / hari
                                        </Label>
                                        <Input
                                            id="wa_daily_quota"
                                            name="wa_daily_quota"
                                            type="number"
                                            min={0}
                                            defaultValue={50}
                                        />
                                        <InputError
                                            message={errors.wa_daily_quota}
                                        />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Simpan
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Folder Google Drive kartu peserta
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm">
                        <p className="text-muted-foreground">
                            Tempel tautan folder "Tyas General Apps" (atau
                            ID-nya). Kartu tiap penyelenggara disimpan di
                            subfolder penyelenggara → program.
                        </p>
                        <Form
                            {...drive.form()}
                            className="flex flex-col gap-2 sm:flex-row sm:items-start"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid flex-1 gap-1">
                                        <Input
                                            name="folder"
                                            defaultValue={
                                                driveRootFolderId ?? ''
                                            }
                                            placeholder="https://drive.google.com/drive/folders/…"
                                            aria-label="Folder Drive"
                                        />
                                        <InputError message={errors.folder} />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Simpan folder
                                    </Button>
                                </>
                            )}
                        </Form>
                        {!driveRootFolderId && (
                            <p className="text-xs text-amber-600">
                                Belum diatur — kartu tetap dibuat dan bisa
                                diunduh dari server.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <div className="flex items-center gap-2">
                    <Input
                        className="max-w-sm"
                        placeholder="Cari nama penyelenggara…"
                        defaultValue={filters.search}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                router.get(
                                    index.url(),
                                    { search: e.currentTarget.value },
                                    { preserveState: true },
                                );
                            }
                        }}
                    />
                </div>

                {penyelenggaras.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 py-12 text-center text-sm text-muted-foreground">
                            <Building2 className="size-8" />
                            Belum ada penyelenggara. Tambahkan yang pertama di
                            atas.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3 font-medium">Nama</th>
                                    <th className="p-3 font-medium">Status</th>
                                    <th className="p-3 text-right font-medium">
                                        Akun
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Kuota peserta
                                    </th>
                                    <th className="p-3 text-right font-medium">
                                        Kuota WA/hari
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {penyelenggaras.data.map((p) => (
                                    <tr key={p.id} className="border-t">
                                        <td className="p-3">
                                            <Link
                                                href={show.url(p.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {p.name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {p.slug}
                                            </div>
                                        </td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    p.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {p.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {p.users_count}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {p.participant_quota ??
                                                'Tanpa batas'}
                                        </td>
                                        <td className="p-3 text-right tabular-nums">
                                            {p.wa_daily_quota}
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

PenyelenggaraIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Penyelenggara', href: index() },
    ],
};
