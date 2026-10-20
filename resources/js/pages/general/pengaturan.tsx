import { Form, Head, useHttp } from '@inertiajs/react';
import {
    CheckCircle2,
    HardDrive,
    MessageSquare,
    Settings,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard, pengaturan as pengaturanRoute } from '@/routes/general';
import { drive, logo, umum, whatsapp } from '@/routes/general/pengaturan';
import { uji } from '@/routes/general/pengaturan/whatsapp';

type Props = {
    umum: {
        nama: string;
        logo: string | null;
        kuota_peserta_bawaan: number | null;
        kuota_wa_bawaan: number;
    };
    drive: { folder: string | null; kredensial: boolean };
    whatsapp: { gateway: 'ozolab' | 'fonnte'; token_terisi: boolean };
    /** Bagian yang boleh diubah staf ini (super admin: semua). */
    boleh: { umum: boolean; drive: boolean; whatsapp: boolean };
};

function Status({ ok, ya, tidak }: { ok: boolean; ya: string; tidak: string }) {
    return ok ? (
        <p className="flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-400">
            <CheckCircle2 className="size-3.5" /> {ya}
        </p>
    ) : (
        <p className="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
            <XCircle className="size-3.5" /> {tidak}
        </p>
    );
}

/**
 * Pengaturan workspace Absensi General — tersimpan terpisah dari pengaturan
 * sekolah mana pun.
 */
