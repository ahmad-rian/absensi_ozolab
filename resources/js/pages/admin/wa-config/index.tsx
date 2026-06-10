import { Head } from '@inertiajs/react';
import { CheckCircle2, Info, MessageSquare, Send, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type Props = {
    status: {
        ozolab_wa: boolean;
        fonnte_wa: { is_active: boolean; display_phone: string; has_token: boolean };
        telegram: { is_active: boolean; has_token: boolean };
    };
};

function StatusBadge({ active }: { active: boolean }) {
    return active ? (
        <Badge className="bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200">
            <CheckCircle2 className="mr-1 size-3" /> Aktif
        </Badge>
    ) : (
        <Badge variant="secondary">
            <XCircle className="mr-1 size-3" /> Nonaktif
        </Badge>
    );
}

export default function WaConfigIndex({ status }: Props) {
    return (
        <>
            <Head title="Status Notifikasi" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Status Notifikasi</h1>
                    <p className="text-muted-foreground text-sm">Channel notifikasi absensi untuk sekolah Anda.</p>
                </div>

                <div className="rounded-lg border bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-950 dark:text-blue-200">
                    <div className="flex gap-2">
                        <Info className="size-5 shrink-0" />
                        <p>
                            Konfigurasi gateway (token Fonnte / bot Telegram) diatur oleh <strong>Super Admin</strong>. Hubungi Super Admin untuk
                            mengubah channel yang aktif.
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <MessageSquare className="size-4" /> WhatsApp Ozolab
                                </CardTitle>
                                <StatusBadge active={status.ozolab_wa} />
                            </div>
                            <CardDescription>Gateway default Ozolab ID.</CardDescription>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <MessageSquare className="size-4" /> WhatsApp Fonnte
                                </CardTitle>
                                <StatusBadge active={status.fonnte_wa.is_active} />
                            </div>
                            <CardDescription>{status.fonnte_wa.display_phone || 'Nomor khusus sekolah'}</CardDescription>
                        </CardHeader>
                        <CardContent className="text-muted-foreground text-sm">
                            Token: {status.fonnte_wa.has_token ? 'terpasang' : 'belum diisi'}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Send className="size-4" /> Telegram
                                </CardTitle>
                                <StatusBadge active={status.telegram.is_active} />
                            </div>
                            <CardDescription>Push ke chat_id orang tua.</CardDescription>
                        </CardHeader>
                        <CardContent className="text-muted-foreground text-sm">
                            Bot token: {status.telegram.has_token ? 'terpasang' : 'belum diisi'}
                        </CardContent>
                    </Card>
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle className="text-base">Catatan</CardTitle>
                    </CardHeader>
                    <CardContent className="text-muted-foreground space-y-2 text-sm">
                        <p>• Notifikasi absensi terkirim otomatis ke orang tua tiap siswa check-in / check-out.</p>
                        <p>• Untuk Telegram, pastikan tiap orang tua sudah mengisi Telegram Chat ID di menu Orang Tua.</p>
                        <p>• Aktif/nonaktif global & template pesan diatur di menu Pengaturan.</p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

WaConfigIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Status Notifikasi', href: '/admin/wa-config' },
    ],
};
