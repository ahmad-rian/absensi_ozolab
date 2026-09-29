import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CardCropReposition } from '@/components/shared/card-crop-reposition';
import type { CropRect } from '@/components/shared/card-crop-reposition';
import { DrivePhotoPicker } from '@/components/shared/drive-photo-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import { browse, image, thumbnail } from '@/routes/kartu-bebas/drive';
import { index, show, store, update } from '@/routes/kartu-bebas/peserta';
import type { Participant, ParticipantField } from './types';

type Layout = { id: string; name: string; fields: ParticipantField[] };
export default function ParticipantForm({
    layouts,
    participant,
}: {
    layouts: Layout[];
    participant: Participant | null;
}) {
    const form = useForm<{
        _method: string;
        layout_id: string;
        data: Record<string, string | File | null>;
        manual_crop: CropRect | null;
    }>({
        _method: participant ? 'put' : 'post',
        layout_id: participant?.layout_id ?? layouts[0]?.id ?? '',
        data: Object.fromEntries(
            Object.entries(participant?.data ?? {}).map(([key, value]) => [
                key,
                value === null ? '' : String(value),
            ]),
        ),
        manual_crop: null,
    });
    const layout = layouts.find((item) => item.id === form.data.layout_id);
    const [file, setFile] = useState<File | null>(null);
    const [cropExisting, setCropExisting] = useState(false);
    const [fileUrl, setFileUrl] = useState<string | null>(null);
    useEffect(
        () => () => {
            if (fileUrl) {
                URL.revokeObjectURL(fileUrl);
            }
        },
        [fileUrl],
    );
    const cropUrl = file
        ? fileUrl
        : cropExisting
          ? participant?.original_photo_url
          : null;
    const title = participant ? 'Edit Peserta' : 'Tambah Peserta';
    const choosePhoto = (key: string, selected: File) => {
        form.setData((previous) => ({
            ...previous,
            data: { ...previous.data, [key]: selected },
            manual_crop: null,
        }));
        setFile(selected);
        setFileUrl(URL.createObjectURL(selected));
    };

    return (
        <>
            <Head title={title} />
            <form
                className="max-w-3xl space-y-6 p-4 md:p-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(
                        participant ? update.url(participant.id) : store.url(),
                        { forceFormData: true },
                    );
                }}
            >
                <h1 className="text-2xl font-semibold">{title}</h1>
                {layouts.length === 0 && (
                    <p>
                        Tambahkan layout kartu terlebih dahulu sebelum membuat
                        peserta.
                    </p>
                )}
                <div className="space-y-2">
                    <Label htmlFor="participant-layout">Layout kartu</Label>
                    <select
                        id="participant-layout"
                        className="w-full rounded-md border bg-background p-2"
                        disabled={!!participant}
                        value={form.data.layout_id}
                        onChange={(event) => {
                            form.setData({
                                _method: 'post',
                                layout_id: event.target.value,
                                data: {},
                                manual_crop: null,
                            });
                            setFile(null);
                            setFileUrl(null);
                            setCropExisting(false);
                            form.clearErrors();
                        }}
                    >
                        {layouts.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                </div>
                {layout?.fields.map((field) => (
                    <div key={field.key} className="space-y-2">
                        <Label htmlFor={`participant-${field.key}`}>
                            {field.label}
                            {field.required ? ' *' : ''}
                        </Label>
                        {field.type === 'photo' ? (
                            <>
                                <Input
                                    id={`participant-${field.key}`}
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) => {
                                        const selected =
                                            event.target.files?.[0];

                                        if (selected) {
                                            choosePhoto(field.key, selected);
                                        }
                                    }}
                                />
                                <p className="text-sm text-muted-foreground">
                                    Pilih foto dari komputer atau Google Drive.
                                    Maksimal 8 MB.
                                </p>
                                <DrivePhotoPicker
                                    browseUrl={browse.url()}
                                    thumbnailUrl={(id) => thumbnail.url(id)}
                                    onSelect={async (selected) => {
                                        const response = await fetch(
                                            image.url(selected.id),
                                            {
                                                headers: {
                                                    Accept: 'application/json',
                                                },
                                            },
                                        );

                                        if (!response.ok) {
                                            throw new Error(
                                                'Foto gagal diambil. Coba pilih kembali.',
                                            );
                                        }

                                        const blob = await response.blob();
                                        choosePhoto(
                                            field.key,
                                            new File([blob], selected.name, {
                                                type: blob.type,
                                            }),
                                        );
                                    }}
                                />
                                {!file && participant?.photo_url && (
                                    <div className="space-y-2">
                                        <img
                                            src={participant.photo_url}
                                            alt="Foto peserta saat ini"
                                            className="h-48 rounded"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                setCropExisting(!cropExisting);
                                                form.setData(
                                                    'manual_crop',
                                                    null,
                                                );
                                            }}
                                        >
                                            {cropExisting
                                                ? 'Batalkan crop ulang'
                                                : 'Atur ulang crop'}
                                        </Button>
                                    </div>
                                )}
                                {cropUrl && (
                                    <CardCropReposition
                                        key={cropUrl}
                                        imageUrl={cropUrl}
                                        filename={file?.name}
                                        onChange={(crop) =>
                                            form.setData('manual_crop', crop)
                                        }
                                    />
                                )}
                            </>
                        ) : field.type === 'select' ? (
                            <select
                                id={`participant-${field.key}`}
                                required={field.required}
                                className="w-full rounded-md border bg-background p-2"
                                value={String(form.data.data[field.key] ?? '')}
                                onChange={(event) =>
                                    form.setData('data', {
                                        ...form.data.data,
                                        [field.key]: event.target.value,
                                    })
                                }
                            >
                                <option value="">Pilih {field.label}</option>
                                {field.options?.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        ) : (
                            <Input
                                id={`participant-${field.key}`}
                                type={
                                    field.type === 'number' ||
                                    field.type === 'date'
                                        ? field.type
                                        : 'text'
                                }
                                step={
                                    field.type === 'number' ? 'any' : undefined
                                }
                                required={field.required}
                                value={String(form.data.data[field.key] ?? '')}
                                onChange={(event) =>
                                    form.setData('data', {
                                        ...form.data.data,
                                        [field.key]: event.target.value,
                                    })
                                }
                            />
                        )}
                    </div>
                ))}
                {Object.keys(form.errors).length > 0 && (
                    <div
                        role="alert"
                        className="rounded-md border border-destructive p-3 text-destructive"
                    >
                        {Object.entries(form.errors).map(([key, message]) => (
                            <p key={key}>{message}</p>
                        ))}
                    </div>
                )}
                <p className="text-sm text-muted-foreground">
                    Simpan data, lalu periksa dan generate kartu di halaman
                    peserta. Hasil kartu lama tetap tersedia sampai kartu baru
                    berhasil dibuat.
                </p>
                <div className="flex gap-3">
                    <Button disabled={form.processing || !layout}>
                        {form.processing ? 'Menyimpan…' : 'Simpan Peserta'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link
                            href={
                                participant
                                    ? show.url(participant.id)
                                    : index.url()
                            }
                        >
                            Batal
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

ParticipantForm.layout = (page: React.ReactNode) => (
    <KartuBebasLayout breadcrumbs={[{ title: 'Peserta', href: index.url() }]}>
        {page}
    </KartuBebasLayout>
);
