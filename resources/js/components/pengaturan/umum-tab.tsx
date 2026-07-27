import { useForm } from '@inertiajs/react';
import { Building2, Clock, Save } from 'lucide-react';
import { type FormEvent, useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { SettingsValues } from './settings-tabs';

type UmumData = {
    section: 'umum';
    school_name: string;
    timezone: string;
};

export function UmumTab({
    settings,
    onDirtyChange,
}: {
    settings: SettingsValues;
    onDirtyChange: (dirty: boolean) => void;
}) {
    const { data, setData, put, processing, errors, isDirty } = useForm<UmumData>({
        // `section` ikut di dalam payload supaya controller tahu ruleset mana
        // yang dipakai; ia di-unset sebelum merge ke schools.settings.
        section: 'umum',
        school_name: (settings.school_name as string) || '',
        timezone: (settings.timezone as string) || 'Asia/Jakarta',
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

            <div className="flex justify-end">
                <Button type="submit" disabled={processing || !isDirty}>
                    <Save className="mr-2 size-4" />
                    Simpan Pengaturan Umum
                </Button>
            </div>
        </form>
    );
}
