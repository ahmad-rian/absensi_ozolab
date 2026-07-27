import { Head, router, usePage } from '@inertiajs/react';
import { CheckCheck, Trash2 } from 'lucide-react';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dashboard } from '@/routes';

type ParentProfile = {
    id: string;
    whatsapp_number: string | null;
    user: {
        id: string;
        name: string;
    } | null;
};

type Student = {
    id: string;
    full_name: string;
};

type NotificationLog = {
    id: string;
    created_at: string;
    whatsapp_number: string | null;
    status: string;
    attempt_count: number;
    error_message: string | null;
    read_at: string | null;
    student: Student | null;
    parent_profile: ParentProfile | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
};

type Filters = {
    status: string;
    date_from: string;
    date_to: string;
    unread: string;
};

type PageProps = {
    notifications: Paginated<NotificationLog>;
    unreadCount: number;
    filters: Filters;
};

const statusConfig: Record<string, { label: string; className: string }> = {
    SENT: { label: 'Terkirim', className: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' },
    FAILED: { label: 'Gagal', className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' },
    PENDING: { label: 'Menunggu', className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' },
};

function formatDateTime(dateStr: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(dateStr));
}

export default function NotifikasiIndex() {
    const { notifications, unreadCount, filters } = usePage<PageProps>().props;

    function applyFilter(key: string, value: string) {
        router.get(
            '/admin/notifikasi',
            { ...filters, [key]: value, page: undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    // `notifications` ikut di-reload supaya badge di sidebar (prop `notifications`
    // yang di-share) dan tabelnya sama-sama segar.
    const refresh = { preserveScroll: true };

    return (
        <>
            <Head title="Log Notifikasi" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                {/* Page Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            Log Notifikasi
                            {unreadCount > 0 && (
                                <Badge variant="default">{unreadCount > 99 ? '99+' : unreadCount} belum dibaca</Badge>
                            )}
                        </h1>
                        <p className="text-muted-foreground text-sm">Pantau status pengiriman notifikasi ke orang tua.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={unreadCount === 0}
                            onClick={() => router.post('/admin/notifikasi/baca-semua', {}, refresh)}
                        >
                            <CheckCheck className="mr-1.5 size-4" />
                            Tandai semua dibaca
                        </Button>
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <Trash2 className="mr-1.5 size-4" />
                                    Hapus yang sudah dibaca
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Hapus notifikasi terbaca</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        Semua log yang sudah ditandai dibaca akan dihapus permanen. Log yang belum dibaca tetap aman.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Batal</AlertDialogCancel>
                                    <AlertDialogAction
                                        className="bg-destructive text-white hover:bg-destructive/90"
                                        onClick={() => router.delete('/admin/notifikasi/terbaca', refresh)}
                                    >
                                        Hapus
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>

                {/* Filter Bar */}
                <Card>
                    <CardContent className="pt-0">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                            <div className="space-y-1.5">
                                <Label>Status</Label>
                                <Select
                                    value={filters.status}
                                    onValueChange={(val) => applyFilter('status', val === 'all' ? '' : val)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Semua Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Status</SelectItem>
                                        <SelectItem value="SENT">Terkirim</SelectItem>
                                        <SelectItem value="FAILED">Gagal</SelectItem>
                                        <SelectItem value="PENDING">Menunggu</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label>Dari Tanggal</Label>
                                <Input
                                    type="date"
                                    value={filters.date_from}
                                    onChange={(e) => applyFilter('date_from', e.target.value)}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Sampai Tanggal</Label>
                                <Input
                                    type="date"
                                    value={filters.date_to}
                                    onChange={(e) => applyFilter('date_to', e.target.value)}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Tampilkan</Label>
                                <Select
                                    value={filters.unread === '1' ? 'unread' : 'all'}
                                    onValueChange={(val) => applyFilter('unread', val === 'unread' ? '1' : '')}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua</SelectItem>
                                        <SelectItem value="unread">Belum dibaca</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Data Table */}
                <Card>
                    <CardContent className="pt-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Siswa</TableHead>
                                    <TableHead>Orang Tua</TableHead>
                                    <TableHead>No. WhatsApp</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Percobaan</TableHead>
                                    <TableHead>Error</TableHead>
                                    <TableHead className="w-12" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {notifications.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={8} className="text-muted-foreground py-8 text-center">
                                            Tidak ada data notifikasi.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    notifications.data.map((n) => (
                                        <TableRow key={n.id} className={n.read_at ? undefined : 'bg-primary/5'}>
                                            <TableCell>
                                                <span className="flex items-center gap-2">
                                                    {!n.read_at && (
                                                        <span
                                                            className="bg-primary size-2 shrink-0 rounded-full"
                                                            title="Belum dibaca"
                                                        />
                                                    )}
                                                    {formatDateTime(n.created_at)}
                                                </span>
                                            </TableCell>
                                            <TableCell className="font-medium">{n.student?.full_name ?? '-'}</TableCell>
                                            <TableCell>{n.parent_profile?.user?.name ?? '-'}</TableCell>
                                            <TableCell>{n.whatsapp_number ?? '-'}</TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className={statusConfig[n.status]?.className}>
                                                    {statusConfig[n.status]?.label ?? n.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>{n.attempt_count}</TableCell>
                                            <TableCell className="text-muted-foreground max-w-[200px] truncate">
                                                {n.error_message ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex justify-end gap-1">
                                                    {!n.read_at && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            title="Tandai dibaca"
                                                            onClick={() => router.post(`/admin/notifikasi/${n.id}/baca`, {}, refresh)}
                                                        >
                                                            <CheckCheck className="size-4" />
                                                        </Button>
                                                    )}
                                                    <AlertDialog>
                                                        <AlertDialogTrigger asChild>
                                                            <Button variant="ghost" size="icon" title="Hapus">
                                                                <Trash2 className="text-destructive size-4" />
                                                            </Button>
                                                        </AlertDialogTrigger>
                                                        <AlertDialogContent>
                                                            <AlertDialogHeader>
                                                                <AlertDialogTitle>Hapus notifikasi</AlertDialogTitle>
                                                                <AlertDialogDescription>
                                                                    Log untuk {n.student?.full_name ?? 'siswa ini'} akan dihapus permanen.
                                                                </AlertDialogDescription>
                                                            </AlertDialogHeader>
                                                            <AlertDialogFooter>
                                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                                <AlertDialogAction
                                                                    className="bg-destructive text-white hover:bg-destructive/90"
                                                                    onClick={() => router.delete(`/admin/notifikasi/${n.id}`, refresh)}
                                                                >
                                                                    Hapus
                                                                </AlertDialogAction>
                                                            </AlertDialogFooter>
                                                        </AlertDialogContent>
                                                    </AlertDialog>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>

                        {/* Pagination */}
                        {notifications.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-center gap-1">
                                {notifications.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() => {
                                            if (link.url) {
                                                router.get(link.url, {}, { preserveState: true, preserveScroll: true });
                                            }
                                        }}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

NotifikasiIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Notifikasi', href: '/admin/notifikasi' },
    ],
};
