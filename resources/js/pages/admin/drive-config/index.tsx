import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Cloud, FolderOpen, Key, Loader2, Save, TestTube2, XCircle } from 'lucide-react';
import { type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';

type DriveConfig = {
    id: number;
    root_folder_id: string | null;
    cards_folder_id: string | null;
    albums_folder_id: string | null;
    parents_folder_id: string | null;
    is_active: boolean;
    has_credentials: boolean;
    last_tested_at: string | null;
} | null;

type Props = {
    driveConfig: DriveConfig;
};

export default function DriveConfigIndex({ driveConfig }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        service_account_json: '',
        root_folder_id: driveConfig?.root_folder_id || '',
        cards_folder_id: driveConfig?.cards_folder_id || '',
        albums_folder_id: driveConfig?.albums_folder_id || '',
        parents_folder_id: driveConfig?.parents_folder_id || '',
        is_active: driveConfig?.is_active || false,
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post('/admin/drive-config', { preserveScroll: true });
    }

    function handleTest() {
        router.post('/admin/drive-config/test', {}, { preserveScroll: true });
    }

    const folders = [
        {
            key: 'cards_folder_id' as const,
            label: 'Folder Kartu Siswa',
            desc: 'Hasil generate kartu OSIS, perpustakaan, dan identitas disimpan di folder ini.',
            icon: '🪪',
            value: driveConfig?.cards_folder_id,
        },
        {
            key: 'albums_folder_id' as const,
            label: 'Folder Album Foto',
            desc: 'Hasil generate album foto per kelas disimpan di folder ini.',
            icon: '📸',
            value: driveConfig?.albums_folder_id,
        },
        {
            key: 'parents_folder_id' as const,
            label: 'Folder Orang Tua',
            desc: 'Folder yang bisa diakses orang tua untuk mengambil kartu/foto anaknya.',
            icon: '👨‍👩‍👧‍👦',
            value: driveConfig?.parents_folder_id,
        },
    ];

    return (
        <>
            <Head title="Google Drive Config" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Konfigurasi Google Drive</h1>
                    <p className="text-muted-foreground text-sm">
                        Hubungkan Google Drive untuk menyimpan kartu siswa, album foto, dan berbagi ke orang tua.
                    </p>
                </div>

                {/* Status Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Cloud className="size-4" />
                            Status Koneksi
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-center gap-4">
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground text-sm">Credential:</span>
                                {driveConfig?.has_credentials ? (
                                    <Badge variant="default" className="bg-green-600">
                                        <CheckCircle2 className="mr-1 size-3" /> Tersedia
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary">
                                        <XCircle className="mr-1 size-3" /> Belum diset
                                    </Badge>
                                )}
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground text-sm">Status:</span>
                                {driveConfig?.is_active ? (
                                    <Badge variant="default" className="bg-green-600">Aktif</Badge>
                                ) : (
                                    <Badge variant="secondary">Nonaktif</Badge>
                                )}
                            </div>
                            {driveConfig?.last_tested_at && (
                                <div className="flex items-center gap-2">
                                    <span className="text-muted-foreground text-sm">Terakhir ditest:</span>
                                    <span className="text-sm font-medium">{driveConfig.last_tested_at}</span>
                                </div>
                            )}
                        </div>

                        {/* Folder status grid */}
                        {driveConfig?.has_credentials && (
                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                {folders.map((f) => (
                                    <div
                                        key={f.key}
                                        className={`rounded-lg border p-3 ${f.value ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950' : 'border-dashed'}`}
                                    >
                                        <div className="mb-1 flex items-center gap-2">
                                            <span>{f.icon}</span>
                                            <span className="text-sm font-medium">{f.label}</span>
                                        </div>
                                        {f.value ? (
                                            <code className="text-muted-foreground block truncate text-xs">{f.value}</code>
                                        ) : (
                                            <span className="text-muted-foreground text-xs">Belum dikonfigurasi</span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Configuration Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Credential */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Key className="size-4" />
                                Service Account Credential
                            </CardTitle>
                            <CardDescription>
                                Paste JSON credential dari Google Cloud Console. Satu service account digunakan untuk semua folder.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="service_account_json">
                                    Service Account JSON
                                    {driveConfig?.has_credentials && (
                                        <span className="text-muted-foreground ml-2 text-xs font-normal">
                                            (sudah tersimpan — kosongkan jika tidak ingin mengubah)
                                        </span>
                                    )}
                                </Label>
                                <Textarea
                                    id="service_account_json"
                                    value={data.service_account_json}
                                    onChange={(e) => setData('service_account_json', e.target.value)}
                                    placeholder='{"type": "service_account", "project_id": "...", ...}'
                                    rows={6}
                                    className="font-mono text-xs"
                                />
                                {errors.service_account_json && (
                                    <p className="text-sm text-red-500">{errors.service_account_json}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="root_folder_id">Root Folder ID (opsional)</Label>
                                <Input
                                    id="root_folder_id"
                                    value={data.root_folder_id}
                                    onChange={(e) => setData('root_folder_id', e.target.value)}
                                    placeholder="ID folder induk di Google Drive"
                                />
                                <p className="text-muted-foreground text-xs">
                                    Folder induk yang menampung semua subfolder. Bisa dikosongkan jika 3 folder di bawah sudah diisi langsung.
                                </p>
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                                />
                                <Label htmlFor="is_active">Aktifkan Google Drive</Label>
                            </div>
                        </CardContent>
                    </Card>

                    {/* 3 Folder Configs */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <FolderOpen className="size-4" />
                                Konfigurasi Folder
                            </CardTitle>
                            <CardDescription>
                                Buat 3 folder terpisah di Google Drive, share ke email service account, lalu isi Folder ID masing-masing.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {folders.map((f) => (
                                <div key={f.key} className="rounded-lg border p-4">
                                    <div className="mb-3 flex items-center gap-2">
                                        <span className="text-lg">{f.icon}</span>
                                        <div>
                                            <Label className="text-sm font-semibold">{f.label}</Label>
                                            <p className="text-muted-foreground text-xs">{f.desc}</p>
                                        </div>
                                    </div>
                                    <Input
                                        value={data[f.key]}
                                        onChange={(e) => setData(f.key, e.target.value)}
                                        placeholder={`Folder ID untuk ${f.label.toLowerCase()}`}
                                    />
                                </div>
                            ))}

                            <p className="text-muted-foreground text-xs">
                                Cara mendapatkan Folder ID: buka folder di Google Drive, copy ID dari URL → <code>drive.google.com/drive/folders/<b>[ID_INI]</b></code>
                            </p>
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing} className="gap-2">
                            {processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                            Simpan Konfigurasi
                        </Button>

                        {driveConfig?.has_credentials && (
                            <Button type="button" variant="outline" onClick={handleTest} className="gap-2">
                                <TestTube2 className="size-4" />
                                Test Koneksi
                            </Button>
                        )}
                    </div>
                </form>

                {/* Setup Guide */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Panduan Setup Google Drive</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="text-muted-foreground list-inside list-decimal space-y-2 text-sm">
                            <li>Buka <b>Google Cloud Console</b> dan buat project baru (atau gunakan yang sudah ada)</li>
                            <li>Aktifkan <b>Google Drive API</b> di menu APIs & Services</li>
                            <li>Buat <b>Service Account</b> di menu IAM & Admin → Service Accounts</li>
                            <li>Generate <b>JSON key</b> untuk service account tersebut</li>
                            <li>
                                Buat <b>3 folder</b> di Google Drive:
                                <ul className="ml-6 mt-1 list-disc space-y-1">
                                    <li><b>Kartu Siswa</b> — untuk hasil generate kartu OSIS/perpustakaan</li>
                                    <li><b>Album Foto</b> — untuk hasil generate album per kelas</li>
                                    <li><b>Orang Tua</b> — folder yang di-share ke orang tua untuk download</li>
                                </ul>
                            </li>
                            <li><b>Share</b> ketiga folder tersebut ke email service account (dengan akses Editor)</li>
                            <li>Copy <b>Folder ID</b> dari masing-masing folder dan paste di form di atas</li>
                            <li>Paste isi file JSON key ke field credential</li>
                            <li>Klik <b>Simpan</b> lalu <b>Test Koneksi</b></li>
                        </ol>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DriveConfigIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Google Drive', href: '/admin/drive-config' },
    ],
};
