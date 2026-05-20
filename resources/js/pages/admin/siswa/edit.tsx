import { Head, Link, useForm } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type Classroom = {
    id: number;
    name: string;
};

type ParentUser = {
    id: number;
    name: string;
};

type ParentProfile = {
    id: number;
    user: ParentUser | null;
};

type Student = {
    id: number;
    nis: string | null;
    no_absen: string | null;
    nisn: string | null;
    full_name: string;
    gender: string;
    is_active: boolean;
    classroom_id: number | null;
    parent_profile_id: number | null;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
};

type PageProps = {
    student: Student;
    classrooms: Classroom[];
    parentProfiles: ParentProfile[];
};

export default function SiswaEdit({ student, classrooms, parentProfiles }: PageProps) {
    const [parentOpen, setParentOpen] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        full_name: student.full_name,
        nis: student.nis ?? '',
        no_absen: student.no_absen ?? '',
        nisn: student.nisn ?? '',
        gender: student.gender,
        religion: student.religion ?? '',
        classroom_id: student.classroom_id ? String(student.classroom_id) : '',
        birth_place: student.birth_place ?? '',
        birth_date: student.birth_date ? student.birth_date.substring(0, 10) : '',
        address: student.address ?? '',
        parent_profile_id: student.parent_profile_id ? String(student.parent_profile_id) : '',
        is_active: student.is_active,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/siswa/${student.id}`);
    }

    return (
        <>
            <Head title="Edit Siswa" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Edit Siswa</h1>
                    <p className="text-muted-foreground text-sm">Perbarui data siswa di bawah ini.</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Data Siswa</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="grid gap-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                {/* Nama Lengkap */}
                                <div className="grid gap-2">
                                    <Label htmlFor="full_name">Nama Lengkap *</Label>
                                    <Input
                                        id="full_name"
                                        value={data.full_name}
                                        onChange={(e) => setData('full_name', e.target.value)}
                                        placeholder="Masukkan nama lengkap"
                                    />
                                    {errors.full_name && <p className="text-sm text-destructive">{errors.full_name}</p>}
                                </div>

                                {/* NIS */}
                                <div className="grid gap-2">
                                    <Label htmlFor="nis">NIS</Label>
                                    <Input
                                        id="nis"
                                        value={data.nis}
                                        onChange={(e) => setData('nis', e.target.value)}
                                        placeholder="Nomor Induk Siswa"
                                    />
                                    {errors.nis && <p className="text-sm text-destructive">{errors.nis}</p>}
                                </div>

                                {/* No. Absen */}
                                <div className="grid gap-2">
                                    <Label htmlFor="no_absen">No. Absen</Label>
                                    <Input
                                        id="no_absen"
                                        value={data.no_absen}
                                        onChange={(e) => setData('no_absen', e.target.value)}
                                        placeholder="Nomor absen siswa"
                                    />
                                    {errors.no_absen && <p className="text-sm text-destructive">{errors.no_absen}</p>}
                                </div>

                                {/* NISN */}
                                <div className="grid gap-2">
                                    <Label htmlFor="nisn">NISN</Label>
                                    <Input
                                        id="nisn"
                                        value={data.nisn}
                                        onChange={(e) => setData('nisn', e.target.value)}
                                        placeholder="Nomor Induk Siswa Nasional"
                                    />
                                    {errors.nisn && <p className="text-sm text-destructive">{errors.nisn}</p>}
                                </div>

                                {/* Jenis Kelamin */}
                                <div className="grid gap-2">
                                    <Label>Jenis Kelamin *</Label>
                                    <RadioGroup
                                        value={data.gender}
                                        onValueChange={(value) => setData('gender', value)}
                                        className="flex gap-6 pt-1"
                                    >
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="LAKI_LAKI" id="gender_l" />
                                            <Label htmlFor="gender_l" className="font-normal">
                                                Laki-laki
                                            </Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="PEREMPUAN" id="gender_p" />
                                            <Label htmlFor="gender_p" className="font-normal">
                                                Perempuan
                                            </Label>
                                        </div>
                                    </RadioGroup>
                                    {errors.gender && <p className="text-sm text-destructive">{errors.gender}</p>}
                                </div>

                                {/* Agama */}
                                <div className="grid gap-2">
                                    <Label>Agama</Label>
                                    <Select value={data.religion} onValueChange={(value) => setData('religion', value)}>
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Pilih agama" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="ISLAM">Islam</SelectItem>
                                            <SelectItem value="KRISTEN">Kristen Protestan</SelectItem>
                                            <SelectItem value="KATOLIK">Katolik</SelectItem>
                                            <SelectItem value="HINDU">Hindu</SelectItem>
                                            <SelectItem value="BUDDHA">Buddha</SelectItem>
                                            <SelectItem value="KONGHUCU">Konghucu</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Kelas */}
                                <div className="grid gap-2">
                                    <Label htmlFor="classroom_id">Kelas *</Label>
                                    <Select value={data.classroom_id} onValueChange={(value) => setData('classroom_id', value)}>
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Pilih kelas" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {classrooms.map((classroom) => (
                                                <SelectItem key={classroom.id} value={String(classroom.id)}>
                                                    {classroom.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.classroom_id && <p className="text-sm text-destructive">{errors.classroom_id}</p>}
                                </div>

                                {/* Orang Tua — Searchable */}
                                <div className="grid gap-2">
                                    <Label>Orang Tua / Wali</Label>
                                    <Popover open={parentOpen} onOpenChange={setParentOpen}>
                                        <PopoverTrigger asChild>
                                            <Button
                                                variant="outline"
                                                role="combobox"
                                                aria-expanded={parentOpen}
                                                className="w-full justify-between font-normal"
                                            >
                                                {data.parent_profile_id
                                                    ? parentProfiles.find((p) => String(p.id) === data.parent_profile_id)?.user?.name ?? 'Dipilih'
                                                    : 'Cari nama orang tua...'}
                                                <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
                                            <Command>
                                                <CommandInput placeholder="Ketik nama orang tua..." />
                                                <CommandList>
                                                    <CommandEmpty>Tidak ditemukan.</CommandEmpty>
                                                    <CommandGroup>
                                                        {parentProfiles.map((parent) => (
                                                            <CommandItem
                                                                key={parent.id}
                                                                value={parent.user?.name ?? `parent-${parent.id}`}
                                                                onSelect={() => {
                                                                    setData('parent_profile_id', String(parent.id));
                                                                    setParentOpen(false);
                                                                }}
                                                            >
                                                                <Check
                                                                    className={cn(
                                                                        'mr-2 size-4',
                                                                        data.parent_profile_id === String(parent.id) ? 'opacity-100' : 'opacity-0',
                                                                    )}
                                                                />
                                                                {parent.user?.name ?? `Orang Tua #${parent.id}`}
                                                            </CommandItem>
                                                        ))}
                                                    </CommandGroup>
                                                </CommandList>
                                            </Command>
                                        </PopoverContent>
                                    </Popover>
                                    {errors.parent_profile_id && (
                                        <p className="text-sm text-destructive">{errors.parent_profile_id}</p>
                                    )}
                                </div>

                                {/* Tempat Lahir */}
                                <div className="grid gap-2">
                                    <Label htmlFor="birth_place">Tempat Lahir</Label>
                                    <Input
                                        id="birth_place"
                                        value={data.birth_place}
                                        onChange={(e) => setData('birth_place', e.target.value)}
                                        placeholder="Kota tempat lahir"
                                    />
                                    {errors.birth_place && <p className="text-sm text-destructive">{errors.birth_place}</p>}
                                </div>

                                {/* Tanggal Lahir */}
                                <div className="grid gap-2">
                                    <Label htmlFor="birth_date">Tanggal Lahir</Label>
                                    <Input
                                        id="birth_date"
                                        type="date"
                                        value={data.birth_date}
                                        onChange={(e) => setData('birth_date', e.target.value)}
                                    />
                                    {errors.birth_date && <p className="text-sm text-destructive">{errors.birth_date}</p>}
                                </div>
                            </div>

                            {/* Alamat */}
                            <div className="grid gap-2">
                                <Label htmlFor="address">Alamat</Label>
                                <Textarea
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    placeholder="Alamat lengkap siswa"
                                    rows={3}
                                />
                                {errors.address && <p className="text-sm text-destructive">{errors.address}</p>}
                            </div>

                            {/* Status */}
                            <div className="grid gap-2">
                                <Label>Status</Label>
                                <RadioGroup
                                    value={data.is_active ? 'active' : 'inactive'}
                                    onValueChange={(value) => setData('is_active', value === 'active')}
                                    className="flex gap-6"
                                >
                                    <div className="flex items-center gap-2">
                                        <RadioGroupItem value="active" id="status_active" />
                                        <Label htmlFor="status_active" className="font-normal">
                                            Aktif
                                        </Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <RadioGroupItem value="inactive" id="status_inactive" />
                                        <Label htmlFor="status_inactive" className="font-normal">
                                            Nonaktif
                                        </Label>
                                    </div>
                                </RadioGroup>
                            </div>

                            {/* Actions */}
                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/admin/siswa">Batal</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SiswaEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Siswa', href: '/admin/siswa' },
        { title: 'Edit Siswa', href: '#' },
    ],
};
