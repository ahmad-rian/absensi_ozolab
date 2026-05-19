import { Head, router, useForm } from '@inertiajs/react';
import { Edit, GraduationCap, Plus, Trash2, Users } from 'lucide-react';
import { FormEvent, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';
import type { AcademicYear, Classroom } from '@/types';

type Teacher = {
    id: number;
    name: string;
};

type PageProps = {
    classrooms: Classroom[];
    academic_years: (AcademicYear & { is_active: boolean })[];
    teachers: Teacher[];
};

type ClassroomFormData = {
    name: string;
    grade_level: string;
    academic_year_id: string;
    homeroom_teacher_id: string;
    capacity: string;
};

const GRADE_LEVELS = [
    { value: '7', label: 'Kelas 7' },
    { value: '8', label: 'Kelas 8' },
    { value: '9', label: 'Kelas 9' },
    { value: '10', label: 'Kelas 10' },
    { value: '11', label: 'Kelas 11' },
    { value: '12', label: 'Kelas 12' },
];

export default function KelasIndex({ classrooms, academic_years, teachers }: PageProps) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingClassroom, setEditingClassroom] = useState<Classroom | null>(null);

    const createForm = useForm<ClassroomFormData>({
        name: '',
        grade_level: '',
        academic_year_id: '',
        homeroom_teacher_id: '',
        capacity: '30',
    });

    const editForm = useForm<ClassroomFormData>({
        name: '',
        grade_level: '',
        academic_year_id: '',
        homeroom_teacher_id: '',
        capacity: '30',
    });

    function openCreateDialog() {
        createForm.reset();
        const activeYear = academic_years.find((ay) => ay.is_active);
        if (activeYear) {
            createForm.setData('academic_year_id', String(activeYear.id));
        }
        setIsCreateOpen(true);
    }

    function handleCreate(e: FormEvent) {
        e.preventDefault();
        createForm.post('/admin/kelas', {
            preserveScroll: true,
            onSuccess: () => setIsCreateOpen(false),
        });
    }

    function openEditDialog(classroom: Classroom) {
        editForm.setData({
            name: classroom.name,
            grade_level: String(classroom.grade_level),
            academic_year_id: String(classroom.academic_year_id),
            homeroom_teacher_id: classroom.homeroom_teacher_id ? String(classroom.homeroom_teacher_id) : '',
            capacity: String(classroom.capacity),
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

    function handleDelete(id: number) {
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
                                        <span>
                                            {classroom.students_count ?? 0} / {classroom.capacity} siswa
                                        </span>
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        <span className="font-medium">Wali Kelas:</span>{' '}
                                        {classroom.homeroom_teacher?.name ?? '-'}
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

            {/* Create Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah Kelas</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleCreate} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="create-name">Nama Kelas</Label>
                            <Input
                                id="create-name"
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                                placeholder="Contoh: VII-A"
                            />
                            {createForm.errors.name && (
                                <p className="text-destructive text-sm">{createForm.errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-grade">Tingkat Kelas</Label>
                            <Select
                                value={createForm.data.grade_level}
                                onValueChange={(value) => createForm.setData('grade_level', value)}
                            >
                                <SelectTrigger id="create-grade" className="w-full">
                                    <SelectValue placeholder="Pilih tingkat kelas" />
                                </SelectTrigger>
                                <SelectContent>
                                    {GRADE_LEVELS.map((level) => (
                                        <SelectItem key={level.value} value={level.value}>
                                            {level.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {createForm.errors.grade_level && (
                                <p className="text-destructive text-sm">{createForm.errors.grade_level}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-academic-year">Tahun Ajaran</Label>
                            <Select
                                value={createForm.data.academic_year_id}
                                onValueChange={(value) => createForm.setData('academic_year_id', value)}
                            >
                                <SelectTrigger id="create-academic-year" className="w-full">
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
                            {createForm.errors.academic_year_id && (
                                <p className="text-destructive text-sm">{createForm.errors.academic_year_id}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-teacher">Wali Kelas</Label>
                            <Select
                                value={createForm.data.homeroom_teacher_id}
                                onValueChange={(value) => createForm.setData('homeroom_teacher_id', value)}
                            >
                                <SelectTrigger id="create-teacher" className="w-full">
                                    <SelectValue placeholder="Pilih wali kelas (opsional)" />
                                </SelectTrigger>
                                <SelectContent>
                                    {teachers.map((teacher) => (
                                        <SelectItem key={teacher.id} value={String(teacher.id)}>
                                            {teacher.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {createForm.errors.homeroom_teacher_id && (
                                <p className="text-destructive text-sm">{createForm.errors.homeroom_teacher_id}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-capacity">Kapasitas</Label>
                            <Input
                                id="create-capacity"
                                type="number"
                                min="1"
                                max="100"
                                value={createForm.data.capacity}
                                onChange={(e) => createForm.setData('capacity', e.target.value)}
                            />
                            {createForm.errors.capacity && (
                                <p className="text-destructive text-sm">{createForm.errors.capacity}</p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                Simpan
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
                            <Label htmlFor="edit-name">Nama Kelas</Label>
                            <Input
                                id="edit-name"
                                value={editForm.data.name}
                                onChange={(e) => editForm.setData('name', e.target.value)}
                                placeholder="Contoh: VII-A"
                            />
                            {editForm.errors.name && (
                                <p className="text-destructive text-sm">{editForm.errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-grade">Tingkat Kelas</Label>
                            <Select
                                value={editForm.data.grade_level}
                                onValueChange={(value) => editForm.setData('grade_level', value)}
                            >
                                <SelectTrigger id="edit-grade" className="w-full">
                                    <SelectValue placeholder="Pilih tingkat kelas" />
                                </SelectTrigger>
                                <SelectContent>
                                    {GRADE_LEVELS.map((level) => (
                                        <SelectItem key={level.value} value={level.value}>
                                            {level.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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

                        <div className="space-y-2">
                            <Label htmlFor="edit-teacher">Wali Kelas</Label>
                            <Select
                                value={editForm.data.homeroom_teacher_id}
                                onValueChange={(value) => editForm.setData('homeroom_teacher_id', value)}
                            >
                                <SelectTrigger id="edit-teacher" className="w-full">
                                    <SelectValue placeholder="Pilih wali kelas (opsional)" />
                                </SelectTrigger>
                                <SelectContent>
                                    {teachers.map((teacher) => (
                                        <SelectItem key={teacher.id} value={String(teacher.id)}>
                                            {teacher.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {editForm.errors.homeroom_teacher_id && (
                                <p className="text-destructive text-sm">{editForm.errors.homeroom_teacher_id}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-capacity">Kapasitas</Label>
                            <Input
                                id="edit-capacity"
                                type="number"
                                min="1"
                                max="100"
                                value={editForm.data.capacity}
                                onChange={(e) => editForm.setData('capacity', e.target.value)}
                            />
                            {editForm.errors.capacity && (
                                <p className="text-destructive text-sm">{editForm.errors.capacity}</p>
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
