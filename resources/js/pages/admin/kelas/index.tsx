import { Head, router, useForm } from '@inertiajs/react';
import { Edit, GraduationCap, Plus, Trash2, Users } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';
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
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import type { AcademicYear, Classroom } from '@/types';

type PageProps = {
    classrooms: Classroom[];
    academic_years: (AcademicYear & { is_active: boolean })[];
};

type ClassroomFormData = {
    name: string;
    grade_level: string;
    academic_year_id: string;
};

type BulkCreateFormData = {
    grade_level: string;
    parallel_from: string;
    parallel_to: string;
    academic_year_id: string;
};

const GRADE_LEVELS = Array.from({ length: 12 }, (_, i) => ({
    value: String(i + 1),
    label: `Tingkat ${i + 1}`,
}));

const PARALLELS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').map((c) => ({ value: c, label: c }));

export default function KelasIndex({ classrooms, academic_years }: PageProps) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingClassroom, setEditingClassroom] = useState<Classroom | null>(null);

    const [editGradeLevel, setEditGradeLevel] = useState('');
    const [editParallel, setEditParallel] = useState('');

    const bulkForm = useForm<BulkCreateFormData>({
        grade_level: '',
        parallel_from: '',
        parallel_to: '',
        academic_year_id: '',
    });

    const editForm = useForm<ClassroomFormData>({
        name: '',
        grade_level: '',
        academic_year_id: '',
    });

    // Auto-generate class name: "{grade_level}{parallel}" e.g. "7A", "10B"
    function autoName(gradeLevel: string, parallel: string): string {
        return gradeLevel && parallel ? `${gradeLevel}${parallel}` : '';
    }

    // Parse existing class name into grade_level and parallel (e.g. "7A" → "7", "A"; "10B" → "10", "B")
    function parseName(name: string): { grade: string; parallel: string } {
        const match = name.match(/^(\d+)([A-Z])$/);
        return match ? { grade: match[1], parallel: match[2] } : { grade: '', parallel: '' };
    }

    // Generate preview of classes to be created
    function bulkPreview(): string[] {
        const { grade_level, parallel_from, parallel_to } = bulkForm.data;
        if (!grade_level || !parallel_from || !parallel_to) return [];
        const from = parallel_from.charCodeAt(0);
        const to = parallel_to.charCodeAt(0);
        if (from > to) return [];
        const names: string[] = [];
        for (let i = from; i <= to; i++) {
            names.push(`${grade_level}${String.fromCharCode(i)}`);
        }
        return names;
    }

    useEffect(() => {
        editForm.setData('name', autoName(editGradeLevel, editParallel));
    }, [editGradeLevel, editParallel]);

    function openCreateDialog() {
        bulkForm.reset();
        const activeYear = academic_years.find((ay) => ay.is_active);
        if (activeYear) {
            bulkForm.setData('academic_year_id', String(activeYear.id));
        }
        setIsCreateOpen(true);
    }

    function handleCreate(e: FormEvent) {
        e.preventDefault();
        bulkForm.post('/admin/kelas', {
            preserveScroll: true,
            onSuccess: () => setIsCreateOpen(false),
        });
    }

    function openEditDialog(classroom: Classroom) {
        const parsed = parseName(classroom.name);
        setEditGradeLevel(parsed.grade || String(classroom.grade_level));
        setEditParallel(parsed.parallel);
        editForm.setData({
            name: classroom.name,
            grade_level: String(classroom.grade_level),
            academic_year_id: String(classroom.academic_year_id),
        });
        setEditingClassroom(classroom);
    }

    function handleEdit(e: FormEvent) {
        e.preventDefault();
        if (!editingClassroom) return;
        editForm.put(`/admin/kelas/${editingClassroom.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingClassroom(null),
        });
    }

    function handleDelete(id: string) {
        router.delete(`/admin/kelas/${id}`, {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Data Kelas" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Data Kelas</h1>
                        <p className="text-muted-foreground text-sm">Kelola kelas, tahun ajaran, dan wali kelas.</p>
                    </div>
                    <Button onClick={openCreateDialog}>
                        <Plus />
                        Tambah Kelas
                    </Button>
                </div>

                {/* Grid of Cards */}
                {classrooms.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-2 py-12">
                        <GraduationCap className="text-muted-foreground size-12" />
                        <p className="text-muted-foreground text-sm">Belum ada data kelas.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {classrooms.map((classroom) => (
                            <Card key={classroom.id} className="group relative cursor-pointer transition-shadow hover:shadow-md">
                                <CardHeader className="pb-2">
                                    <div className="flex items-start justify-between">
                                        <CardTitle className="text-lg">{classroom.name}</CardTitle>
                                        <Badge variant="secondary">Kelas {classroom.grade_level}</Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-2 pb-2">
                                    <div className="flex items-center gap-2 text-sm">
                                        <Users className="text-muted-foreground size-4" />
                                        <span>{classroom.students_count ?? 0} siswa</span>
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        <span className="font-medium">Tahun Ajaran:</span>{' '}
                                        {classroom.academic_year?.name ?? '-'}
                                    </div>
                                </CardContent>
                                <CardFooter className="gap-2 pt-2">
                                    <Button variant="outline" size="sm" onClick={() => openEditDialog(classroom)}>
                                        <Edit className="size-3.5" />
                                        Edit
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button variant="outline" size="sm">
                                                <Trash2 className="text-destructive size-3.5" />
                                                Hapus
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Hapus Kelas</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Apakah Anda yakin ingin menghapus kelas <strong>{classroom.name}</strong>?
                                                    Kelas yang masih memiliki siswa tidak dapat dihapus.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                                <AlertDialogAction
                                                    className="bg-destructive text-white hover:bg-destructive/90"
                                                    onClick={() => handleDelete(classroom.id)}
                                                >
                                                    Hapus
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Create Dialog — Bulk */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah Kelas</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleCreate} className="space-y-4">
                        <div className="space-y-2">
                            <Label>Tingkat</Label>
                            <Select
                                value={bulkForm.data.grade_level}
                                onValueChange={(value) => bulkForm.setData('grade_level', value)}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Pilih tingkat" />
                                </SelectTrigger>
                                <SelectContent>
                                    {GRADE_LEVELS.map((level) => (
                                        <SelectItem key={level.value} value={level.value}>
                                            {level.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {bulkForm.errors.grade_level && (
                                <p className="text-destructive text-sm">{bulkForm.errors.grade_level}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>Paralel</Label>
                            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                                <Select
                                    value={bulkForm.data.parallel_from}
                                    onValueChange={(value) => bulkForm.setData('parallel_from', value)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Dari" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PARALLELS.map((p) => (
                                            <SelectItem key={p.value} value={p.value}>
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <span className="text-muted-foreground text-sm">s/d</span>
                                <Select
                                    value={bulkForm.data.parallel_to}
                                    onValueChange={(value) => bulkForm.setData('parallel_to', value)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Sampai" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PARALLELS.map((p) => (
                                            <SelectItem key={p.value} value={p.value}>
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            {bulkForm.errors.parallel_from && (
                                <p className="text-destructive text-sm">{bulkForm.errors.parallel_from}</p>
                            )}
                            {bulkForm.errors.parallel_to && (
                                <p className="text-destructive text-sm">{bulkForm.errors.parallel_to}</p>
                            )}
                            {bulkPreview().length > 0 && (
                                <p className="text-muted-foreground text-sm">
                                    Kelas yang dibuat: <strong>{bulkPreview().join(', ')}</strong>
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>Tahun Ajaran</Label>
                            <Select
                                value={bulkForm.data.academic_year_id}
                                onValueChange={(value) => bulkForm.setData('academic_year_id', value)}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Pilih tahun ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    {academic_years.map((ay) => (
                                        <SelectItem key={ay.id} value={String(ay.id)}>
                                            {ay.name} {ay.is_active ? '(Aktif)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {bulkForm.errors.academic_year_id && (
                                <p className="text-destructive text-sm">{bulkForm.errors.academic_year_id}</p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={bulkForm.processing}>
                                {bulkPreview().length > 1 ? `Buat ${bulkPreview().length} Kelas` : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={editingClassroom !== null} onOpenChange={(open) => !open && setEditingClassroom(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Kelas</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleEdit} className="space-y-4">
                        <div className="space-y-2">
                            <Label>Nama Kelas</Label>
                            <div className="grid grid-cols-2 gap-2">
                                <div className="space-y-1">
                                    <Label htmlFor="edit-grade" className="text-muted-foreground text-xs">
                                        Tingkat
                                    </Label>
                                    <Select
                                        value={editGradeLevel}
                                        onValueChange={(value) => {
                                            setEditGradeLevel(value);
                                            editForm.setData('grade_level', value);
                                        }}
                                    >
                                        <SelectTrigger id="edit-grade" className="w-full">
                                            <SelectValue placeholder="Tingkat" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {GRADE_LEVELS.map((level) => (
                                                <SelectItem key={level.value} value={level.value}>
                                                    {level.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="edit-parallel" className="text-muted-foreground text-xs">
                                        Paralel
                                    </Label>
                                    <Select value={editParallel} onValueChange={setEditParallel}>
                                        <SelectTrigger id="edit-parallel" className="w-full">
                                            <SelectValue placeholder="Paralel" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {PARALLELS.map((p) => (
                                                <SelectItem key={p.value} value={p.value}>
                                                    {p.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            {editGradeLevel && editParallel && (
                                <p className="text-muted-foreground text-sm">
                                    Nama kelas: <strong>{autoName(editGradeLevel, editParallel)}</strong>
                                </p>
                            )}
                            {editForm.errors.name && (
                                <p className="text-destructive text-sm">{editForm.errors.name}</p>
                            )}
                            {editForm.errors.grade_level && (
                                <p className="text-destructive text-sm">{editForm.errors.grade_level}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-academic-year">Tahun Ajaran</Label>
                            <Select
                                value={editForm.data.academic_year_id}
                                onValueChange={(value) => editForm.setData('academic_year_id', value)}
                            >
                                <SelectTrigger id="edit-academic-year" className="w-full">
                                    <SelectValue placeholder="Pilih tahun ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    {academic_years.map((ay) => (
                                        <SelectItem key={ay.id} value={String(ay.id)}>
                                            {ay.name} {ay.is_active ? '(Aktif)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {editForm.errors.academic_year_id && (
                                <p className="text-destructive text-sm">{editForm.errors.academic_year_id}</p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditingClassroom(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                Perbarui
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

KelasIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Kelas', href: '/admin/kelas' },
    ],
};
