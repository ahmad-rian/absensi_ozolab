import { useForm } from '@inertiajs/react';
import { Save, Users } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import type { SettingsValues } from './settings-tabs';

type SholatData = {
    section: 'sholat';
    prayer_dhuha_enabled: boolean;
    prayer_enabled: boolean;
    prayer_all_religions: boolean;
    prayer_dhuha_nama: string;
    prayer_nama: string;
};

/** Sama dengan PrayerType::NAMA_MAKS di server. */
const NAMA_MAKS = 30;

const JENIS = [
    {
        aktif: 'prayer_dhuha_enabled',
        nama: 'prayer_dhuha_nama',
        bawaan: 'Dhuha',
    },
    { aktif: 'prayer_enabled', nama: 'prayer_nama', bawaan: 'Dzuhur' },
] as const;

export function SholatTab({
    settings,
    onDirtyChange,
}: {
    settings: SettingsValues;
    onDirtyChange: (dirty: boolean) => void;
}) {
    const { data, setData, put, processing, isDirty, errors } =
        useForm<SholatData>({
            section: 'sholat',
            prayer_dhuha_enabled: Boolean(settings.prayer_dhuha_enabled),
            prayer_enabled: Boolean(settings.prayer_enabled),
            prayer_all_religions: Boolean(settings.prayer_all_religions),
            prayer_dhuha_nama: String(settings.prayer_dhuha_nama ?? ''),
            prayer_nama: String(settings.prayer_nama ?? ''),
        });

    useEffect(() => onDirtyChange(isDirty), [isDirty, onDirtyChange]);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        put('/admin/pengaturan', { preserveScroll: true, preserveState: true });
    }

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            <p className="text-sm text-muted-foreground">
                Kedua jenis sholat memakai satu tautan scan yang sama; jenisnya
                ditentukan dari jam scan. Karena itu jendela waktunya tidak
                boleh saling beririsan. Hari aktifnya mengikuti Jadwal Absensi.
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
                {JENIS.map(({ aktif, nama, bawaan }) => {
                    const tampil = data[nama].trim() || bawaan;

                    return (
                        <div
                            key={aktif}
                            className="flex flex-col gap-3 rounded-lg border p-4"
                        >
                            <Label className="flex items-center gap-3">
                                <Checkbox
                                    checked={data[aktif]}
                                    onCheckedChange={(value) =>
                                        setData(aktif, Boolean(value))
                                    }
                                />
                                Aktifkan {tampil}
                            </Label>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor={nama} className="text-xs">
                                    Nama tampilan (bawaan: {bawaan})
                                </Label>
                                <Input
                                    id={nama}
                                    value={data[nama]}
                                    maxLength={NAMA_MAKS}
                                    placeholder={bawaan}
                                    onChange={(e) =>
                                        setData(nama, e.target.value)
                                    }
                                />
                                <InputError message={errors[nama]} />
                                <p className="text-xs text-muted-foreground">
                                    Dipakai di layar scan, laporan, dan pesan
                                    WhatsApp. Kosongkan untuk memakai nama
                                    bawaan.
                                </p>
                            </div>
                        </div>
                    );
                })}
            </div>
            <p className="text-sm text-muted-foreground">
                Jam sholat diatur melalui menu Jadwal Absensi.
            </p>
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Users className="size-5 text-blue-600" />
                        <CardTitle>Kepesertaan</CardTitle>
                    </div>
                    <CardDescription>
                        Berlaku untuk kedua jenis sholat.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-2">
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="prayer_all_religions"
                            checked={data.prayer_all_religions}
                            onCheckedChange={(checked) =>
                                setData(
                                    'prayer_all_religions',
                                    Boolean(checked),
                                )
                            }
                        />
                        <Label
                            htmlFor="prayer_all_religions"
                            className="cursor-pointer text-sm font-medium"
                        >
                            Sertakan siswa non-Islam
                        </Label>
                    </div>
                    <p className="pl-7 text-xs text-muted-foreground">
                        Tanpa centang ini, hanya siswa beragama Islam yang bisa
                        scan dan dihitung di laporan. Tiap siswa masih bisa
                        diatur satu per satu dari halaman detailnya.
                    </p>
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing || !isDirty}>
                    <Save className="mr-2 size-4" />
                    Simpan Pengaturan Sholat
                </Button>
            </div>
        </form>
    );
}
