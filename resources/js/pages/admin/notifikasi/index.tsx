import { Head, router, usePage } from '@inertiajs/react';
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
};

type PageProps = {
    notifications: Paginated<NotificationLog>;
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
    const { notifications, filters } = usePage<PageProps>().props;

    function applyFilter(key: string, value: string) {
        router.get(
            '/admin/notifikasi',
            { ...filters, [key]: value, page: undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Log Notifikasi WhatsApp" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                {/* Page Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Log Notifikasi WhatsApp</h1>
                    <p className="text-muted-foreground text-sm">Pantau status pengiriman notifikasi WhatsApp ke orang tua.</p>
                </div>

                {/* Filter Bar */}
                <Card>
                    <CardContent className="pt-0">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {notifications.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-muted-foreground py-8 text-center">
                                            Tidak ada data notifikasi.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    notifications.data.map((n) => (
                                        <TableRow key={n.id}>
                                            <TableCell>{formatDateTime(n.created_at)}</TableCell>
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
