import { Head, router, useForm } from '@inertiajs/react';
import { CalendarPlus, Clock, Edit, Plus, Trash2 } from 'lucide-react';
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
import { Card, CardContent } from '@/components/ui/card';
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
    1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 7: 'Minggu',
};

function fmt(t: string) {
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
            check_in_start: fmt(s.check_in_start),
            check_in_end: fmt(s.check_in_end),
            late_threshold: fmt(s.late_threshold),
            check_out_start: fmt(s.check_out_start),
            check_out_end: fmt(s.check_out_end),
            is_active: s.is_active,
        });
        setIsOpen(true);
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (editingId) {
            form.put(`/admin/jadwal-absensi/${editingId}`, { preserveScroll: true, onSuccess: () => setIsOpen(false) });
        } else {
            form.post('/admin/jadwal-absensi', { preserveScroll: true, onSuccess: () => setIsOpen(false) });
        }
    }

    function handleGenerateDefaults() {
        router.post('/admin/jadwal-absensi/generate-defaults', {}, { preserveScroll: true });
    }

    const hasSchedules = schedules.length > 0;

    return (
        <>
            <Head title="Jadwal Absensi" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Jadwal Absensi</h1>
                        <p className="text-muted-foreground text-sm">Atur jadwal masuk, batas terlambat, dan pulang per hari.</p>
                    </div>
                    <div className="flex gap-2">
                        {!hasSchedules && (
                            <Button variant="outline" onClick={handleGenerateDefaults}>
                                <CalendarPlus className="mr-1.5 size-4" />
                                Generate Default (Sen-Sab)
                            </Button>
                        )}
                        <Button onClick={openCreate}>
                            <Plus className="mr-1.5 size-4" />
                            Tambah Jadwal
                        </Button>
                    </div>
                </div>

                {/* Table */}
                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="px-4 py-3 text-left font-semibold">Hari</th>
                                        <th className="px-4 py-3 text-left font-semibold">Kelas</th>
                                        <th className="px-4 py-3 text-left font-semibold">Jam Masuk</th>
                                        <th className="px-4 py-3 text-left font-semibold">Batas Terlambat</th>
                                        <th className="px-4 py-3 text-left font-semibold">Jam Pulang</th>
                                        <th className="px-4 py-3 text-left font-semibold">Status</th>
                                        <th className="px-4 py-3 text-right font-semibold">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {!hasSchedules && (
                                        <tr>
                                            <td colSpan={7} className="px-4 py-12 text-center">
                                                <Clock className="text-muted-foreground mx-auto mb-3 size-10" />
                                                <p className="text-muted-foreground font-medium">Belum ada jadwal absensi.</p>
                                                <p className="text-muted-foreground mt-1 text-xs">Klik "Generate Default" untuk membuat jadwal Senin-Sabtu otomatis.</p>
                                            </td>
                                        </tr>
                                    )}
                                    {schedules.map((s) => (
                                        <tr key={s.id} className={`border-b last:border-0 ${!s.is_active ? 'opacity-50' : ''}`}>
                                            <td className="px-4 py-3 font-medium">{DAY_LABELS[s.day_of_week] ?? s.day_of_week}</td>
                                            <td className="px-4 py-3">
                                                {s.classroom?.name ?? (
                                                    <span className="text-muted-foreground">Semua Kelas</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-mono">{fmt(s.check_in_start)}</span>
                                                <span className="text-muted-foreground mx-1">-</span>
                                                <span className="font-mono">{fmt(s.check_in_end)}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-mono font-semibold text-orange-600">{fmt(s.late_threshold)}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-mono">{fmt(s.check_out_start)}</span>
                                                <span className="text-muted-foreground mx-1">-</span>
                                                <span className="font-mono">{fmt(s.check_out_end)}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={s.is_active ? 'default' : 'secondary'}>
                                                    {s.is_active ? 'Aktif' : 'Nonaktif'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-1">
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
                                                                    Hapus jadwal {DAY_LABELS[s.day_of_week]} {s.classroom?.name ? `(${s.classroom.name})` : '(Semua Kelas)'}?
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
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
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
