import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { RiwayatKehadiranCard } from '@/components/penyelenggara/riwayat-kehadiran';
import type {
    HalamanRiwayat,
    StatistikKehadiran,
} from '@/components/penyelenggara/riwayat-kehadiran';
import { CardCropReposition } from '@/components/shared/card-crop-reposition';
import type { CropRect } from '@/components/shared/card-crop-reposition';
import { PilihKecamatan } from '@/components/shared/pilih-kecamatan';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAksesPenyelenggara } from '@/hooks/use-akses-penyelenggara';
import { dashboard } from '@/routes/penyelenggara';
import {
    destroy,
    generate,
    index,
    kartu,
    restore,
    store,
    update,
} from '@/routes/penyelenggara/peserta';
import { STATUS_KARTU } from './index';

type Field = {
    key: string;
    label: string;
    type: 'text' | 'number' | 'date' | 'select' | 'kecamatan' | 'photo';
    required?: boolean;
    options?: string[];
};

type Props = {
    program: {
        id: string;
        name: string;
        archived: boolean;
        istilah_peserta: string;
        istilah_grup: string;
    };
    fields: Field[];
    grups: { id: string; name: string; parent_id: string | null }[];
    istilahPeserta: string;
    peserta: {
        id: string;
        data: Record<string, string | null>;
        grup_ids: string[];
        status: string;
        photo_url: string | null;
        original_url: string | null;
        has_card: boolean;
        archived: boolean;
    } | null;
    riwayat: {
        tanggal: string;
        aksi: 'masuk' | 'keluar' | 'pindah';
        dari: string | null;
        ke: string | null;
        oleh: string | null;
    }[];
    riwayatKehadiran?: HalamanRiwayat | null;
    statistikKehadiran?: StatistikKehadiran | null;
};

