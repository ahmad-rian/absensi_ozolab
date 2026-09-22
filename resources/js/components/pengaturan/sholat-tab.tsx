import { useForm } from '@inertiajs/react';
import { Save, Users } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

import type { SettingsValues } from './settings-tabs';

type SholatData = {
    section: 'sholat';
    prayer_dhuha_enabled: boolean;
    prayer_enabled: boolean;
    prayer_all_religions: boolean;
};

export function SholatTab({
    settings,
    onDirtyChange,
}: {
    settings: SettingsValues;
    onDirtyChange: (dirty: boolean) => void;
}) {
    const { data, setData, put, processing, isDirty } = useForm<SholatData>({
        section: 'sholat',
        prayer_dhuha_enabled: Boolean(settings.prayer_dhuha_enabled),
        prayer_enabled: Boolean(settings.prayer_enabled),
        prayer_all_religions: Boolean(settings.prayer_all_religions),
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

            <div className="flex flex-wrap gap-6">
                {(['prayer_dhuha_enabled', 'prayer_enabled'] as const).map(
                    (key) => (
                        <Label key={key} className="flex items-center gap-3">
                            <Checkbox
                                checked={data[key]}
                                onCheckedChange={(value) =>
                                    setData(key, Boolean(value))
                                }
                            />
                            {key === 'prayer_dhuha_enabled'
                                ? 'Aktifkan Dhuha'
                                : 'Aktifkan Dzuhur'}
                        </Label>
                    ),
                )}
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
