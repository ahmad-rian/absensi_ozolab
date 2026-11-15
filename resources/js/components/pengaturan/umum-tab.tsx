import { router, useForm } from '@inertiajs/react';
import { Building2, CalendarRange, Clock, Plus, Save } from 'lucide-react';
import { type FormEvent, type KeyboardEvent, useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { GuideDialog } from '@/components/panduan/guide-dialog';
import { TahunAjaran } from '@/components/panduan/sections/alur';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import tahunAjaran from '@/routes/admin/pengaturan/tahun-ajaran';
import type { SettingsValues } from './settings-tabs';

export type AcademicYearOption = {
    id: string;
    name: string;
    is_active: boolean;
};

type UmumData = {
    section: 'umum';
    school_name: string;
    timezone: string;
    academic_year_id: string;
};

export function UmumTab({
    settings,
    academicYears,
    activeAcademicYearId,
    onDirtyChange,
}: {
    settings: SettingsValues;
    academicYears: AcademicYearOption[];
    activeAcademicYearId: string | null;
    onDirtyChange: (dirty: boolean) => void;
}) {
    const { data, setData, put, processing, errors, isDirty } = useForm<UmumData>({
        // `section` ikut di dalam payload supaya controller tahu ruleset mana
        // yang dipakai; ia di-unset sebelum merge ke schools.settings.
        section: 'umum',
        school_name: (settings.school_name as string) || '',
        timezone: (settings.timezone as string) || 'Asia/Jakarta',
        academic_year_id: activeAcademicYearId ?? '',
    });

    useEffect(() => onDirtyChange(isDirty), [isDirty, onDirtyChange]);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        // preserveState menjaga komponen tetap hidup melewati redirect, jadi
        // tab aktif tidak melompat balik ke tab pertama.
        put('/admin/pengaturan', { preserveScroll: true, preserveState: true });
    }

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Building2 className="size-5 text-blue-600" />
                        <CardTitle>Informasi Situs</CardTitle>
                    </div>
                    <CardDescription>Nama dan identitas situs yang ditampilkan di aplikasi.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-2">
                        <Label htmlFor="school_name" className="text-sm font-medium">
                            Nama Situs
                        </Label>
                        <Input
                            id="school_name"
                            value={data.school_name}
                            onChange={(e) => setData('school_name', e.target.value)}
                            placeholder="Masukkan nama situs"
                        />
                        <InputError message={errors.school_name} />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Clock className="size-5 text-green-600" />
                        <CardTitle>Zona Waktu</CardTitle>
                    </div>
                    <CardDescription>Pengaturan zona waktu untuk sistem absensi.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-2">
                        <Label htmlFor="timezone" className="text-sm font-medium">
                            Zona Waktu
                        </Label>
                        <Select value={data.timezone} onValueChange={(val) => setData('timezone', val)}>
                            <SelectTrigger className="w-64">
                                <SelectValue placeholder="Pilih zona waktu" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Asia/Jakarta">Asia/Jakarta (WIB)</SelectItem>
                                <SelectItem value="Asia/Makassar">Asia/Makassar (WITA)</SelectItem>
                                <SelectItem value="Asia/Jayapura">Asia/Jayapura (WIT)</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.timezone} />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <CalendarRange className="size-5 text-amber-600" />
                        <CardTitle>Tahun Ajaran Aktif</CardTitle>
                        <GuideDialog
                            title="Cara menambah dan mengganti tahun ajaran"
                            description="Urutan yang aman saat pergantian tahun ajaran: tambah, aktifkan, buat kelas, lalu naikkan siswa."
                            topik="tahun-ajaran"
                        >
                            <TahunAjaran />
                        </GuideDialog>
                    </div>
                    <CardDescription>
                        Menentukan tahun ajaran yang dipakai sebagai acuan kelas dan laporan. Hanya satu tahun
                        ajaran yang bisa aktif.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-2">
                        <Label htmlFor="academic_year_id" className="text-sm font-medium">
                            Tahun Ajaran
                        </Label>
                        {academicYears.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Belum ada tahun ajaran. Tambahkan lewat kolom Tahun mulai di bawah ini.
                            </p>
                        ) : (
                            <Select
                                value={data.academic_year_id}
                                onValueChange={(val) => setData('academic_year_id', val)}
                            >
                                <SelectTrigger id="academic_year_id" className="w-64">
                                    <SelectValue placeholder="Pilih tahun ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    {academicYears.map((year) => (
                                        <SelectItem key={year.id} value={year.id}>
                                            {year.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        <InputError message={errors.academic_year_id} />
                    </div>
                    <TambahTahunAjaran />
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing || !isDirty}>
                    <Save className="mr-2 size-4" />
                    Simpan Pengaturan Umum
                </Button>
            </div>
        </form>
    );
}

/**
 * Menambah tahun ajaran baru ke daftar di atas.
 *
 * Sengaja bukan <form>: komponen ini berada di dalam form Pengaturan Umum, dan
 * form bersarang tidak sah di HTML. Menambah tidak mengaktifkan; tahun aktif
 * tetap dipilih di daftar lalu disimpan.
 */
function TambahTahunAjaran() {
    const [tahunMulai, setTahunMulai] = useState('');
    const [galat, setGalat] = useState<string | undefined>();
    const [mengirim, setMengirim] = useState(false);

    const mulai = Number.parseInt(tahunMulai, 10);
    const pratinjau = /^\d{4}$/.test(tahunMulai) ? `${mulai}/${mulai + 1}` : null;

    function tambah() {
        router.post(
            tahunAjaran.store.url(),
            { tahun_mulai: tahunMulai },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setMengirim(true),
                onFinish: () => setMengirim(false),
                onError: (errors) => setGalat(errors.tahun_mulai),
                onSuccess: () => {
                    setTahunMulai('');
                    setGalat(undefined);
                },
            },
        );
    }

    function tekanEnter(e: KeyboardEvent<HTMLInputElement>) {
        if (e.key !== 'Enter') {
            return;
        }

        // Enter tidak boleh ikut mengirim form Pengaturan Umum di luarnya.
        e.preventDefault();

        if (pratinjau && !mengirim) {
            tambah();
        }
    }

    return (
        <div className="mt-6 grid gap-2 border-t pt-4">
            <Label htmlFor="tahun_mulai" className="text-sm font-medium">
                Tambah tahun ajaran
            </Label>
            <p className="text-muted-foreground text-sm">
                Isi tahun mulainya saja, misalnya 2027 untuk tahun ajaran 2027/2028.
            </p>
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    id="tahun_mulai"
                    inputMode="numeric"
                    maxLength={4}
                    className="w-32"
                    placeholder="2027"
                    value={tahunMulai}
                    onChange={(e) => setTahunMulai(e.target.value.replace(/\D/g, ''))}
                    onKeyDown={tekanEnter}
                />
                <Button type="button" variant="outline" disabled={!pratinjau || mengirim} onClick={tambah}>
                    <Plus className="mr-2 size-4" />
                    {pratinjau ? `Tambah ${pratinjau}` : 'Tambah'}
                </Button>
            </div>
            <InputError message={galat} />
        </div>
    );
}
