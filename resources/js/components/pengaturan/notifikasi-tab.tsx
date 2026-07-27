import { useForm } from '@inertiajs/react';
import { Bell, MoonStar, Save } from 'lucide-react';
import { type FormEvent, useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import type { SchoolFeatureMap } from '@/types';
import type { SettingsValues } from './settings-tabs';

type NotifikasiData = {
    section: 'notifikasi';
    whatsapp_enabled: boolean;
    notify_on_check_in: boolean;
    notify_on_check_out: boolean;
    prayer_absence_threshold: number;
    prayer_absence_require_present: boolean;
    whatsapp_template_attendance: string;
    whatsapp_template_prayer_absence: string;
};

// Variabel yang benar-benar disubstitusi gateway. Daftar lama di halaman ini
// mengiklankan {parent_name} dan kawan-kawan yang tidak pernah diganti kode
// mana pun.
const ATTENDANCE_VARS = '{nama_siswa}, {kelas}, {waktu}, {tanggal}, {status}, {jenis}, {aktivitas}, {nama_sekolah}';
const ABSENCE_VARS =
    '{nama_siswa}, {kelas}, {nama_sekolah}, {jenis_sholat}, {jumlah_hari}, {ambang}, {tanggal_mulai}, {tanggal_terakhir}, {daftar_tanggal}';

export function NotifikasiTab({
    settings,
    features,
    onDirtyChange,
}: {
    settings: SettingsValues;
    features: SchoolFeatureMap;
    onDirtyChange: (dirty: boolean) => void;
}) {
    const { data, setData, put, processing, errors, isDirty } = useForm<NotifikasiData>({
        section: 'notifikasi',
        whatsapp_enabled: Boolean(settings.whatsapp_enabled),
        notify_on_check_in: Boolean(settings.notify_on_check_in),
        notify_on_check_out: Boolean(settings.notify_on_check_out),
        prayer_absence_threshold: Number(settings.prayer_absence_threshold ?? 3),
        prayer_absence_require_present: Boolean(settings.prayer_absence_require_present),
        whatsapp_template_attendance: (settings.whatsapp_template_attendance as string) || '',
        whatsapp_template_prayer_absence: (settings.whatsapp_template_prayer_absence as string) || '',
    });

    useEffect(() => onDirtyChange(isDirty), [isDirty, onDirtyChange]);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        put('/admin/pengaturan', { preserveScroll: true, preserveState: true });
    }

    const absenceOn = features.notif_alpa_sholat === true;

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Bell className="size-5 text-purple-600" />
                        <CardTitle>Notifikasi Absensi</CardTitle>
                    </div>
                    <CardDescription>
                        Dikirim ke orang tua tiap siswa scan. Kanal yang dipakai (WhatsApp, Telegram, email)
                        mengikuti Gateway Notifikasi sekolah.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="whatsapp_enabled"
                            checked={data.whatsapp_enabled}
                            onCheckedChange={(checked) => setData('whatsapp_enabled', Boolean(checked))}
                        />
                        <Label htmlFor="whatsapp_enabled" className="cursor-pointer text-sm font-medium">
                            Aktifkan notifikasi absensi
                        </Label>
                    </div>

                    <Separator />

                    <div className="space-y-3 pl-7">
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="notify_on_check_in"
                                checked={data.notify_on_check_in}
                                onCheckedChange={(checked) => setData('notify_on_check_in', Boolean(checked))}
                                disabled={!data.whatsapp_enabled}
                            />
                            <Label htmlFor="notify_on_check_in" className="cursor-pointer text-sm font-medium">
                                Kirim notifikasi saat check-in
                            </Label>
                        </div>
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="notify_on_check_out"
                                checked={data.notify_on_check_out}
                                onCheckedChange={(checked) => setData('notify_on_check_out', Boolean(checked))}
                                disabled={!data.whatsapp_enabled}
                            />
                            <Label htmlFor="notify_on_check_out" className="cursor-pointer text-sm font-medium">
                                Kirim notifikasi saat check-out
                            </Label>
                        </div>
                    </div>

                    <Separator />

                    <div className="grid gap-2">
                        <Label htmlFor="whatsapp_template_attendance" className="text-sm font-medium">
                            Template Pesan Kehadiran
                        </Label>
                        <Textarea
                            id="whatsapp_template_attendance"
                            value={data.whatsapp_template_attendance}
                            onChange={(e) => setData('whatsapp_template_attendance', e.target.value)}
                            placeholder="Kosongkan untuk memakai template bawaan."
                            rows={4}
                            disabled={!data.whatsapp_enabled}
                        />
                        <p className="text-muted-foreground text-xs">Variabel: {ATTENDANCE_VARS}</p>
                        <InputError message={errors.whatsapp_template_attendance} />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <MoonStar className="size-5 text-amber-600" />
                        <CardTitle>Notifikasi Alpa Sholat</CardTitle>
                    </div>
                    <CardDescription>
                        {absenceOn
                            ? 'Peringatan otomatis ke orang tua bila siswa berturut-turut tidak ikut sholat.'
                            : 'Nyalakan dulu di tab Fitur untuk mengatur bagian ini.'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-2 sm:max-w-xs">
                        <Label htmlFor="prayer_absence_threshold" className="text-sm font-medium">
                            Ambang hari berturut-turut
                        </Label>
                        <Input
                            id="prayer_absence_threshold"
                            type="number"
                            min={2}
                            max={10}
                            value={data.prayer_absence_threshold}
                            onChange={(e) => setData('prayer_absence_threshold', Number(e.target.value))}
                            disabled={!absenceOn}
                        />
                        <p className="text-muted-foreground text-xs">
                            Dihitung dari hari sekolah, bukan hari kalender. Reset begitu siswa ikut sholat lagi.
                        </p>
                        <InputError message={errors.prayer_absence_threshold} />
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="prayer_absence_require_present"
                            className="mt-0.5"
                            checked={data.prayer_absence_require_present}
                            onCheckedChange={(checked) => setData('prayer_absence_require_present', Boolean(checked))}
                            disabled={!absenceOn}
                        />
                        <div className="grid gap-0.5">
                            <Label htmlFor="prayer_absence_require_present" className="cursor-pointer text-sm font-medium">
                                Abaikan hari siswa tidak masuk
                            </Label>
                            <p className="text-muted-foreground text-xs">
                                Sangat disarankan. Tanpa ini, anak yang sakit tiga hari membuat orang tuanya
                                menerima peringatan tidak sholat.
                            </p>
                        </div>
                    </div>

                    <Separator />

                    <div className="grid gap-2">
                        <Label htmlFor="whatsapp_template_prayer_absence" className="text-sm font-medium">
                            Template Pesan Alpa Sholat
                        </Label>
                        <Textarea
                            id="whatsapp_template_prayer_absence"
                            value={data.whatsapp_template_prayer_absence}
                            onChange={(e) => setData('whatsapp_template_prayer_absence', e.target.value)}
                            placeholder="Kosongkan untuk memakai template bawaan."
                            rows={5}
                            disabled={!absenceOn}
                        />
                        <p className="text-muted-foreground text-xs">Variabel: {ABSENCE_VARS}</p>
                        <InputError message={errors.whatsapp_template_prayer_absence} />
                    </div>
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing || !isDirty}>
                    <Save className="mr-2 size-4" />
                    Simpan Pengaturan Notifikasi
                </Button>
            </div>
        </form>
    );
}
