import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dashboard } from '@/routes';
import type { ParentProfile } from '@/types';

type PageProps = {
    parent: ParentProfile;
};

function relationLabel(relation: string): string {
    const labels: Record<string, string> = {
        AYAH: 'Ayah',
        IBU: 'Ibu',
        WALI: 'Wali',
    };
    return labels[relation] ?? relation;
}

export default function OrangTuaShow({ parent }: PageProps) {
    return (
        <>
            <Head title={`Detail Orang Tua - ${parent.user?.name ?? ''}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/admin/orang-tua">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Detail Orang Tua</h1>
                        <p className="text-muted-foreground text-sm">Informasi lengkap orang tua/wali.</p>
                    </div>
                </div>

                {/* Parent Info Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Orang Tua</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground text-sm font-medium">Nama</dt>
                                <dd className="mt-1 text-sm">{parent.user?.name ?? '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm font-medium">Email</dt>
                                <dd className="mt-1 text-sm">{parent.user?.email ?? '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm font-medium">Nomor WhatsApp</dt>
                                <dd className="mt-1 text-sm">{parent.whatsapp_number}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm font-medium">Hubungan</dt>
                                <dd className="mt-1">
                                    <Badge variant="secondary">{relationLabel(parent.relation)}</Badge>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm font-medium">NIK</dt>
                                <dd className="mt-1 text-sm">{parent.nik ?? '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm font-medium">Pekerjaan</dt>
                                <dd className="mt-1 text-sm">{parent.occupation ?? '-'}</dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-muted-foreground text-sm font-medium">Alamat</dt>
                                <dd className="mt-1 text-sm">
                                    {parent.address ?? '-'}
                                    {parent.city ? `, ${parent.city}` : ''}
                                </dd>
                            </div>
                            {parent.created_at && (
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Terdaftar Sejak</dt>
                                    <dd className="mt-1 flex items-center gap-1.5 text-sm">
                                        <Calendar className="text-muted-foreground size-3.5" />
                                        {new Date(parent.created_at).toLocaleDateString('id-ID', {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </CardContent>
                </Card>

                {/* Children Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Anak</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>NIS</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Kelas</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {(!parent.students || parent.students.length === 0) ? (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-muted-foreground py-8 text-center">
                                                Belum ada data anak.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        parent.students.map((student) => (
                                            <TableRow key={student.id}>
                                                <TableCell className="font-medium">{student.nis}</TableCell>
                                                <TableCell>{student.full_name}</TableCell>
                                                <TableCell>{student.classroom?.name ?? '-'}</TableCell>
                                                <TableCell>
                                                    <Badge variant={student.is_active ? 'default' : 'secondary'}>
                                                        {student.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

OrangTuaShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Orang Tua', href: '/admin/orang-tua' },
        { title: 'Detail', href: '#' },
    ],
};
