import { Head, router, useForm } from '@inertiajs/react';
import { Clock, Edit, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';

type Schedule = {
    id: string;
    day_of_week: number;
    classroom_id: string | null;
    classroom: { id: string; name: string } | null;
    check_in_start: string;
    check_in_end: string;
    late_threshold: string;
    check_out_start: string;
    check_out_end: string;
    is_active: boolean;
};

type Classroom = { id: string; name: string };

type Props = {
    schedules: Schedule[];
    classrooms: Classroom[];
};

type FormData = {
    day_of_week: string;
    classroom_id: string;
    check_in_start: string;
    check_in_end: string;
    late_threshold: string;
    check_out_start: string;
    check_out_end: string;
    is_active: boolean;
};

const DAY_LABELS: Record<number, string> = {
    1: 'Senin',
    2: 'Selasa',
    3: 'Rabu',
    4: 'Kamis',
    5: 'Jumat',
    6: 'Sabtu',
    7: 'Minggu',
};

function formatTime(t: string) {
    return t?.substring(0, 5) ?? '-';
}

export default function JadwalAbsensiIndex({ schedules, classrooms }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [editingId, setEditingId] = useState<string | null>(null);

    const form = useForm<FormData>({
        day_of_week: '1',
        classroom_id: '',
        check_in_start: '06:00',
        check_in_end: '08:00',
        late_threshold: '07:15',
        check_out_start: '12:00',
        check_out_end: '17:00',
        is_active: true,
    });

    function openCreate() {
        setEditingId(null);
        form.reset();
        setIsOpen(true);
    }

    function openEdit(s: Schedule) {
        setEditingId(s.id);
        form.setData({
            day_of_week: String(s.day_of_week),
            classroom_id: s.classroom_id ?? '',
            check_in_start: formatTime(s.check_in_start),
            check_in_end: formatTime(s.check_in_end),
            late_threshold: formatTime(s.late_threshold),
            check_out_start: formatTime(s.check_out_start),
            check_out_end: formatTime(s.check_out_end),
            is_active: s.is_active,
        });
        setIsOpen(true);
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (editingId) {
            form.put(`/admin/jadwal-absensi/${editingId}`, {
                preserveScroll: true,
                onSuccess: () => setIsOpen(false),
            });
        } else {
            form.post('/admin/jadwal-absensi', {
                preserveScroll: true,
                onSuccess: () => setIsOpen(false),
            });
        }
    }

    // Group schedules by day
    const grouped = Object.entries(DAY_LABELS).map(([day, label]) => ({
        day: Number(day),
        label,
        items: schedules.filter((s) => s.day_of_week === Number(day)),
    }));

    return (
        <>
            <Head title="Jadwal Absensi" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Jadwal Absensi</h1>
                        <p className="text-muted-foreground text-sm">Atur jadwal masuk, batas terlambat, dan pulang per hari.</p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-1.5 size-4" />
                        Tambah Jadwal
                    </Button>
                </div>

                <div className="space-y-4">
                    {grouped.map(({ day, label, items }) => (
                        <Card key={day}>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Clock className="size-4" />
                                    {label}
                                    {items.length === 0 && (
                                        <Badge variant="secondary" className="text-xs">Tidak ada jadwal</Badge>
                                    )}
                                </CardTitle>
                            </CardHeader>
                            {items.length > 0 && (
                                <CardContent className="pt-0">
                                    <div className="space-y-2">
                                        {items.map((s) => (
                                            <div
                                                key={s.id}
                                                className={`flex items-center justify-between rounded-lg border p-3 ${!s.is_active ? 'opacity-50' : ''}`}
                                            >
                                                <div className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                                                    <div>
                                                        <span className="text-muted-foreground">Kelas:</span>{' '}
                                                        <span className="font-medium">{s.classroom?.name ?? 'Semua Kelas'}</span>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">Masuk:</span>{' '}
                                                        <span className="font-medium">{formatTime(s.check_in_start)} - {formatTime(s.check_in_end)}</span>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">Terlambat:</span>{' '}
                                                        <span className="font-medium text-orange-600">{formatTime(s.late_threshold)}</span>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">Pulang:</span>{' '}
                                                        <span className="font-medium">{formatTime(s.check_out_start)} - {formatTime(s.check_out_end)}</span>
                                                    </div>
                                                    {!s.is_active && <Badge variant="secondary">Nonaktif</Badge>}
                                                </div>
                                                <div className="flex shrink-0 gap-1">
                                                    <Button variant="ghost" size="icon" className="size-8" onClick={() => openEdit(s)}>
                                                        <Edit className="size-3.5" />
                                                    </Button>
                                                    <AlertDialog>
                                                        <AlertDialogTrigger asChild>
                                                            <Button variant="ghost" size="icon" className="size-8">
                                                                <Trash2 className="text-destructive size-3.5" />
                                                            </Button>
                                                        </AlertDialogTrigger>
                                                        <AlertDialogContent>
                                                            <AlertDialogHeader>
                                                                <AlertDialogTitle>Hapus Jadwal</AlertDialogTitle>
                                                                <AlertDialogDescription>
                                                                    Hapus jadwal {label} {s.classroom?.name ? `(${s.classroom.name})` : '(Semua Kelas)'}?
                                                                </AlertDialogDescription>
                                                            </AlertDialogHeader>
                                                            <AlertDialogFooter>
                                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                                <AlertDialogAction
                                                                    className="bg-destructive text-white hover:bg-destructive/90"
                                                                    onClick={() => router.delete(`/admin/jadwal-absensi/${s.id}`, { preserveScroll: true })}
                                                                >
                                                                    Hapus
                                                                </AlertDialogAction>
                                                            </AlertDialogFooter>
                                                        </AlertDialogContent>
                                                    </AlertDialog>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            )}
                        </Card>
                    ))}
                </div>
            </div>

            {/* Create/Edit Dialog */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingId ? 'Edit' : 'Tambah'} Jadwal Absensi</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-2">
                                <Label>Hari</Label>
                                <Select value={form.data.day_of_week} onValueChange={(v) => form.setData('day_of_week', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(DAY_LABELS).map(([val, label]) => (
                                            <SelectItem key={val} value={val}>{label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.day_of_week && <p className="text-destructive text-sm">{form.errors.day_of_week}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label>Kelas</Label>
                                <Select value={form.data.classroom_id || 'all'} onValueChange={(v) => form.setData('classroom_id', v === 'all' ? '' : v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Kelas</SelectItem>
                                        {classrooms.map((c) => (
                                            <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Jam Masuk</Label>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">Mulai</Label>
                                    <Input type="time" value={form.data.check_in_start} onChange={(e) => form.setData('check_in_start', e.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">Selesai</Label>
                                    <Input type="time" value={form.data.check_in_end} onChange={(e) => form.setData('check_in_end', e.target.value)} />
                                </div>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Batas Terlambat</Label>
                            <Input type="time" value={form.data.late_threshold} onChange={(e) => form.setData('late_threshold', e.target.value)} />
                            {form.errors.late_threshold && <p className="text-destructive text-sm">{form.errors.late_threshold}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>Jam Pulang</Label>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">Mulai</Label>
                                    <Input type="time" value={form.data.check_out_start} onChange={(e) => form.setData('check_out_start', e.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">Selesai</Label>
                                    <Input type="time" value={form.data.check_out_end} onChange={(e) => form.setData('check_out_end', e.target.value)} />
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(c) => form.setData('is_active', c === true)}
                            />
                            <Label htmlFor="is_active">Aktif</Label>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>Batal</Button>
                            <Button type="submit" disabled={form.processing}>
                                {editingId ? 'Perbarui' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

JadwalAbsensiIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Absensi', href: '/admin/jadwal-absensi' },
    ],
};
