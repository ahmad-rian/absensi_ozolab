import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dashboard } from '@/routes';
import type { ParentProfile } from '@/types';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedParents = {
    data: ParentProfile[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type Filters = {
    search: string | null;
};

type PageProps = {
    parents: PaginatedParents;
    filters: Filters;
};

function relationLabel(relation: string): string {
    const labels: Record<string, string> = {
        AYAH: 'Ayah',
        IBU: 'Ibu',
        WALI: 'Wali',
    };
    return labels[relation] ?? relation;
}

export default function OrangTuaIndex({ parents, filters }: PageProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    function handleSearch(value: string) {
        setSearch(value);
        router.get(
            '/admin/orang-tua',
            { search: value || undefined },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Data Orang Tua" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Data Orang Tua</h1>
                        <p className="text-muted-foreground text-sm">Kelola data orang tua/wali dan nomor WhatsApp.</p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/orang-tua/create">
                            <Plus className="mr-2 size-4" />
                            Tambah Orang Tua
                        </Link>
                    </Button>
                </div>

                {/* Search */}
                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            placeholder="Cari nama atau nomor WhatsApp..."
                            value={search}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                </div>

                {/* Table */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>WhatsApp</TableHead>
                                <TableHead>Hubungan</TableHead>
                                <TableHead>Jumlah Anak</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {parents.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        Tidak ada data orang tua ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                parents.data.map((parent) => (
                                    <TableRow key={parent.id}>
                                        <TableCell className="font-medium">{parent.user?.name ?? '-'}</TableCell>
                                        <TableCell>{parent.user?.email ?? '-'}</TableCell>
                                        <TableCell>{parent.whatsapp_number}</TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">{relationLabel(parent.relation)}</Badge>
                                        </TableCell>
                                        <TableCell>{parent.students?.length ?? 0} anak</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={`/admin/orang-tua/${parent.id}`}>
                                                        <Eye className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={`/admin/orang-tua/${parent.id}/edit`}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button variant="ghost" size="icon">
                                                            <Trash2 className="size-4 text-destructive" />
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>Hapus Orang Tua</AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                Apakah Anda yakin ingin menghapus data orang tua{' '}
                                                                <strong>{parent.user?.name}</strong>? Tindakan ini tidak dapat dibatalkan.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>Batal</AlertDialogCancel>
                                                            <AlertDialogAction
                                                                onClick={() => router.delete(`/admin/orang-tua/${parent.id}`)}
                                                                className="bg-destructive text-white hover:bg-destructive/90"
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
                </div>

                {/* Pagination */}
                {parents.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-muted-foreground text-sm">
                            Menampilkan {parents.from} - {parents.to} dari {parents.total} data
                        </p>
                        <div className="flex gap-1">
                            {parents.links.map((link, index) => (
                                <Button
                                    key={index}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    onClick={() => {
                                        if (link.url) {
                                            router.get(link.url, {}, { preserveState: true });
                                        }
                                    }}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

OrangTuaIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Orang Tua', href: '/admin/orang-tua' },
    ],
};
