import { Head, router, useForm } from '@inertiajs/react';
import { CreditCard, Search, Trash2, Wifi } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { destroy as destroyCard, store as storeCard } from '@/actions/App/Http/Controllers/Admin/RfidCardController';
import InputError from '@/components/input-error';
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
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type Classroom = {
    id: string;
    name: string;
};

type StudentRow = {
    id: string;
    full_name: string;
    nis: string | null;
    nisn: string | null;
    classroom: string | null;
    is_active: boolean;
    rfid_uid: string | null;
    rfid_registered_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PageProps = {
    students: {
        data: StudentRow[];
        links: PaginationLink[];
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    classrooms: Classroom[];
    filters: { search: string; classroom_id: string; status: string };
    summary: { total: number; registered: number };
};

export default function RfidCardsIndex({ students, classrooms, filters, summary }: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [targetId, setTargetId] = useState<string | null>(null);

    // Diturunkan ulang dari prop, bukan disimpan sebagai objek. Setiap simpan
    // mengalihkan halaman dan mengganti isi `students`, jadi baris yang disimpan
    // di state akan menampilkan UID versi sebelum perubahan.
    const target = targetId ? (students.data.find((student) => student.id === targetId) ?? null) : null;

    function applyFilters(next: Partial<{ search: string; classroom_id: string; status: string }>) {
        const merged = { ...filters, ...next };

        router.get(
            '/admin/rfid-cards',
            {
                search: merged.search || undefined,
                classroom_id: merged.classroom_id || undefined,
                status: merged.status || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    function unregister(student: StudentRow) {
        router.delete(destroyCard.url(student.id), { preserveScroll: true });
    }

    return (
        <>
            <Head title="Kartu RFID" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Kartu RFID</h1>
                    <p className="text-muted-foreground text-sm">
                        Daftarkan kartu ke siswa supaya bisa absen dengan menempelkan kartu di gerbang.
                    </p>
                </div>

                <Card>
                    <CardContent className="text-muted-foreground flex flex-wrap items-center gap-x-6 gap-y-1 py-4 text-sm">
                        <span>
                            <span className="text-foreground font-semibold">{summary.registered}</span> dari {summary.total} siswa sudah punya kartu
                        </span>
                        <span className="text-xs">
                            Colok pembaca kartu USB ke komputer ini, klik Daftarkan, lalu tempelkan kartunya — UID terisi sendiri.
                        </span>
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            placeholder="Cari nama, NIS, NISN, atau UID kartu..."
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                applyFilters({ search: e.target.value });
                            }}
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={filters.classroom_id || 'all'}
                        onValueChange={(value) => applyFilters({ classroom_id: value === 'all' ? '' : value })}
                    >
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder="Semua Kelas" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Kelas</SelectItem>
                            {classrooms.map((classroom) => (
                                <SelectItem key={classroom.id} value={String(classroom.id)}>
                                    {classroom.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.status || 'all'} onValueChange={(value) => applyFilters({ status: value === 'all' ? '' : value })}>
                        <SelectTrigger className="w-full sm:w-[180px]">
                            <SelectValue placeholder="Semua Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Status</SelectItem>
                            <SelectItem value="terdaftar">Sudah punya kartu</SelectItem>
                            <SelectItem value="belum">Belum punya kartu</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>NIS</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead>Kelas</TableHead>
                                <TableHead>UID Kartu</TableHead>
                                <TableHead>Didaftarkan</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {students.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        Tidak ada siswa yang cocok.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                students.data.map((student) => (
                                    <TableRow key={student.id}>
                                        <TableCell className="font-medium">{student.nis ?? '-'}</TableCell>
                                        <TableCell>
                                            {student.full_name}
                                            {!student.is_active && (
                                                <Badge variant="secondary" className="ml-2">
                                                    Nonaktif
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>{student.classroom ?? '-'}</TableCell>
                                        <TableCell>
                                            {student.rfid_uid ? (
                                                <code className="bg-muted rounded px-1.5 py-0.5 text-xs">{student.rfid_uid}</code>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">Belum ada</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-xs">{student.rfid_registered_at ?? '-'}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="outline" size="sm" onClick={() => setTargetId(student.id)}>
                                                    <CreditCard className="mr-1.5 size-4" />
                                                    {student.rfid_uid ? 'Ganti' : 'Daftarkan'}
                                                </Button>
                                                {student.rfid_uid && (
                                                    <AlertDialog>
                                                        <AlertDialogTrigger asChild>
                                                            <Button variant="ghost" size="icon" title="Lepas kartu">
                                                                <Trash2 className="text-destructive size-4" />
                                                            </Button>
                                                        </AlertDialogTrigger>
                                                        <AlertDialogContent>
                                                            <AlertDialogHeader>
                                                                <AlertDialogTitle>Lepas Kartu</AlertDialogTitle>
                                                                <AlertDialogDescription>
                                                                    Kartu <strong>{student.rfid_uid}</strong> tidak akan bisa dipakai absen oleh{' '}
                                                                    <strong>{student.full_name}</strong> lagi. UID-nya kembali bebas dan bisa
                                                                    didaftarkan ke siswa lain.
                                                                </AlertDialogDescription>
                                                            </AlertDialogHeader>
                                                            <AlertDialogFooter>
                                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                                <AlertDialogAction
                                                                    className="bg-destructive hover:bg-destructive/90 text-white"
                                                                    onClick={() => unregister(student)}
                                                                >
                                                                    Lepas
                                                                </AlertDialogAction>
                                                            </AlertDialogFooter>
                                                        </AlertDialogContent>
                                                    </AlertDialog>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {students.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-muted-foreground text-sm">
                            Menampilkan {students.from} - {students.to} dari {students.total} siswa
                        </p>
                        <div className="flex gap-1">
                            {students.links.map((link, index) => (
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

            <RegisterCardDialog student={target} onClose={() => setTargetId(null)} />
        </>
    );
}

/**
 * Pembaca RFID mode HID berlaku seperti keyboard: ia mengetikkan UID lalu menekan
 * Enter. Jadi yang dibutuhkan hanya satu input yang sudah terfokus — tidak perlu
 * menyadap keydown global seperti konsol scan gerbang, karena di sini fokusnya
 * memang milik dialog ini sendiri.
 */
function RegisterCardDialog({ student, onClose }: { student: StudentRow | null; onClose: () => void }) {
    const inputRef = useRef<HTMLInputElement | null>(null);
    const form = useForm({ rfid_uid: '' });

    useEffect(() => {
        if (student) {
            form.setData('rfid_uid', '');
            form.clearErrors();
            // Menunggu dialognya benar-benar terpasang sebelum merebut fokus.
            const id = window.setTimeout(() => inputRef.current?.focus(), 50);

            return () => window.clearTimeout(id);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [student?.id]);

    function submit(e: FormEvent) {
        e.preventDefault();

        // Sebagian pembaca RFID mengirim Enter lebih dari sekali, atau membaca
        // ulang selama kartu masih menempel. Tanpa kunci ini satu tap bisa
        // melahirkan beberapa permintaan sekaligus.
        if (!student || form.processing) {
            return;
        }

        form.post(storeCard.url(student.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
            // Simpan gagal berarti operator akan menempelkan kartu lagi —
            // kolomnya harus siap menerima, bukan kehilangan fokus.
            onError: () => inputRef.current?.focus(),
        });
    }

    return (
        <Dialog open={student !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Daftarkan Kartu RFID</DialogTitle>
                    <DialogDescription>
                        Untuk <strong>{student?.full_name}</strong>
                        {student?.classroom ? ` · ${student.classroom}` : ''}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="rfid-uid">UID Kartu</Label>
                        <div className="relative">
                            <Wifi className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                id="rfid-uid"
                                ref={inputRef}
                                value={form.data.rfid_uid}
                                onChange={(e) => form.setData('rfid_uid', e.target.value)}
                                placeholder="Tempelkan kartu ke pembaca…"
                                autoComplete="off"
                                className="pl-9 font-mono tracking-wider"
                            />
                        </div>
                        <InputError message={form.errors.rfid_uid} />
                        <p className="text-muted-foreground text-xs">
                            Kolom ini sudah terfokus. Tempelkan kartunya dan UID akan terisi sendiri, biasanya langsung terkirim. Boleh juga
                            diketik manual.
                        </p>
                    </div>

                    {student?.rfid_uid && (
                        <p className="text-xs text-amber-600 dark:text-amber-500">
                            Siswa ini sudah memakai kartu <code className="font-mono">{student.rfid_uid}</code>. Mendaftarkan kartu baru akan
                            menggantikannya, dan kartu lama berhenti berlaku.
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing || form.data.rfid_uid.trim() === ''}>
                            Simpan Kartu
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
