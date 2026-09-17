import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, CreditCard, Loader2 } from 'lucide-react';
import { useEffect } from 'react';
import { ProgresGenerate } from '@/components/shared/progres-generate';
import type { Progres } from '@/components/shared/progres-generate';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dashboard } from '@/routes';

type Opsi = { id: string; name: string };

type SiswaTanpaFoto = {
    id: string;
    full_name: string;
    nis: string | null;
    classroom: string | null;
};

type Ringkasan = {
    total: number;
    berfoto: number;
    tanpa_foto: SiswaTanpaFoto[];
};

type Batch = {
    id: string;
    kelas: string | null;
    dibuat: string;
    progres: Progres;
};

type PageProps = {
    filters: { school_id: string; classroom_id: string };
    schools: Opsi[];
    classrooms: Opsi[];
    ringkasan: Ringkasan | null;
    batchBerjalan: Batch | null;
};

// Sentinel untuk "semua kelas". Radix Select menolak SelectItem bernilai string
// kosong, jadi tidak ada pilihan selain nilai semu — pola yang sama dipakai
// halaman daftar siswa.
const SEMUA = 'all';

export default function GenerateKartuMassal({ filters, schools, classrooms, ringkasan, batchBerjalan }: PageProps) {
    const { post, processing, errors } = useForm({
        school_id: filters.school_id,
        classroom_id: filters.classroom_id,
    });

    function pilihSekolah(schoolId: string) {
        // Kelas ikut dikosongkan: nama kelas berulang di setiap sekolah, dan
        // membawa `classroom_id` lama ke sekolah baru hanya menghasilkan filter
        // yang menunjuk kelas milik orang lain.
        router.get('/admin/generate-kartu', { school_id: schoolId }, { preserveState: true, replace: true });
    }

    function pilihKelas(classroomId: string) {
        router.get(
            '/admin/generate-kartu',
            {
                school_id: filters.school_id,
                ...(classroomId === SEMUA ? {} : { classroom_id: classroomId }),
            },
            { preserveState: true, replace: true },
        );
    }

    const kurang = ringkasan ? ringkasan.total - ringkasan.berfoto : 0;
    const siap = ringkasan !== null && ringkasan.total > 0 && kurang === 0;

    /*
        Polling tiga detik selama batch terakhir belum beres.

        Kemajuannya dihitung server dari baris log, jadi menutup halaman ini
        tidak menghentikan apa pun dan membukanya lagi menunjukkan angka yang
        benar. Itulah syarat "bisa ditinggal".
    */
    const berjalan = batchBerjalan?.progres.status === 'processing';

    useEffect(() => {
        if (!berjalan) {
            return;
        }

        const interval = setInterval(() => {
            router.reload({ only: ['batchBerjalan'] });
        }, 3000);

        return () => clearInterval(interval);
    }, [berjalan]);

    return (
        <>
            <Head title="Generate Kartu" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Generate Kartu</h1>
                    <p className="text-muted-foreground text-sm">
                        Membuat kartu OSIS depan dan belakang untuk satu sekolah atau satu kelas sekaligus.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Pilih Sasaran</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Sekolah</Label>
                            <Select value={filters.school_id || undefined} onValueChange={pilihSekolah}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Pilih sekolah" />
                                </SelectTrigger>
                                <SelectContent>
                                    {schools.map((s) => (
                                        <SelectItem key={s.id} value={s.id}>
                                            {s.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.school_id && <p className="text-destructive text-sm">{errors.school_id}</p>}
                        </div>

                        <div className="grid gap-2">
                            <Label>Kelas</Label>
                            <Select
                                value={filters.classroom_id || SEMUA}
                                onValueChange={pilihKelas}
                                disabled={!filters.school_id}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder={filters.school_id ? 'Semua kelas' : 'Pilih sekolah dulu'} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SEMUA}>Semua kelas</SelectItem>
                                    {classrooms.map((c) => (
                                        <SelectItem key={c.id} value={c.id}>
                                            {c.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.classroom_id && <p className="text-destructive text-sm">{errors.classroom_id}</p>}
                        </div>
                    </CardContent>
                </Card>

                {ringkasan && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Kesiapan Foto</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {ringkasan.total === 0 ? (
                                <p className="text-muted-foreground text-sm">Tidak ada siswa aktif pada pilihan ini.</p>
                            ) : (
                                <div
                                    className={`flex items-start gap-3 rounded-lg border p-4 ${
                                        siap
                                            ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950'
                                            : 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950'
                                    }`}
                                >
                                    {siap ? (
                                        <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-green-600" />
                                    ) : (
                                        <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-600" />
                                    )}
                                    <div>
                                        <p className="text-sm font-semibold">
                                            {ringkasan.berfoto} dari {ringkasan.total} siswa punya pas foto
                                        </p>
                                        <p className="text-muted-foreground mt-0.5 text-sm">
                                            {siap
                                                ? 'Semua siap. Kartu bisa dibuat sekarang.'
                                                : `${kurang} siswa belum punya pas foto. Kartu tanpa foto tetap jadi — dengan kotak kosong di tempat wajahnya — jadi generate ditahan sampai semuanya lengkap.`}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {kurang > 0 && (
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Nama</TableHead>
                                                <TableHead>NIS</TableHead>
                                                <TableHead>Kelas</TableHead>
                                                <TableHead className="text-right">Aksi</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {ringkasan.tanpa_foto.map((s) => (
                                                <TableRow key={s.id}>
                                                    <TableCell className="font-medium">{s.full_name}</TableCell>
                                                    <TableCell className="tabular-nums">{s.nis ?? '—'}</TableCell>
                                                    <TableCell>{s.classroom ?? '—'}</TableCell>
                                                    <TableCell className="text-right">
                                                        {/* Tertaut supaya fotonya bisa langsung dipasang,
                                                            bukan cuma dilaporkan kurang. */}
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={`/admin/siswa/${s.id}/edit`}>Pasang foto</Link>
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>

                                    {kurang > ringkasan.tanpa_foto.length && (
                                        <p className="text-muted-foreground mt-2 text-xs">
                                            Menampilkan {ringkasan.tanpa_foto.length} dari {kurang} siswa tanpa foto.
                                        </p>
                                    )}
                                </div>
                            )}

                            <div>
                                <Button
                                    onClick={() => post('/admin/generate-kartu', { preserveScroll: true })}
                                    disabled={!siap || processing || berjalan}
                                >
                                    {processing ? (
                                        <Loader2 className="mr-2 size-4 animate-spin" />
                                    ) : (
                                        <CreditCard className="mr-2 size-4" />
                                    )}
                                    Generate Kartu OSIS (depan + belakang)
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {batchBerjalan && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Kemajuan</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <p className="text-muted-foreground text-sm">
                                Dimulai {batchBerjalan.dibuat}
                                {batchBerjalan.kelas ? ` · kelas ${batchBerjalan.kelas}` : ' · seluruh sekolah'}
                            </p>
                            <ProgresGenerate progres={batchBerjalan.progres} />
                            <Link href="/admin/card-generation" className="text-sm text-blue-600 hover:underline">
                                Buka Riwayat Kartu untuk melihat hasil per siswa
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

GenerateKartuMassal.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Generate Kartu', href: '#' },
    ],
};
