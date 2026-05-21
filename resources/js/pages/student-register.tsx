import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type School = { id: number; name: string; logo_path: string | null };
type Classroom = { id: number; school_id: number; name: string; grade_level: number };

type Props = {
    schools: School[];
    classrooms: Classroom[];
};

const religions = [
    { value: 'ISLAM', label: 'Islam' },
    { value: 'KRISTEN', label: 'Kristen Protestan' },
    { value: 'KATOLIK', label: 'Katolik' },
    { value: 'HINDU', label: 'Hindu' },
    { value: 'BUDDHA', label: 'Buddha' },
    { value: 'KONGHUCU', label: 'Konghucu' },
];

export default function StudentRegister({ schools, classrooms }: Props) {
    const { flash } = usePage().props as unknown as { flash: { success?: string } };
    const [submitted, setSubmitted] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        school_id: '',
        full_name: '',
        nis: '',
        no_absen: '',
        nisn: '',
        gender: '',
        religion: '',
        classroom_id: '',
        birth_place: '',
        birth_date: '',
        address: '',
        parent_name: '',
        parent_phone: '',
    });

    const selectedSchool = schools.find((s) => String(s.id) === data.school_id);
    const filteredClassrooms = classrooms.filter((c) => String(c.school_id) === data.school_id);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/daftar', {
            preserveScroll: true,
            onSuccess: () => {
                setSubmitted(true);
                reset();
            },
        });
    }

    function handleNewSubmission() {
        setSubmitted(false);
    }

    if (submitted && flash?.success) {
        return (
            <PageWrapper>
                <div className="mx-auto max-w-2xl px-4 py-16">
                    <div className="rounded-2xl border border-green-200 bg-green-50 p-8 text-center dark:border-green-800 dark:bg-green-950">
                        <CheckCircle2 className="mx-auto mb-4 size-16 text-green-600 dark:text-green-400" />
                        <h2 className="mb-2 text-2xl font-bold text-green-800 dark:text-green-200">Pendaftaran Berhasil!</h2>
                        <p className="mb-6 text-green-700 dark:text-green-300">{flash.success}</p>
                        <Button
                            onClick={handleNewSubmission}
                            className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/25"
                        >
                            Daftarkan Siswa Lain
                        </Button>
                    </div>
                </div>
                <Footer />
            </PageWrapper>
        );
    }

    return (
        <PageWrapper>
            <div className="mx-auto max-w-2xl px-4 py-8 sm:py-12">
                {/* Header */}
                <div className="mb-8 text-center">
                    {selectedSchool?.logo_path ? (
                        <img
                            src={`/storage/${selectedSchool.logo_path}`}
                            alt={selectedSchool.name}
                            className="mx-auto mb-4 size-16 rounded-xl object-contain"
                        />
                    ) : (
                        <div className="mx-auto mb-4 flex size-16 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600">
                            <AppLogoIcon className="size-8 fill-current text-white" />
                        </div>
                    )}
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Pendaftaran Data Siswa Baru</h1>
                    <p className="text-muted-foreground mt-2 text-sm sm:text-base">
                        Lengkapi data siswa untuk didaftarkan ke sistem absensi sekolah.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-8">
                    {/* Section 1: Pilih Sekolah */}
                    <FormSection number={1} title="Pilih Sekolah">
                        <div className="grid gap-2">
                            <Label htmlFor="school_id" className="text-sm font-medium">
                                Sekolah <span className="text-red-500">*</span>
                            </Label>
                            <Select value={data.school_id} onValueChange={(val) => { setData((prev) => ({ ...prev, school_id: val, classroom_id: '' })); }}>
                                <SelectTrigger className="h-11 w-full">
                                    <SelectValue placeholder="Pilih sekolah" />
                                </SelectTrigger>
                                <SelectContent>
                                    {schools.map((school) => (
                                        <SelectItem key={school.id} value={String(school.id)}>
                                            {school.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.school_id} />
                        </div>
                    </FormSection>

                    {/* Section 2: Data Siswa */}
                    <FormSection number={2} title="Data Siswa">
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="full_name" className="text-sm font-medium">
                                    Nama Lengkap <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="full_name"
                                    value={data.full_name}
                                    onChange={(e) => setData('full_name', e.target.value)}
                                    placeholder="Nama lengkap siswa"
                                    className="h-11"
                                />
                                <InputError message={errors.full_name} />
                            </div>

                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="nis" className="text-sm font-medium">NIS</Label>
                                    <Input
                                        id="nis"
                                        value={data.nis}
                                        onChange={(e) => setData('nis', e.target.value)}
                                        placeholder="Nomor Induk Siswa"
                                        className="h-11"
                                    />
                                    <InputError message={errors.nis} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="no_absen" className="text-sm font-medium">No. Absen</Label>
                                    <Input
                                        id="no_absen"
                                        value={data.no_absen}
                                        onChange={(e) => setData('no_absen', e.target.value)}
                                        placeholder="Nomor absen"
                                        className="h-11"
                                    />
                                    <InputError message={errors.no_absen} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="nisn" className="text-sm font-medium">NISN</Label>
                                    <Input
                                        id="nisn"
                                        value={data.nisn}
                                        onChange={(e) => setData('nisn', e.target.value)}
                                        placeholder="Nomor Induk Siswa Nasional"
                                        className="h-11"
                                    />
                                    <InputError message={errors.nisn} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-sm font-medium">
                                    Jenis Kelamin <span className="text-red-500">*</span>
                                </Label>
                                <RadioGroup
                                    value={data.gender}
                                    onValueChange={(v) => setData('gender', v)}
                                    className="flex gap-4"
                                >
                                    <label
                                        htmlFor="gender-l"
                                        className={`flex flex-1 cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition ${
                                            data.gender === 'LAKI_LAKI'
                                                ? 'border-blue-500 bg-blue-50 dark:bg-blue-950'
                                                : 'border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-900'
                                        }`}
                                    >
                                        <RadioGroupItem value="LAKI_LAKI" id="gender-l" />
                                        Laki-laki
                                    </label>
                                    <label
                                        htmlFor="gender-p"
                                        className={`flex flex-1 cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition ${
                                            data.gender === 'PEREMPUAN'
                                                ? 'border-pink-500 bg-pink-50 dark:bg-pink-950'
                                                : 'border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-900'
                                        }`}
                                    >
                                        <RadioGroupItem value="PEREMPUAN" id="gender-p" />
                                        Perempuan
                                    </label>
                                </RadioGroup>
                                <InputError message={errors.gender} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="religion" className="text-sm font-medium">Agama</Label>
                                <Select value={data.religion} onValueChange={(val) => setData('religion', val)}>
                                    <SelectTrigger className="h-11 w-full">
                                        <SelectValue placeholder="Pilih agama" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {religions.map((r) => (
                                            <SelectItem key={r.value} value={r.value}>
                                                {r.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.religion} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="classroom_id" className="text-sm font-medium">
                                    Kelas <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={data.classroom_id}
                                    onValueChange={(val) => setData('classroom_id', val)}
                                    disabled={!data.school_id}
                                >
                                    <SelectTrigger className="h-11 w-full">
                                        <SelectValue placeholder={data.school_id ? 'Pilih kelas' : 'Pilih sekolah terlebih dahulu'} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filteredClassrooms.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.classroom_id} />
                            </div>
                        </div>
                    </FormSection>

                    {/* Section 3: Data Kelahiran & Alamat */}
                    <FormSection number={3} title="Data Kelahiran & Alamat">
                        <div className="grid gap-5">
                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="birth_place" className="text-sm font-medium">Tempat Lahir</Label>
                                    <Input
                                        id="birth_place"
                                        value={data.birth_place}
                                        onChange={(e) => setData('birth_place', e.target.value)}
                                        placeholder="Kota kelahiran"
                                        className="h-11"
                                    />
                                    <InputError message={errors.birth_place} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="birth_date" className="text-sm font-medium">Tanggal Lahir</Label>
                                    <Input
                                        id="birth_date"
                                        type="date"
                                        value={data.birth_date}
                                        onChange={(e) => setData('birth_date', e.target.value)}
                                        className="h-11"
                                    />
                                    <InputError message={errors.birth_date} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address" className="text-sm font-medium">Alamat</Label>
                                <Textarea
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    placeholder="Alamat lengkap siswa"
                                    rows={3}
                                />
                                <InputError message={errors.address} />
                            </div>
                        </div>
                    </FormSection>

                    {/* Section 4: Data Orang Tua/Wali */}
                    <FormSection number={4} title="Data Orang Tua/Wali">
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="parent_name" className="text-sm font-medium">Nama Orang Tua/Wali</Label>
                                <Input
                                    id="parent_name"
                                    value={data.parent_name}
                                    onChange={(e) => setData('parent_name', e.target.value)}
                                    placeholder="Nama lengkap orang tua atau wali"
                                    className="h-11"
                                />
                                <InputError message={errors.parent_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="parent_phone" className="text-sm font-medium">No. WhatsApp</Label>
                                <div className="flex">
                                    <span className="border-input bg-muted/50 text-muted-foreground inline-flex items-center rounded-l-md border border-r-0 px-3.5 text-sm font-medium">
                                        +62
                                    </span>
                                    <Input
                                        id="parent_phone"
                                        type="tel"
                                        value={data.parent_phone}
                                        onChange={(e) => setData('parent_phone', e.target.value)}
                                        placeholder="812xxxxxxxx"
                                        className="h-11 rounded-l-none"
                                    />
                                </div>
                                <InputError message={errors.parent_phone} />
                            </div>
                        </div>
                    </FormSection>

                    {/* Submit */}
                    <Button
                        type="submit"
                        disabled={processing}
                        className="h-12 w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-base font-semibold text-white shadow-lg shadow-blue-500/25"
                    >
                        {processing ? <Spinner /> : 'Daftarkan Siswa'}
                    </Button>
                </form>
            </div>

            <Footer />
        </PageWrapper>
    );
}

function PageWrapper({ children }: { children: React.ReactNode }) {
    return (
        <>
            <Head title="Pendaftaran Data Siswa Baru" />
            <div className="bg-zinc-50 dark:bg-zinc-950 min-h-screen">
                {children}
            </div>
        </>
    );
}

function FormSection({ number, title, children }: { number: number; title: string; children: React.ReactNode }) {
    return (
        <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
            <div className="mb-5 flex items-center gap-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 text-sm font-bold text-white">
                    {number}
                </span>
                <h2 className="text-lg font-semibold">{title}</h2>
            </div>
            {children}
        </div>
    );
}

function Footer() {
    return (
        <footer className="border-t border-zinc-200 py-6 text-center dark:border-zinc-800">
            <p className="text-muted-foreground text-sm">
                Powered by <span className="font-semibold">Absensi OZOLAB</span>
            </p>
        </footer>
    );
}
