import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Images,
    Loader2,
    Minus,
    Plus,
    Printer,
    Search,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';

type Classroom = { id: string; name: string };

type StudentItem = {
    id: string;
    full_name: string;
    nis: string | null;
    classroom: string | null;
    classroom_id: string | null;
    has_photo: boolean;
};

type TemplateItem = { value: string; label: string; capacity: number };

type BatchItem = {
    id: string;
    template_label: string;
    status: 'processing' | 'completed' | 'failed';
    pages: number;
    total_slots: number;
    students: string;
    error_message: string | null;
    created_at: string;
};

type Props = {
    students: StudentItem[];
    classrooms: Classroom[];
    templates: TemplateItem[];
    maxPages: number;
    batches: BatchItem[];
};

const statusConfig = {
    completed: { label: 'Selesai', className: 'bg-green-600', icon: CheckCircle2 },
    failed: { label: 'Gagal', className: 'bg-red-600', icon: XCircle },
    processing: { label: 'Diproses', className: 'bg-amber-500', icon: Loader2 },
} as const;

export default function PhotoSheetsIndex({ students, classrooms, templates, maxPages, batches }: Props) {
    const [template, setTemplate] = useState(templates[0]?.value ?? '');
    const [mode, setMode] = useState<'manual' | 'massal'>('manual');
    const [classroomId, setClassroomId] = useState('');
    const [search, setSearch] = useState('');
    // student_id -> jumlah cetak
    const [cart, setCart] = useState<Record<string, number>>({});
    const [bulkClassroomId, setBulkClassroomId] = useState('');
    const [bulkQuantity, setBulkQuantity] = useState(1);

    const { post, processing } = useForm();

    const capacity = useMemo(
        () => templates.find((t) => t.value === template)?.capacity ?? 1,
        [template, templates],
    );

    const filtered = useMemo(() => {
        let list = students;
        if (classroomId) {
            list = list.filter((s) => s.classroom_id === classroomId);
        }
        if (search.trim()) {
            const q = search.toLowerCase();
            list = list.filter(
                (s) => s.full_name.toLowerCase().includes(q) || (s.nis && s.nis.toLowerCase().includes(q)),
            );
        }
        return list;
    }, [students, classroomId, search]);

    const bulkStudents = useMemo(
        () => students.filter((s) => s.classroom_id === bulkClassroomId && s.has_photo),
        [students, bulkClassroomId],
    );

    // Pesanan akhir: keranjang manual, atau seluruh siswa kelas terpilih.
    const items = useMemo(() => {
        if (mode === 'massal') {
            return bulkStudents.map((s) => ({ student_id: s.id, quantity: bulkQuantity }));
        }
        return Object.entries(cart)
            .filter(([, qty]) => qty > 0)
            .map(([student_id, quantity]) => ({ student_id, quantity }));
    }, [mode, cart, bulkStudents, bulkQuantity]);

    const totalSlots = items.reduce((sum, item) => sum + item.quantity, 0);
    const pages = capacity > 0 ? Math.ceil(totalSlots / capacity) : 0;
    const emptySlots = pages * capacity - totalSlots;
    const tooMany = pages > maxPages;

    const studentById = useMemo(() => new Map(students.map((s) => [s.id, s])), [students]);

    function setQuantity(studentId: string, quantity: number) {
        setCart((prev) => {
            const next = { ...prev };
            if (quantity <= 0) {
                delete next[studentId];
            } else {
                next[studentId] = Math.min(100, quantity);
            }
            return next;
        });
    }

    // Riwayat diperbarui sendiri selama masih ada yang dirender.
    const hasProcessing = batches.some((b) => b.status === 'processing');

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
                only: ['batches'],
                onFinish: () => {
                    reloading = false;
                },
            });
        }, 3000);

        return () => window.clearInterval(interval);
    }, [hasProcessing]);

    function handleGenerate() {
        post('/admin/pas-foto', {
            data: { template, items },
            preserveScroll: true,
            onSuccess: () => setCart({}),
        } as never);
    }

    return (
        <>
            <Head title="Generate Pas Foto" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Generate Pas Foto</h1>
                    <p className="text-muted-foreground text-sm">
                        Susun satu lembar 4R untuk beberapa siswa sekaligus, lalu cetak langsung.
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Pemilihan siswa */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Images className="size-4" />
                                Pilih Siswa
                            </CardTitle>
                            <CardDescription>Klik siswa untuk menambahkannya ke lembar cetak.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant={mode === 'manual' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setMode('manual')}
                                >
                                    Manual per siswa
                                </Button>
                                <Button
                                    type="button"
                                    variant={mode === 'massal' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setMode('massal')}
                                >
                                    Massal per kelas
                                </Button>
                            </div>

                            {mode === 'massal' ? (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Kelas</Label>
                                        <Select value={bulkClassroomId} onValueChange={setBulkClassroomId}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih kelas..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {classrooms.map((c) => (
                                                    <SelectItem key={c.id} value={c.id}>
                                                        {c.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Jumlah per siswa</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={100}
                                            value={bulkQuantity}
                                            onChange={(e) => setBulkQuantity(Math.max(1, Number(e.target.value) || 1))}
                                        />
                                    </div>
                                    {bulkClassroomId && (
                                        <p className="text-muted-foreground text-xs sm:col-span-2">
                                            {bulkStudents.length} siswa berfoto di kelas ini akan ikut dicetak.
                                        </p>
                                    )}
                                </div>
                            ) : (
                                <>
                                    <div className="flex flex-col gap-2 sm:flex-row">
                                        <Select value={classroomId || 'all'} onValueChange={(v) => setClassroomId(v === 'all' ? '' : v)}>
                                            <SelectTrigger className="sm:w-52">
                                                <SelectValue placeholder="Semua kelas" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Semua kelas</SelectItem>
                                                {classrooms.map((c) => (
                                                    <SelectItem key={c.id} value={c.id}>
                                                        {c.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <div className="relative flex-1">
                                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                            <Input
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                placeholder="Cari nama atau NIS..."
                                                className="pl-9"
                                            />
                                        </div>
                                    </div>

                                    <div className="max-h-96 divide-y overflow-y-auto rounded-lg border">
                                        {filtered.length === 0 && (
                                            <p className="text-muted-foreground p-4 text-center text-sm">
                                                Tidak ada siswa yang cocok.
                                            </p>
                                        )}
                                        {filtered.map((student) => {
                                            const qty = cart[student.id] ?? 0;

                                            return (
                                                <div key={student.id} className="flex items-center gap-3 p-3">
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate text-sm font-medium">{student.full_name}</p>
                                                        <p className="text-muted-foreground truncate text-xs">
                                                            {student.nis ?? '-'} · {student.classroom ?? 'Tanpa kelas'}
                                                        </p>
                                                    </div>

                                                    {student.has_photo ? (
                                                        <div className="flex items-center gap-1">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="icon"
                                                                className="size-8"
                                                                disabled={qty === 0}
                                                                onClick={() => setQuantity(student.id, qty - 1)}
                                                            >
                                                                <Minus className="size-3.5" />
                                                            </Button>
                                                            <span className="w-8 text-center text-sm font-semibold tabular-nums">
                                                                {qty}
                                                            </span>
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="icon"
                                                                className="size-8"
                                                                onClick={() => setQuantity(student.id, qty + 1)}
                                                            >
                                                                <Plus className="size-3.5" />
                                                            </Button>
                                                        </div>
                                                    ) : (
                                                        <Badge variant="secondary" className="shrink-0">
                                                            Belum ada foto
                                                        </Badge>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Ringkasan */}
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base">Lembar Cetak</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label>Ukuran</Label>
                                <Select value={template} onValueChange={setTemplate}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {templates.map((t) => (
                                            <SelectItem key={t.value} value={t.value}>
                                                {t.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">{capacity} foto per lembar.</p>
                            </div>

                            {mode === 'manual' && items.length > 0 && (
                                <div className="max-h-48 space-y-1 overflow-y-auto rounded-lg border p-2">
                                    {items.map((item) => (
                                        <div key={item.student_id} className="flex items-center gap-2 text-sm">
                                            <span className="min-w-0 flex-1 truncate">
                                                {studentById.get(item.student_id)?.full_name ?? '?'}
                                            </span>
                                            <span className="text-muted-foreground tabular-nums">{item.quantity}</span>
                                            <button
                                                type="button"
                                                onClick={() => setQuantity(item.student_id, 0)}
                                                className="text-muted-foreground hover:text-red-600"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <div className="space-y-1 rounded-lg border p-3 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Total foto</span>
                                    <span className="font-semibold tabular-nums">
                                        {totalSlots} / {pages * capacity || capacity}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Lembar</span>
                                    <span className="font-semibold tabular-nums">{pages}</span>
                                </div>
                            </div>

                            {emptySlots > 0 && (
                                <Alert className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                                    <AlertTriangle className="size-4 text-amber-600" />
                                    <AlertDescription className="text-amber-800 dark:text-amber-200">
                                        {emptySlots} slot akan kosong di lembar terakhir.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {tooMany && (
                                <Alert variant="destructive">
                                    <AlertTriangle className="size-4" />
                                    <AlertDescription>
                                        {pages} lembar melebihi batas {maxPages}. Kurangi jumlahnya.
                                    </AlertDescription>
                                </Alert>
                            )}

                            <Button
                                type="button"
                                onClick={handleGenerate}
                                disabled={processing || items.length === 0 || tooMany}
                                className="w-full gap-2"
                            >
                                {processing ? <Loader2 className="size-4 animate-spin" /> : <Printer className="size-4" />}
                                Generate PDF
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                {/* Riwayat */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Riwayat</CardTitle>
                        <CardDescription>Berkas dibersihkan otomatis setelah 7 hari.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {batches.length === 0 ? (
                            <p className="text-muted-foreground py-6 text-center text-sm">Belum ada lembar dibuat.</p>
                        ) : (
                            <div className="divide-y">
                                {batches.map((batch) => {
                                    const config = statusConfig[batch.status];
                                    const StatusIcon = config.icon;

                                    return (
                                        <div key={batch.id} className="flex flex-wrap items-center gap-3 py-3">
                                            <Badge className={config.className}>
                                                <StatusIcon
                                                    className={`mr-1 size-3 ${batch.status === 'processing' ? 'animate-spin' : ''}`}
                                                />
                                                {config.label}
                                            </Badge>

                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">{batch.students}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {batch.template_label} · {batch.pages} lembar · {batch.total_slots} foto ·{' '}
                                                    {batch.created_at}
                                                </p>
                                                {batch.error_message && (
                                                    <p className="truncate text-xs text-red-600">{batch.error_message}</p>
                                                )}
                                            </div>

                                            {batch.status === 'completed' && (
                                                <a
                                                    href={`/admin/pas-foto/${batch.id}/berkas`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                                                >
                                                    <Printer className="size-3.5" />
                                                    Buka & Cetak
                                                </a>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PhotoSheetsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Generate Pas Foto', href: '/admin/pas-foto' },
    ],
};
