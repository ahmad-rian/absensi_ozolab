import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FieldBuilder from '@/pages/kartu-bebas/data/field-builder';
import type { FormField } from '@/pages/kartu-bebas/data/field-builder';
import { dashboard } from '@/routes/penyelenggara';
import { arsip, index, update } from '@/routes/penyelenggara/kategori';

type Kategori = {
    id: string;
    name: string;
    istilah_peserta: string;
    istilah_grup: string;
    features: Record<string, boolean>;
    fields: FormField[];
    archived: boolean;
};

export const FITUR_LABEL: Record<string, string> = {
    terlambat: 'Status terlambat',
    pulang: 'Absen pulang',
    izin_sakit: 'Izin & sakit',
    notifikasi_wali: 'Notifikasi wali',
    portal_wali: 'Portal wali',
    rfid: 'Kartu RFID',
};

/** Saklar yang disimpan sekarang tapi fiturnya baru hadir di rilis berikutnya. */
const FITUR_SEGERA = new Set(['notifikasi_wali', 'portal_wali', 'rfid']);

type Props = { kategori: Kategori; errors: Record<string, string> };

export default function KategoriEdit({ kategori, errors }: Props) {
    const [name, setName] = useState(kategori.name);
    const [istilahPeserta, setIstilahPeserta] = useState(
        kategori.istilah_peserta,
    );
    const [istilahGrup, setIstilahGrup] = useState(kategori.istilah_grup);
    const [features, setFeatures] = useState(kategori.features);
    const [fields, setFields] = useState<FormField[]>(kategori.fields);
    const [processing, setProcessing] = useState(false);

    const simpan = () =>
        router.put(
            update.url(kategori.id),
            {
                name,
                istilah_peserta: istilahPeserta,
                istilah_grup: istilahGrup,
                features,
                fields,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );

    return (
        <>
            <Head title={kategori.name} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold tracking-tight">
                        {kategori.name}
                    </h1>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.post(arsip.url(kategori.id))}
                    >
                        {kategori.archived ? 'Aktifkan lagi' : 'Arsipkan'}
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Istilah & fitur
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="istilah_peserta">
                                Sebutan peserta
                            </Label>
                            <Input
                                id="istilah_peserta"
                                value={istilahPeserta}
                                onChange={(e) =>
                                    setIstilahPeserta(e.target.value)
                                }
                            />
                            <InputError message={errors.istilah_peserta} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="istilah_grup">Sebutan grup</Label>
                            <Input
                                id="istilah_grup"
                                value={istilahGrup}
                                onChange={(e) => setIstilahGrup(e.target.value)}
                            />
                            <InputError message={errors.istilah_grup} />
                        </div>
                        <div className="grid gap-2 sm:grid-cols-3 md:col-span-2">
                            {Object.keys(FITUR_LABEL).map((key) => (
                                <label
                                    key={key}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={Boolean(features[key])}
                                        onCheckedChange={(checked) =>
                                            setFeatures({
                                                ...features,
                                                [key]: checked === true,
                                            })
                                        }
                                    />
                                    {FITUR_LABEL[key]}
                                    {FITUR_SEGERA.has(key) && (
                                        <span className="text-xs text-muted-foreground">
                                            (segera hadir)
                                        </span>
                                    )}
                                </label>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Kolom data {istilahPeserta.toLowerCase()}
                        </CardTitle>
                        <p className="text-xs text-muted-foreground">
                            Kolom Nama dan Telepon Wali selalu ada. Kolom yang
                            dihapus disembunyikan; datanya tetap tersimpan.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <FieldBuilder
                            name={name}
                            fields={fields}
                            errors={errors}
                            onName={setName}
                            onFields={setFields}
                        />
                    </CardContent>
                </Card>

                <Button
                    onClick={simpan}
                    disabled={processing || kategori.archived}
                    className="self-start"
                >
                    Simpan kategori
                </Button>
            </div>
        </>
    );
}

KategoriEdit.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Kategori', href: index() },
    ],
};
