import { Head, router } from '@inertiajs/react';
import { ExternalLink, History, Loader2 } from 'lucide-react';
import { useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';

type SubmissionItem = {
    id: string;
    layout_name: string;
    status: string;
    card_url: string | null;
    created_at: string | null;
};

type Props = { submissions: SubmissionItem[] };

function formatDate(iso: string | null): string {
    if (!iso) return '-';
    return new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

export default function KartuBebasRiwayatIndex({ submissions }: Props) {
    const hasProcessing = submissions.some((s) => s.status === 'processing');

    useEffect(() => {
        if (!hasProcessing) {
            return;
        }

        let reloading = false;
        const interval = window.setInterval(() => {
            if (reloading) {
                return;
            }
            reloading = true;
            router.reload({
                only: ['submissions'],
                onFinish: () => {
                    reloading = false;
                },
            });
        }, 3000);

        return () => window.clearInterval(interval);
    }, [hasProcessing]);

    return (
        <>
            <Head title="Riwayat Kartu" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">Riwayat Kartu</h1>
                    <p className="text-muted-foreground text-sm">Daftar kartu yang telah berhasil dibuat (100 terbaru).</p>
                    {hasProcessing && (
                        <span className="text-muted-foreground mt-1 inline-flex items-center gap-1 text-xs">
                            <Loader2 className="size-3 animate-spin" /> memperbarui…
                        </span>
                    )}
                </div>

                {submissions.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <History className="mb-4 size-12 text-emerald-500/70" />
                            <p className="text-muted-foreground text-sm">Belum ada kartu yang dibuat.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Layout</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Kartu</TableHead>
                                        <TableHead>Tanggal</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {submissions.map((s) => (
                                        <TableRow key={s.id}>
                                            <TableCell className="font-medium">{s.layout_name}</TableCell>
                                            <TableCell>
                                                {s.status === 'processing' ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="gap-1 border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-900 dark:text-amber-300"
                                                    >
                                                        <Loader2 className="size-3 animate-spin" /> Proses
                                                    </Badge>
                                                ) : s.status === 'failed' ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-red-200 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-900 dark:text-red-300"
                                                    >
                                                        Gagal
                                                    </Badge>
                                                ) : (
                                                    <Badge className="bg-emerald-600 text-white">Selesai</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {s.card_url ? (
                                                    <a
                                                        href={s.card_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1 text-emerald-600 hover:underline dark:text-emerald-400"
                                                    >
                                                        Lihat <ExternalLink className="size-3" />
                                                    </a>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{formatDate(s.created_at)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

KartuBebasRiwayatIndex.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/kartu-bebas' },
            { title: 'Riwayat Kartu', href: '/kartu-bebas/riwayat' },
        ]}
    >
        {page}
    </KartuBebasLayout>
);
