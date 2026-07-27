import { useForm } from '@inertiajs/react';
import { Save, Users } from 'lucide-react';
import { type FormEvent, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { PrayerWindowCard } from './prayer-window-card';
import type { SettingsValues } from './settings-tabs';

type SholatData = {
    section: 'sholat';
    prayer_dhuha_enabled: boolean;
    prayer_dhuha_start: string;
    prayer_dhuha_end: string;
    prayer_enabled: boolean;
    prayer_start: string;
    prayer_end: string;
    prayer_all_religions: boolean;
};

export function SholatTab({
    settings,
    onDirtyChange,
}: {
    settings: SettingsValues;
    onDirtyChange: (dirty: boolean) => void;
}) {
    const { data, setData, put, processing, errors, isDirty } = useForm<SholatData>({
        section: 'sholat',
        prayer_dhuha_enabled: Boolean(settings.prayer_dhuha_enabled),
        prayer_dhuha_start: (settings.prayer_dhuha_start as string) || '07:30',
        prayer_dhuha_end: (settings.prayer_dhuha_end as string) || '09:00',
        prayer_enabled: Boolean(settings.prayer_enabled),
        prayer_start: (settings.prayer_start as string) || '11:00',
        prayer_end: (settings.prayer_end as string) || '13:00',
        prayer_all_religions: Boolean(settings.prayer_all_religions),
    });

    useEffect(() => onDirtyChange(isDirty), [isDirty, onDirtyChange]);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        put('/admin/pengaturan', { preserveScroll: true, preserveState: true });
    }

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            <p className="text-muted-foreground text-sm">
                Kedua jenis sholat memakai satu tautan scan yang sama; jenisnya ditentukan dari jam scan.
                Karena itu jendela waktunya tidak boleh saling beririsan. Hari aktifnya mengikuti Jadwal Absensi.
            </p>

            <PrayerWindowCard
                idPrefix="prayer_dhuha"
                title="Sholat Dhuha"
                description="Jendela pagi, sekali scan per hari."
                tone="amber"
                enabled={data.prayer_dhuha_enabled}
                start={data.prayer_dhuha_start}
                end={data.prayer_dhuha_end}
                errors={{ start: errors.prayer_dhuha_start, end: errors.prayer_dhuha_end }}
                onEnabledChange={(value) => setData('prayer_dhuha_enabled', value)}
                onStartChange={(value) => setData('prayer_dhuha_start', value)}
                onEndChange={(value) => setData('prayer_dhuha_end', value)}
            />

            <PrayerWindowCard
                idPrefix="prayer"
                title="Sholat Dzuhur"
                description="Jendela siang, sekali scan per hari."
                tone="emerald"
                enabled={data.prayer_enabled}
                start={data.prayer_start}
                end={data.prayer_end}
                errors={{ start: errors.prayer_start, end: errors.prayer_end }}
                onEnabledChange={(value) => setData('prayer_enabled', value)}
                onStartChange={(value) => setData('prayer_start', value)}
                onEndChange={(value) => setData('prayer_end', value)}
            />

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Users className="size-5 text-blue-600" />
                        <CardTitle>Kepesertaan</CardTitle>
                    </div>
                    <CardDescription>Berlaku untuk kedua jenis sholat.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-2">
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="prayer_all_religions"
                            checked={data.prayer_all_religions}
                            onCheckedChange={(checked) => setData('prayer_all_religions', Boolean(checked))}
                        />
                        <Label htmlFor="prayer_all_religions" className="cursor-pointer text-sm font-medium">
                            Sertakan siswa non-Islam
                        </Label>
                    </div>
                    <p className="text-muted-foreground pl-7 text-xs">
                        Tanpa centang ini, hanya siswa beragama Islam yang bisa scan dan dihitung di laporan.
                        Tiap siswa masih bisa diatur satu per satu dari halaman detailnya.
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