export default function GeneralPengaturan({
    umum: dataUmum,
    drive: dataDrive,
    whatsapp: dataWa,
    boleh,
}: Props) {
    const [gateway, setGateway] = useState(dataWa.gateway);
    const tes = useHttp({ nomor: '' });
    const [hasilTes, setHasilTes] = useState<{
        success: boolean;
        message: string;
    } | null>(null);

    return (
        <>
            <Head title="Pengaturan" />
            <div className="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Pengaturan
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Berlaku untuk seluruh Absensi General. Pengaturan
                        sekolah tidak ikut berubah.
                    </p>
                </div>

                {boleh.umum && (
                    <Card id="umum">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Settings className="size-4" /> Umum & branding
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-6">
                            <Form
                                {...umum.form()}
                                options={{ preserveScroll: true }}
                                className="grid gap-4 sm:grid-cols-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-1.5 sm:col-span-3">
                                            <Label htmlFor="nama">
                                                Nama workspace
                                            </Label>
                                            <Input
                                                id="nama"
                                                name="nama"
                                                defaultValue={dataUmum.nama}
                                                required
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Tampil di sidebar dan halaman
                                                area penyelenggara.
                                            </p>
                                            <InputError message={errors.nama} />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="kuota_peserta_bawaan">
                                                Kuota peserta bawaan
                                            </Label>
                                            <Input
                                                id="kuota_peserta_bawaan"
                                                name="kuota_peserta_bawaan"
                                                type="number"
                                                min={1}
                                                placeholder="Tanpa batas"
                                                defaultValue={
                                                    dataUmum.kuota_peserta_bawaan ??
                                                    undefined
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors.kuota_peserta_bawaan
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="kuota_wa_bawaan">
                                                Kuota WA/hari bawaan
                                            </Label>
                                            <Input
                                                id="kuota_wa_bawaan"
                                                name="kuota_wa_bawaan"
                                                type="number"
                                                min={0}
                                                defaultValue={
                                                    dataUmum.kuota_wa_bawaan
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.kuota_wa_bawaan}
                                            />
                                        </div>
                                        <div className="flex items-end">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Simpan
                                            </Button>
                                        </div>
                                        <p className="text-xs text-muted-foreground sm:col-span-3">
                                            Kuota bawaan dipakai saat
                                            penyelenggara baru dibuat; bisa
                                            diubah per penyelenggara.
                                        </p>
                                    </>
                                )}
                            </Form>

                            <Form
                                {...logo.form()}
                                encType="multipart/form-data"
                                options={{ preserveScroll: true }}
                                className="flex flex-wrap items-center gap-4 border-t pt-5"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        {dataUmum.logo ? (
                                            <img
                                                src={dataUmum.logo}
                                                alt="Logo"
                                                className="size-14 rounded-lg border object-contain p-1"
                                            />
                                        ) : (
                                            <span className="flex size-14 items-center justify-center rounded-lg border text-xs text-muted-foreground">
                                                Logo
                                            </span>
                                        )}
                                        <div className="grid gap-1">
                                            <Input
                                                type="file"
                                                name="logo"
                                                accept="image/png,image/jpeg,image/webp"
                                                required
                                            />
                                            <InputError message={errors.logo} />
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Unggah logo
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {boleh.drive && (
                    <Card id="drive">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <HardDrive className="size-4" /> Google Drive
                                kartu peserta
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <p className="text-muted-foreground">
                                Tempel tautan folder "Tyas General Apps" dari
                                address bar Chrome. Kartu disimpan di subfolder
                                kategori → penyelenggara → program.
                            </p>
                            <Form
                                {...drive.form()}
                                options={{ preserveScroll: true }}
                                className="flex flex-col gap-2 sm:flex-row sm:items-start"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid flex-1 gap-1">
                                            <Input
                                                name="folder"
                                                defaultValue={
                                                    dataDrive.folder ?? ''
                                                }
                                                placeholder="https://drive.google.com/drive/folders/…"
                                                aria-label="Folder Drive"
                                            />
                                            <InputError
                                                message={errors.folder}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Simpan folder
                                        </Button>
                                    </>
                                )}
                            </Form>
                            <Status
                                ok={Boolean(dataDrive.folder)}
                                ya="Folder sudah diatur"
                                tidak="Folder belum diatur — kartu hanya tersimpan di server"
                            />
                            <Status
                                ok={dataDrive.kredensial}
                                ya="Kredensial Google aktif"
                                tidak="Kredensial Google belum terpasang di server"
                            />
                        </CardContent>
                    </Card>
                )}

                {boleh.whatsapp && (
                    <Card id="whatsapp">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <MessageSquare className="size-4" /> WhatsApp
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 text-sm">
                            <Form
                                {...whatsapp.form()}
                                options={{ preserveScroll: true }}
                                className="grid gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label>Gateway</Label>
                                            <div className="flex flex-wrap gap-4">
                                                {(
                                                    [
                                                        'ozolab',
                                                        'fonnte',
                                                    ] as const
                                                ).map((g) => (
                                                    <label
                                                        key={g}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="gateway"
                                                            value={g}
                                                            checked={
                                                                gateway === g
                                                            }
                                                            onChange={() =>
                                                                setGateway(g)
                                                            }
                                                        />
                                                        {g === 'ozolab'
                                                            ? 'Ozolab (bawaan)'
                                                            : 'Fonnte (token sendiri)'}
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                        {gateway === 'fonnte' && (
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="fonnte_token">
                                                    Token Fonnte
                                                </Label>
                                                <Input
                                                    id="fonnte_token"
                                                    name="fonnte_token"
                                                    type="password"
                                                    autoComplete="off"
                                                    placeholder={
                                                        dataWa.token_terisi
                                                            ? 'Tersimpan — kosongkan untuk tetap memakai token lama'
                                                            : 'Tempel token dari dashboard Fonnte'
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.fonnte_token
                                                    }
                                                />
                                            </div>
                                        )}
                                        <div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Simpan WhatsApp
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>

                            <form
                                className="flex flex-col gap-2 border-t pt-5 sm:flex-row sm:items-start"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    setHasilTes(null);
                                    tes.post(uji.url(), {
                                        onSuccess: (data) =>
                                            setHasilTes(
                                                data as {
                                                    success: boolean;
                                                    message: string;
                                                },
                                            ),
                                    });
                                }}
                            >
                                <div className="grid flex-1 gap-1">
                                    <Input
                                        value={tes.data.nomor}
                                        onChange={(e) =>
                                            tes.setData('nomor', e.target.value)
                                        }
                                        placeholder="Nomor WA tujuan tes, mis. 081234567890"
                                        aria-label="Nomor WA tujuan tes"
                                    />
                                    <InputError message={tes.errors.nomor} />
                                </div>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={tes.processing || !tes.data.nomor}
                                >
                                    Kirim pesan tes
                                </Button>
                            </form>
                            {hasilTes && (
                                <Status
                                    ok={hasilTes.success}
                                    ya={hasilTes.message}
                                    tidak={hasilTes.message}
                                />
                            )}
                            <p className="text-xs text-muted-foreground">
                                Pengiriman notifikasi ke wali peserta memakai
                                pengaturan ini saat fiturnya diaktifkan.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

GeneralPengaturan.layout = {
    breadcrumbs: [
        { title: 'Absensi General', href: dashboard() },
        { title: 'Pengaturan', href: pengaturanRoute() },
    ],
};