export default function PesertaForm({
    program,
    fields,
    grups,
    istilahPeserta,
    peserta,
    riwayat,
    riwayatKehadiran,
    statistikKehadiran,
}: Props) {
    const akses = useAksesPenyelenggara();
    const bolehKelola = akses.kelolaPeserta && !program.archived;
    const form = useForm<{
        data: Record<string, string | File | null>;
        grup_ids: string[];
        manual_crop: CropRect | null;
    }>({
        data: Object.fromEntries(
            fields
                .filter((f) => f.type !== 'photo')
                .map((f) => [f.key, peserta?.data?.[f.key] ?? '']),
        ),
        grup_ids: peserta?.grup_ids ?? [],
        manual_crop: null,
    });
    const [file, setFile] = useState<File | null>(null);
    const [fileUrl, setFileUrl] = useState<string | null>(null);
    const [cropLama, setCropLama] = useState(false);
    useEffect(
        () => () => void (fileUrl && URL.revokeObjectURL(fileUrl)),
        [fileUrl],
    );

    const fotoField = fields.find((f) => f.type === 'photo');
    const cropUrl = file ? fileUrl : cropLama ? peserta?.original_url : null;

    const simpan = () => {
        const opsi = { forceFormData: true, preserveScroll: true };

        if (peserta) {
            form.post(update.url(peserta.id), opsi);
        } else {
            form.post(store.url(program.id), opsi);
        }
    };

    const errors = form.errors as Record<string, string>;

    return (
        <>
            <Head
                title={
                    peserta
                        ? `Ubah ${istilahPeserta}`
                        : `Tambah ${istilahPeserta}`
                }
            />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold tracking-tight">
                        {peserta
                            ? `Ubah ${istilahPeserta.toLowerCase()}`
                            : `Tambah ${istilahPeserta.toLowerCase()}`}
                    </h1>
                    <Link
                        href={index(program.id)}
                        className="text-sm underline"
                    >
                        Kembali ke daftar
                    </Link>
                </div>

                {errors.kuota && (
                    <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        {errors.kuota}
                    </p>
                )}

                <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Data</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {fields
                                .filter((f) => f.type !== 'photo')
                                .map((f) => (
                                    <div key={f.key} className="grid gap-2">
                                        <Label htmlFor={f.key}>
                                            {f.label}
                                            {f.required && (
                                                <span className="text-red-600">
                                                    {' '}
                                                    *
                                                </span>
                                            )}
                                        </Label>
                                        {f.type === 'kecamatan' ? (
                                            <PilihKecamatan
                                                id={f.key}
                                                nilai={String(
                                                    form.data.data[f.key] ?? '',
                                                )}
                                                onUbah={(v) =>
                                                    form.setData('data', {
                                                        ...form.data.data,
                                                        [f.key]: v,
                                                    })
                                                }
                                            />
                                        ) : f.type === 'select' ? (
                                            <select
                                                id={f.key}
                                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                                value={String(
                                                    form.data.data[f.key] ?? '',
                                                )}
                                                onChange={(e) =>
                                                    form.setData('data', {
                                                        ...form.data.data,
                                                        [f.key]: e.target.value,
                                                    })
                                                }
                                            >
                                                <option value="">—</option>
                                                {(f.options ?? []).map((o) => (
                                                    <option key={o} value={o}>
                                                        {o}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            <Input
                                                id={f.key}
                                                type={
                                                    f.type === 'number'
                                                        ? 'number'
                                                        : f.type === 'date'
                                                          ? 'date'
                                                          : 'text'
                                                }
                                                value={String(
                                                    form.data.data[f.key] ?? '',
                                                )}
                                                onChange={(e) =>
                                                    form.setData('data', {
                                                        ...form.data.data,
                                                        [f.key]: e.target.value,
                                                    })
                                                }
                                            />
                                        )}
                                        <InputError
                                            message={errors[`data.${f.key}`]}
                                        />
                                    </div>
                                ))}

                            {grups.length > 0 && (
                                <div className="grid gap-2">
                                    <Label>{program.istilah_grup}</Label>
                                    <div className="grid gap-1 sm:grid-cols-2">
                                        {grups.map((g) => (
                                            <label
                                                key={g.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.grup_ids.includes(
                                                        g.id,
                                                    )}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'grup_ids',
                                                            e.target.checked
                                                                ? [
                                                                      ...form
                                                                          .data
                                                                          .grup_ids,
                                                                      g.id,
                                                                  ]
                                                                : form.data.grup_ids.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          g.id,
                                                                  ),
                                                        )
                                                    }
                                                />
                                                {g.parent_id ? '↳ ' : ''}
                                                {g.name}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-6">
                        {fotoField && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {fotoField.label}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-3">
                                    {peserta?.photo_url && !cropUrl && (
                                        <img
                                            src={peserta.photo_url}
                                            alt=""
                                            className="w-40 rounded border"
                                        />
                                    )}
                                    <Input
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => {
                                            const selected =
                                                e.target.files?.[0];

                                            if (!selected) {
                                                return;
                                            }

                                            setFile(selected);
                                            setFileUrl(
                                                URL.createObjectURL(selected),
                                            );
                                            form.setData((prev) => ({
                                                ...prev,
                                                data: {
                                                    ...prev.data,
                                                    [fotoField.key]: selected,
                                                },
                                                manual_crop: null,
                                            }));
                                        }}
                                    />
                                    {peserta?.original_url && !file && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setCropLama(true)}
                                        >
                                            Atur ulang posisi foto
                                        </Button>
                                    )}
                                    {cropUrl && (
                                        <CardCropReposition
                                            key={cropUrl}
                                            imageUrl={cropUrl}
                                            filename={file?.name}
                                            onChange={(crop) =>
                                                form.setData(
                                                    'manual_crop',
                                                    crop,
                                                )
                                            }
                                        />
                                    )}
                                    <InputError
                                        message={
                                            errors[`data.${fotoField.key}`] ??
                                            errors.manual_crop
                                        }
                                    />
                                </CardContent>
                            </Card>
                        )}

                        {peserta && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Kartu
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-2 text-sm">
                                    <p>
                                        Status:{' '}
                                        {STATUS_KARTU[peserta.status] ??
                                            peserta.status}
                                    </p>
                                    {bolehKelola && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    generate.url(peserta.id),
                                                )
                                            }
                                        >
                                            {peserta.has_card
                                                ? 'Generate ulang kartu'
                                                : 'Generate kartu'}
                                        </Button>
                                    )}
                                    {peserta.has_card && (
                                        <a
                                            href={kartu.url(peserta.id, {
                                                query: { unduh: 1 },
                                            })}
                                            className="underline"
                                        >
                                            Unduh kartu
                                        </a>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        QR di kartu tidak berubah walau kartu
                                        digenerate ulang.
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {riwayat.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Riwayat {program.istilah_grup}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="grid gap-1 text-sm">
                                        {riwayat.map((r, i) => (
                                            <li key={i}>
                                                <span className="text-muted-foreground">
                                                    {r.tanggal.slice(0, 16)}
                                                </span>{' '}
                                                {r.aksi === 'pindah'
                                                    ? `Pindah dari ${r.dari} ke ${r.ke}`
                                                    : r.aksi === 'masuk'
                                                      ? `Masuk ${r.ke}`
                                                      : `Keluar dari ${r.dari}`}
                                                {r.oleh && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        · {r.oleh}
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>

                {peserta && (
                    <RiwayatKehadiranCard
                        statistik={statistikKehadiran}
                        halaman={riwayatKehadiran}
                        onPage={(page) =>
                            router.reload({
                                only: ['riwayatKehadiran'],
                                data: { riwayat_page: page },
                            })
                        }
                    />
                )}

                {bolehKelola && (
                    <div className="flex flex-wrap gap-2">
                        <Button onClick={simpan} disabled={form.processing}>
                            Simpan
                        </Button>
                        {peserta &&
                            (peserta.archived ? (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(restore.url(peserta.id))
                                    }
                                >
                                    Pulihkan
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        window.confirm(
                                            `Arsipkan ${istilahPeserta.toLowerCase()} ini? Kartunya tidak bisa dipakai scan sampai dipulihkan.`,
                                        ) &&
                                        router.delete(destroy.url(peserta.id))
                                    }
                                >
                                    Arsipkan
                                </Button>
                            ))}
                    </div>
                )}
            </div>
        </>
    );
}

PesertaForm.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
