import { Head, router, useForm } from '@inertiajs/react';
import { Bell, Building2, Clock, ImageIcon, Save, Upload } from 'lucide-react';
import { type FormEvent, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

type SettingsData = {
    school_name: string;
    default_check_in_time: string;
    late_threshold_time: string;
    default_check_out_time: string;
    timezone: string;
    whatsapp_enabled: boolean;
    notify_on_check_in: boolean;
    notify_on_check_out: boolean;
    whatsapp_template_attendance: string;
};

type Props = {
    settings: Record<string, string | boolean>;
    logoUrl: string;
    faviconUrl: string;
};

export default function PengaturanIndex({ settings, logoUrl, faviconUrl }: Props) {
    const logoInputRef = useRef<HTMLInputElement>(null);
    const faviconInputRef = useRef<HTMLInputElement>(null);

    const { data, setData, put, processing } = useForm<SettingsData>({
        school_name: (settings.school_name as string) || '',
        default_check_in_time: (settings.default_check_in_time as string) || '07:00',
        late_threshold_time: (settings.late_threshold_time as string) || '07:15',
        default_check_out_time: (settings.default_check_out_time as string) || '15:00',
        timezone: (settings.timezone as string) || 'Asia/Jakarta',
        whatsapp_enabled: Boolean(settings.whatsapp_enabled),
        notify_on_check_in: Boolean(settings.notify_on_check_in),
        notify_on_check_out: Boolean(settings.notify_on_check_out),
        whatsapp_template_attendance: (settings.whatsapp_template_attendance as string) || '',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        put('/admin/pengaturan', { preserveScroll: true });
    }

    function handleLogoUpload(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        router.post('/admin/pengaturan/upload-logo', { logo: file }, { preserveScroll: true });
    }

    function handleFaviconUpload(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        router.post('/admin/pengaturan/upload-favicon', { favicon: file }, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Pengaturan Situs" />

            <form onSubmit={handleSubmit} className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Pengaturan Situs</h1>
                        <p className="text-muted-foreground text-sm">Kelola konfigurasi aplikasi absensi</p>
                    </div>
                    <Button type="submit" disabled={processing}>
                        <Save className="mr-2 size-4" />
                        Simpan
                    </Button>
                </div>

                {/* Card 1: Informasi Situs */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Building2 className="size-5 text-blue-600" />
                            <CardTitle>Informasi Situs</CardTitle>
                        </div>
                        <CardDescription>Nama dan identitas situs yang ditampilkan di aplikasi.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="school_name" className="text-sm font-medium">Nama Situs</Label>
                            <Input
                                id="school_name"
                                value={data.school_name}
                                onChange={(e) => setData('school_name', e.target.value)}
                                placeholder="Masukkan nama situs"
                            />
                        </div>

                        <Separator />

                        {/* Logo Upload */}
                        <div className="grid gap-2">
                            <Label className="text-sm font-medium">Logo Aplikasi</Label>
                            <div className="flex items-center gap-4">
                                {logoUrl ? (
                                    <div className="flex size-20 items-center justify-center overflow-hidden rounded-lg border bg-white p-1">
                                        <img src={logoUrl} alt="Logo" className="max-h-full max-w-full object-contain" />
                                    </div>
                                ) : (
                                    <div className="text-muted-foreground flex size-20 items-center justify-center rounded-lg border bg-muted">
                                        <ImageIcon className="size-8 opacity-40" />
                                    </div>
                                )}
                                <div className="space-y-2">
                                    <input
                                        ref={logoInputRef}
                                        type="file"
                                        accept="image/*"
                                        className="hidden"
                                        onChange={handleLogoUpload}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => logoInputRef.current?.click()}
                                    >
                                        <Upload className="mr-2 size-4" />
                                        Upload Logo
                                    </Button>
                                    <p className="text-muted-foreground text-xs">Format: JPG, PNG, WebP. Maks 2MB.</p>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        {/* Favicon Upload */}
                        <div className="grid gap-2">
                            <Label className="text-sm font-medium">Favicon Aplikasi</Label>
                            <div className="flex items-center gap-4">
                                {faviconUrl ? (
                                    <div className="flex size-20 items-center justify-center overflow-hidden rounded-lg border bg-white p-1">
                                        <img src={faviconUrl} alt="Favicon" className="max-h-full max-w-full object-contain" />
                                    </div>
                                ) : (
                                    <div className="text-muted-foreground flex size-20 items-center justify-center rounded-lg border bg-muted">
                                        <ImageIcon className="size-8 opacity-40" />
                                    </div>
                                )}
                                <div className="space-y-2">
                                    <input
                                        ref={faviconInputRef}
                                        type="file"
                                        accept="image/*"
                                        className="hidden"
                                        onChange={handleFaviconUpload}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => faviconInputRef.current?.click()}
                                    >
                                        <Upload className="mr-2 size-4" />
                                        Upload Favicon
                                    </Button>
                                    <p className="text-muted-foreground text-xs">Format: JPG, PNG, WebP. Maks 2MB.</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Card 2: Konfigurasi Absensi */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Clock className="size-5 text-green-600" />
                            <CardTitle>Konfigurasi Absensi</CardTitle>
                        </div>
                        <CardDescription>Atur jam masuk, batas terlambat, dan jam pulang default.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="default_check_in_time" className="text-sm font-medium">Jam Masuk Default</Label>
                                <Input
                                    id="default_check_in_time"
                                    type="time"
                                    value={data.default_check_in_time}
                                    onChange={(e) => setData('default_check_in_time', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="late_threshold_time" className="text-sm font-medium">Batas Waktu Terlambat</Label>
                                <Input
                                    id="late_threshold_time"
                                    type="time"
                                    value={data.late_threshold_time}
                                    onChange={(e) => setData('late_threshold_time', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="default_check_out_time" className="text-sm font-medium">Jam Pulang Default</Label>
                                <Input
                                    id="default_check_out_time"
                                    type="time"
                                    value={data.default_check_out_time}
                                    onChange={(e) => setData('default_check_out_time', e.target.value)}
                                />
                            </div>
                        </div>
                        <Separator />
                        <div className="grid gap-2">
                            <Label htmlFor="timezone" className="text-sm font-medium">Zona Waktu</Label>
                            <Select value={data.timezone} onValueChange={(val) => setData('timezone', val)}>
                                <SelectTrigger className="w-64">
                                    <SelectValue placeholder="Pilih zona waktu" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Asia/Jakarta">Asia/Jakarta (WIB)</SelectItem>
                                    <SelectItem value="Asia/Makassar">Asia/Makassar (WITA)</SelectItem>
                                    <SelectItem value="Asia/Jayapura">Asia/Jayapura (WIT)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* Card 3: Konfigurasi WhatsApp */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Bell className="size-5 text-purple-600" />
                            <CardTitle>Konfigurasi WhatsApp</CardTitle>
                        </div>
                        <CardDescription>Atur notifikasi WhatsApp yang dikirim ke orang tua.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="whatsapp_enabled"
                                checked={data.whatsapp_enabled}
                                onCheckedChange={(checked) => setData('whatsapp_enabled', Boolean(checked))}
                            />
                            <Label htmlFor="whatsapp_enabled" className="cursor-pointer text-sm font-medium">
                                Aktifkan Notifikasi WhatsApp
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
                                placeholder="Contoh: Yth. {parent_name}, anak Anda {student_name} telah {status} pada {date} pukul {time}."
                                rows={4}
                                disabled={!data.whatsapp_enabled}
                            />
                            <p className="text-muted-foreground text-xs">
                                Variabel yang tersedia: {'{parent_name}'}, {'{student_name}'}, {'{status}'}, {'{date}'}, {'{time}'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Submit Button */}
                <div className="flex justify-end">
                    <Button type="submit" disabled={processing} className="gap-2">
                        <Save className="size-4" />
                        Simpan Pengaturan
                    </Button>
                </div>
            </form>
        </>
    );
}

PengaturanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengaturan', href: '/admin/pengaturan' },
    ],
};
