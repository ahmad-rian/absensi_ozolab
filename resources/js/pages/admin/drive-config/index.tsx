import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Cloud, FolderOpen, Loader2, Save, TestTube2, XCircle } from 'lucide-react';
import { type FormEvent } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type DriveConfig = {
    id: string;
    root_folder_id: string | null;
    cards_folder_id: string | null;
    albums_folder_id: string | null;
    parents_folder_id: string | null;
    is_active: boolean;
    last_tested_at: string | null;
} | null;

type Props = {
    driveConfig: DriveConfig;
    hasGlobalCredentials: boolean;
};

export default function DriveConfigIndex({ driveConfig, hasGlobalCredentials }: Props) {
    const { data, setData, post, processing } = useForm({
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

    // Extract folder ID from full Drive URL or plain ID
    function parseFolderId(input: string): string {
        const match = input.match(/folders\/([a-zA-Z0-9_-]+)/);
        return match ? match[1] : input.trim();
    }

    const folders = [
        {
            key: 'cards_folder_id' as const,
            label: 'Folder Kartu Siswa',
            desc: 'Hasil generate kartu OSIS, perpustakaan, dan identitas.',
            icon: '🪪',
        },
        {
            key: 'albums_folder_id' as const,
            label: 'Folder Album Foto',
            desc: 'Hasil generate album foto per kelas.',
            icon: '📸',
        },
        {
            key: 'parents_folder_id' as const,
            label: 'Folder Orang Tua',
            desc: 'Folder yang di-share ke orang tua untuk download.',
            icon: '👨‍👩‍👧‍👦',
        },
    ];

    return (
        <>
            <Head title="Google Drive" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Google Drive</h1>
                    <p className="text-muted-foreground text-sm">
                        Hubungkan folder Google Drive untuk menyimpan kartu siswa, album foto, dan berbagi ke orang tua.
                    </p>
                </div>

                {/* Global credential status */}
                {!hasGlobalCredentials && (
                    <Alert className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                        <AlertTriangle className="size-4 text-amber-600" />
                        <AlertDescription className="text-amber-800 dark:text-amber-200">
                            Service account Google Drive belum dikonfigurasi oleh Super Admin. Hubungi administrator untuk setup credential di server.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Status Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Cloud className="size-4" />
                            Status
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-center gap-4">
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground text-sm">Credential:</span>
                                {hasGlobalCredentials ? (
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
                        {driveConfig && (
                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                {folders.map((f) => {
                                    const value = driveConfig?.[f.key];
                                    return (
                                        <div
                                            key={f.key}
                                            className={`rounded-lg border p-3 ${value ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950' : 'border-dashed'}`}
                                        >
                                            <div className="mb-1 flex items-center gap-2">
                                                <span>{f.icon}</span>
                                                <span className="text-sm font-medium">{f.label}</span>
                                            </div>
                                            {value ? (
                                                <code className="text-muted-foreground block truncate text-xs">{value}</code>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">Belum dikonfigurasi</span>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Folder Config Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <FolderOpen className="size-4" />
                                Konfigurasi Folder
                            </CardTitle>
                            <CardDescription>
                                Paste link folder Google Drive atau Folder ID. Pastikan folder sudah di-share ke service account.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="rounded-lg border p-4">
                                <div className="mb-3">
                                    <Label className="text-sm font-semibold">Root Folder (opsional)</Label>
                                    <p className="text-muted-foreground text-xs">Folder induk yang menampung semua subfolder sekolah ini.</p>
                                </div>
                                <Input
                                    value={data.root_folder_id}
                                    onChange={(e) => setData('root_folder_id', parseFolderId(e.target.value))}
                                    placeholder="Paste link atau ID folder..."
                                />
                            </div>

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
                                        onChange={(e) => setData(f.key, parseFolderId(e.target.value))}
                                        placeholder="Paste link Google Drive atau Folder ID..."
                                    />
                                </div>
                            ))}

                            <p className="text-muted-foreground text-xs">
                                Tip: paste langsung link folder (contoh: <code>https://drive.google.com/drive/folders/abc123xyz</code>) — ID otomatis terdeteksi.
                            </p>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                                />
                                <Label htmlFor="is_active">Aktifkan Google Drive untuk sekolah ini</Label>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing} className="gap-2">
                            {processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                            Simpan
                        </Button>

                        {hasGlobalCredentials && driveConfig && (
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
                        <CardTitle className="text-base">Cara Setup</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="text-muted-foreground list-inside list-decimal space-y-2 text-sm">
                            <li>Buat 3 folder di Google Drive (Kartu Siswa, Album Foto, Orang Tua)</li>
                            <li><b>Share</b> ketiga folder ke email service account (minta ke Super Admin) dengan akses <b>Editor</b></li>
                            <li>Buka folder → copy <b>link</b> atau <b>Folder ID</b> dari URL</li>
                            <li>Paste link di form di atas — ID otomatis diambil dari URL</li>
                            <li>Centang <b>Aktifkan</b> lalu klik <b>Simpan</b></li>
                            <li>Klik <b>Test Koneksi</b> untuk memastikan terhubung</li>
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
